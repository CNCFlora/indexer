#!/bin/bash

SERVER="http://$1"
COUCH="$SERVER:5984"
ES="$SERVER:9200"
DB=$2
filename='ids.txt'
while IFS= read -r ID; do
  #endOfFile when find ABCDE
  if [ "$ID" == "ABCDE" ] ; then
    break
  else
    URL0="$COUCH/$DB/$ID"

    REV=$(curl "${URL0}" | grep '"_rev":"\([a-zA-Z0-9\-]\+\)"' -o | sed -e 's/"//g' -e 's/_rev://')
    URL1="$COUCH/$DB/$ID?rev=$REV"
    echo $URL1
    curl -X DELETE $URL1

    URL2="$ES/$DB/occurrence/$ID"
    echo $URL2

    curl -X DELETE $URL2
  fi
done < ids.txt;
