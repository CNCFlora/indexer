<?php
include 'parseHeaders.php';
include 'reindex_docs.php';

if(!isset($argv[1])) {
    echo "Which host and which database?\n";
  exit;
}

if(!isset($argv[2])) {
  echo "Which database?\n";
  exit;
}

$host = $argv[1];
$db = $argv[2];

define("COUCHDB",'http://'.$host.':5984/'.$db);
define("ELASTICSEARCH", 'http://'.$host.':9200/');

echo "Host: ".$host."\n";
echo "Database: ".$db."\n";

// Index one specific document
if(isset($argv[3])) {

    $doc = json_decode(file_get_contents(COUCHDB."/".rawurlencode($argv[3])));
    $all = new \stdclass;
    $all->rows = array();
    $all->rows[0] = new \stdclass;
    $all->rows[0]->doc = $doc;
    $db_to_insert = $db;

} else {
    // Reindex all documents from database
    //passthru("curl '".COUCHDB."/_all_docs?include_docs=true' -o '".$db.".json'");
    passthru("curl '".COUCHDB."/_changes?include_docs=true' -o '".$db.".json'");
    $all = new StdClass;
    $all->rows = array();
    $af = fopen($db.".json",'r');
    fgets($af);
    while($l = fgets($af)){
        if (substr($l, 0, 1) != '{') {
            $l = '{'.$l;
        }
        $all->rows[] = json_decode(rtrim(rtrim($l), ","));
    }
    //Clean parsed array
    foreach ($all->rows as $key => $doc){
        //Remove null or deleted entries
        if (is_null($doc) || (array_key_exists("deleted", $doc) && $doc->deleted == true)){
            unset($all->rows[$key]);
        }
        // Get last sequence number
        elseif (array_key_exists("last_seq", $doc)){
            $last_seq = $doc->last_seq;
            unset($all->rows[$key]);
        }
    }

    // Only delete index and recreate it if reindexing all documents from a db
    // Otherwise, keep old index
    // Get old index
    $response = file_get_contents(ELASTICSEARCH."/".$db."*");
    $index_obj = json_decode($response);
    $index_obj = get_object_vars($index_obj);
    if (count($index_obj) > 1) {
        echo "More than one database found. Using delete and create method.\n";
        // Not the desired behavior. What is going on?
        $curl = curl_init(ELASTICSEARCH."/".$db);
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "DELETE");
        curl_setopt($curl, CURLOPT_HEADER, false);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HTTPHEADER, array('Content-Type: application/json', 'Content-Length: 0'));

        //Make the REST call, returning the result
        $response = curl_exec($curl);
        if (!$response) {
            die("Connection Failure.n");
        }
        echo $response."\n";

        $curl = curl_init(ELASTICSEARCH."/".$db);
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "PUT");
        curl_setopt($curl, CURLOPT_HEADER, false);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HTTPHEADER, array('Content-Type: application/json', 'Content-Length: 0'));

        //Make the REST call, returning the result
        $response = curl_exec($curl);
        if (!$response) {
            die("Connection Failure.\n");
        }
        echo $response."\n";
        $db_to_insert = $db;
    }
    else {
        // Get old index
        foreach($index_obj as $key => $val){
            $old_index = $key;
        }

        $new_index = rawurlencode($db).'_'.date('Y_m_d_H:i');
        echo "Creating index $new_index.\n";

        // Add new index
        $curl = curl_init(ELASTICSEARCH."/".$new_index);
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "PUT");
        curl_setopt($curl, CURLOPT_HEADER, false);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HTTPHEADER, array('Content-Type: application/json', 'Content-Length: 0'));

        $response = curl_exec($curl);
        if (!$response) {
            die("Connection Failure.\n");
        }
        echo $response."\n";
        $db_to_insert = $new_index;
    }
}

// Reindex docs
list($inserted, $not_inserted) = reindex_docs($all, $db_to_insert);
echo count($inserted)." records inserted.\n";
echo count($not_inserted)." records NOT inserted.\n";

file_put_contents('not_inserted.json', json_encode($not_inserted));
file_put_contents('inserted.json', json_encode($inserted));

