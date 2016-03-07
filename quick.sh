#!/bin/bash

curl "http://cncflora.jbrj.gov.br/couchdb/$1/$2" -o doc.json 
TYPE0=$(cat doc.json | grep '\"type\":\"[a-z]\+\"' -o)
TYPE=${TYPE0:8:-1}
ID0=$(cat doc.json | grep '\"_id\":\"[^"]\+\"' -o)
ID=${ID0:7:-1}
sed -i -e 's/_rev/rev/' doc.json
sed -i -e 's/_id/id/' doc.json

curl -X POST "http://cncflora.jbrj.gov.br/elasticsearch/$1/$TYPE/$ID" --data-binary @doc.json -H 'Content-Type: application/json'

rm doc.json

