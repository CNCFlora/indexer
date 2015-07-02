<?php
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

define("COUCHDB", $host.':5984/'.$db);
define("ELASTICSEARCH", $host.':9200/'.$db);

echo "Host:".$host."\n";
echo "Database: ".$db."\n";

if(isset($argv[3])) {
    $doc = json_decode(file_get_contents(COUCHDB."/".rawurlencode($argv[3])));
  $all = new \stdclass;
  $all->rows = array();
  $all->rows[0] = new \stdclass;
  $all->rows[0]->doc = $doc;
} else {
  passthru("curl '".COUCHDB."/_all_docs?include_docs=true' -o '".$db.".json'");
  $all = json_decode(file_get_contents($db.".json"));
}

$curl = curl_init(ELASTICSEARCH);
curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "DELETE");
curl_setopt($curl, CURLOPT_HEADER, false);
curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
curl_setopt($curl, CURLOPT_HTTPHEADER, array('Content-Type: application/json', 'Content-Length: 0'));

// Make the REST call, returning the result
$response = curl_exec($curl);
if (!$response) {
    die("Connection Failure.n");
}
echo $response."\n";

curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "PUT");
curl_setopt($curl, CURLOPT_HEADER, false);
curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
curl_setopt($curl, CURLOPT_HTTPHEADER, array('Content-Type: application/json', 'Content-Length: 0'));

// Make the REST call, returning the result
$response = curl_exec($curl);
if (!$response) {
    die("Connection Failure.n");
}
echo $response."\n";

$docs = [];
$inserted = [];
$not_inserted = [];
foreach($all->rows as $row) {
    $doc = $row->doc;
    $doc->id = $doc->_id;
    $doc->rev=  $doc->_rev;
    unset($doc->_id);
    unset($doc->_rev);
    $json = json_encode($doc);
    $opts_count = ['http'=>['method'=>'GET','header'=>'Content-type: application/json']];
    $counter_response = file_get_contents(ELASTICSEARCH."/_count", NULL, stream_context_create($opts_count));
    $counter_before = json_decode($counter_response)->count;

    $opts = ['http'=>['method'=>'POST','content'=>$json,'header'=>'Content-type: application/json']];
    echo file_get_contents(ELASTICSEARCH."/".$doc->metadata->type."/".urlencode($doc->id), NULL, stream_context_create($opts))."\n";
    $refresh_response = file_get_contents(ELASTICSEARCH."/_refresh", NULL, stream_context_create($opts_count));

    $counter_response = file_get_contents(ELASTICSEARCH."/_count", NULL, stream_context_create($opts_count));
    $counter_after = json_decode($counter_response)->count;

    if ($counter_before == $counter_after-1) {
        $inserted[] = $doc->id;
    } else {
        $not_inserted[] = $doc->id;
    }
}

echo count($inserted)." records inserted.\n";
echo count($not_inserted)." records NOT inserted.\n";

file_put_contents('not_inserted.txt', var_export($not_inserted, true));
