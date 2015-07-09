#!/bin/bash

SERVER="http://$ARGV[0]"
COUCH="$SERVER:5984"
ES="$SERVER:9200"
DB=$1
ID=$(perl -MURI::Escape -e 'print uri_escape($ARGV[1]);' "$2")
URL0="$COUCH/$DB/$ID"

REV=$(curl "${URL0}" | grep '"_rev":"\([a-zA-Z0-9\-]\+\)"' -o | sed -e 's/"//g' -e 's/_rev://')
URL1="$COUCH/$DB/$ID?rev=$REV"
echo $URL1
curl -X DELETE $URL1

#TYPE=$(curl "${URL0}" | grep '"type":"\([a-z]\+\)"' -o | sed -e 's/"//g' -e 's/type://')
URL2="$ES/$DB/occurrence/$ID"
echo $URL2

curl -X DELETE $URL2

