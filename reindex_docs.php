<?php
include_once 'parseHeaders.php';

function reindex_docs($all, $db_to_insert){
    $docs = [];
    $inserted = [];
    $not_inserted = [];
    $i=0;
    $counter_after = 0;
    foreach($all->rows as $row) {
        $i++;
        $doc = $row->doc;
        $doc->id = $doc->_id;
        $doc->rev=  $doc->_rev;
        unset($doc->_id);
        unset($doc->_rev);
        $json = json_encode($doc);

        $counter_before = $counter_after;
        $opts = ['http'=>['method'=>'POST','content'=>$json,'header'=>'Content-type: application/json']];
        $response = file_get_contents(ELASTICSEARCH."/".$db_to_insert."/".$doc->metadata->type."/".urlencode($doc->id), NULL, stream_context_create($opts));
        //echo $response."\n";

        // Get response code
        $header = parseHeaders($http_response_header);
        if (($header['response_code'] == 200) || ($header['response_code'] == 201)){
            $counter_after = $counter_before + 1;
        }

        // Increase counter
        if ($counter_before == $counter_after-1) {
            $inserted[] = $doc->id;
        } else {
            $not_inserted[] = $doc->id;
        }
    }
    return array($inserted, $not_inserted);
}

function delete_doc($doc, $db_to_insert)
{
    var_dump($doc);
    //Make sure we have all data before trying to delete
    if (array_key_exists("_id", $doc) && !is_null($doc->_id)) {

        echo "Deleting doc ".$doc->_id."\n";

        $curl = curl_init(ELASTICSEARCH."/".$db_to_insert."/_query?q=_id:\"".$doc->_id."\"");
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "DELETE");
        curl_setopt($curl, CURLOPT_HEADER, false);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HTTPHEADER, array('Content-Type: application/json', 'Content-Length: 0'));

        //Make the REST call, returning the result
        $response = curl_exec($curl);
        if (!$response) {
            die("Connection Failure.\n");
        }
        echo $response."\n";
    }
}