// Create alias and remove old index if whole db was reindexed or just indexed
// for the 1st time
if(isset($new_index)) {
    // Create alias and delete old alias
    if (isset($old_index)) {
        $alias_obj = (object)[ 'actions' =>
            (array) [(object)['remove' => (object) [
                'index' => $old_index,
                'alias' => $db]],
                (object)['add' => (object)[
                    'index' => $new_index,
                    'alias' => $db]]
                ]
            ];
    }
    // Just create alias
    else {
        $alias_obj = (object)[ 'actions' =>
            (array) [(object)['add' => (object)[
                'index' => $new_index,
                'alias' => $db]]
            ]
        ];
    }

    $opts = ['http'=>['method'=>'POST','content'=>json_encode($alias_obj),'header'=>'Content-type: application/json']];
    $response = file_get_contents(ELASTICSEARCH."/_aliases", NULL, stream_context_create($opts));
    $header = parseHeaders($http_response_header);
    // Doing it for the 1st time
    if ($header['response_code'] == 400) {
        // If using temp alias, stop new insertions on index
        $curl = curl_init(ELASTICSEARCH."/".$old_index."/_settings");
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "PUT");
        curl_setopt($curl, CURLOPT_HEADER, false);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
        curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query(array("index.blocks.write" => true)));

        //Make the REST call, returning the result
        $response = curl_exec($curl);
        if (!$response) {
            die("Connection Failure.\n");
        }
        echo "Stopping index $old_index for insertions.\n";
        echo $response."\n";
        echo "Alias $db exists as an index. Creating temp alias instead.\n";
        $alias_obj->actions[1]->add->alias = $db."_temp";
        $opts = ['http'=>['method'=>'POST','content'=>json_encode($alias_obj),'header'=>'Content-type: application/json']];
        $response = file_get_contents(ELASTICSEARCH."/_aliases", NULL, stream_context_create($opts));

    }
    echo "Creating alias ".$alias_obj->actions[sizeof($alias_obj->actions)-1]->add->alias."\n";
    echo $response."\n";

    // Check if new documents were inserted
    $response = file_get_contents(COUCHDB."/_changes?include_docs=true&since=".$last_seq);
    $changes = json_decode("$response");
    $new_inserted = array();
    $new_not_inserted = array();
    $removed = 0;
    if ($last_seq != $changes->last_seq){
        echo "Some entries were inserted or deleted during reindexing.\n";
        $new_docs = new StdClass;
        $new_docs->rows = array();
        $new_docs->rows = $changes->results;
        //Clean parsed array
        foreach ($new_docs->rows as $key => $doc){
            //Remove deleted entries
            if (array_key_exists("deleted", $doc) && $doc->deleted == true){
                delete_doc($doc, $db_to_insert);
                $removed += 1;
                unset($new_docs->rows[$key]);
            }
        }
        list($new_inserted, $new_not_inserted) = reindex_docs($new_docs, $db_to_insert);
        echo count($new_inserted)." records inserted.\n";
        echo count($new_not_inserted)." records NOT inserted.\n";
        echo $removed." records were removed.\n";
    }

    // Delete old index
    if (isset($old_index)) {
        $curl = curl_init(ELASTICSEARCH."/".$old_index);
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "DELETE");
        curl_setopt($curl, CURLOPT_HEADER, false);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HTTPHEADER, array('Content-Type: application/json', 'Content-Length: 0'));

        //Make the REST call, returning the result
        $response = curl_exec($curl);
        if (!$response) {
            die("Connection Failure.n");
        }
        echo "Removing index $old_index.\n";
        echo $response."\n";
    }

    // Rename alias if necessary
    if (isset($alias_obj)){
        if ($alias_obj->actions[sizeof($alias_obj->actions)-1]->add->alias == $db."_temp" ) {
            $alias_obj->actions[0]->remove->alias = $db."_temp";
            $alias_obj->actions[0]->remove->index = $new_index;
            $alias_obj->actions[1]->add->alias = $db;
            $opts = ['http'=>['method'=>'POST','content'=>json_encode($alias_obj),'header'=>'Content-type: application/json']];
            $response = file_get_contents(ELASTICSEARCH."/_aliases", NULL, stream_context_create($opts));
            echo "Renaming alias ".$alias_obj->actions[1]->add->alias."_temp to ".$alias_obj->actions[1]->add->alias."\n";
            echo $response."\n";
        }
    }
    $total_inserted = count($inserted) + count($new_inserted);
    $total_not_inserted = count($not_inserted) + count($new_not_inserted);
    echo $total_inserted." records inserted.\n";
    echo $total_not_inserted." records NOT inserted.\n";
    echo $removed." records were removed.\n";
}
