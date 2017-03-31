#!/bin/bash

SERVER="http://$1"
COUCH="$SERVER:5984"
ES="$SERVER:9200"
DB=$2
filename='ids.txt'
while IFS='' read -r ID; do
  #
  #
  # A SOLUÇÃO É, TROCAR METADATA PARA METADATA_OLD, CONCATENAR O METADATA ANTIGO COM AS NOVAS VARIAVEIS E FECHAR }}
  #
  #
  #
  URL0="$COUCH/$DB/$ID/"

  #alterando metadata para metadata2
  OLD=$(curl -X GET "${URL0}" | sed -e 's/metadata/metadata_old/g')

  #pega tudo depois dentro de "metadata" e substitui creator por creator_old
  OMET=$(curl -X GET "${URL0}" | grep -ioE '\"\bmetadata\b.(.[^\}]*)' | sed -e 's/contributor/contibutor_old/g' | sed -e 's/cncflora@cncflora.jbrj.gov.br/contato@cncflora.net/g')

  #removendo a última ocorrência do }
  #RET1=$(curl -X GET "${URL0}" | grep -ioE '\}([^:]+)$\' | sed -e 's/}//g')

  #trocando metadata para metadata_old, removendo }}, para algo temporário, tirando os }} últimos para concatenar depois e retornando com o }},
  ALL=$(curl -X GET "${URL0}" | sed -e 's/metadata/metadata_old/g' | sed -e 's/}},/_++/g' | sed -e 's/}}/}/g' | sed -e 's/_++/}},/g')

  FINAL='"validatedBy":"Sheila Regina Profice","georeferencedBy":"Thiago Serrano de Almeida Penedo","contributor":"Luiz Antonio Ferreira dos Santos Filho, Pablo Viany Prieto, Thiago Serrano de Almeida Penedo, Julia Caram Sfair","evaluator":"Julia Caram Sfair","reviewer":"Tainan Messina"}'
  RET3=$ALL","$OMET","$FINAL"}"

  echo $RET3 > teste.json
  echo $ID
done < ids.txt;
curl -X PUT -d @teste.json $URL0
