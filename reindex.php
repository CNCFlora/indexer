<?php

if(!isset($argv[1])) {
  echo "Which database?\n";
  exit;
}

$db = $argv[1];

define("COUCHDB","http://cncflora.jbrj.gov.br/couchdb/".$db);
define("ELASTICSEARCH","http://cncflora.jbrj.gov.br/elasticsearch/".$db);

echo "Database: ".$db."\n";

if(isset($argv[2])) {
  $doc = json_decode(file_get_contents(COUCHDB."/".$argv[2]));
  $all = new \stdclass;
  $all->rows = array();
  $all->rows[0] = new \stdclass;
  $all->rows[0]->doc = $doc;
} else {
  passthru("curl '".COUCHDB."/_all_docs?include_docs=true' -o '".$db.".json'");
  $all = json_decode(file_get_contents($db.".json"));
}


$docs = [];
foreach($all->rows as $row) {
    $doc = $row->doc;
    $doc->id = $doc->_id;
    $doc->rev=  $doc->_rev;
    unset($doc->_id);
    unset($doc->_rev);
    $json = json_encode($doc);
    $opts = ['http'=>['method'=>'POST','content'=>$json,'header'=>'Content-type: application/json']];
    echo file_get_contents(ELASTICSEARCH."/".$doc->metadata->type."/".urlencode($doc->id), NULL, stream_context_create($opts))."\n";
}


