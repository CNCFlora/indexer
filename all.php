<?php

if(!isset($argv[1])) {
    echo "Which host?\n";
  exit;
}
$host = $argv[1];
define("COUCHDB", $host.':5984/');
$all = json_decode(file_get_contents(COUCHDB."_all_dbs"));

foreach($all as $db) {
  if(!preg_match("/^_/",$db) && !preg_match("/_history$/",$db)) {
    passthru("php reindex.php ".$host." ".$db);
  }
}
