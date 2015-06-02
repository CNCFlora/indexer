#!/bin/bash

DB=$1
FILE=$2

cat $FILE | xargs -I{} -d"\n" ./rm.sh $DB {}

