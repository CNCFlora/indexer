#!/bin/bash

SERVER=$1
DB=$2
FILE=$3

cat $FILE | xargs -I{} -d"\n" ./rm.sh $SERVER $DB {}

