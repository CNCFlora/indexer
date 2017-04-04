#!/bin/bash

SERVER="http://$1"
COUCH="$SERVER:5984"
ES="$SERVER:9200"
DB=$2
FILE=$3
while IFS='' read -r ID; do
  #
  #
  # A SOLUÇÃO É, TROCAR METADATA PARA METADATA_OLD, CONCATENAR O METADATA ANTIGO COM AS NOVAS VARIAVEIS E FECHAR }}
  #
  #
  #
  URL0="$COUCH/$DB/$ID/"

  #alterando scientificName
  OLD=$(curl -X GET "${URL0}" | sed -e 's/Schinus spinosus/Schinus spinosa/g' | sed -e 's/spinosus/spinosa/g')

  #echo $OLD
  echo $OLD > teste2.json
  curl -X PUT -d @teste2.json $URL0
done < "$FILE";
