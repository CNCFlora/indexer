<?php

$all = json_decode(file_get_contents("http://cncflora.jbrj.gov.br/couchdb/_all_dbs"));

foreach($all as $db) {
  if(!preg_match("/^_/",$db) && !preg_match("/_history$/",$db)) {
    passthru("php reindex.php ".$db);
  }
}
