# Indexer

Script to index a database on demand, or remove documents.

## Configurations

    apt-get install php5-cli php5-curl

## Re-index a single document

    php reindex.php SERVER DATABASE_NAME DOCUMENT_ID

Example:

    php reindex.php jb048 endemicas_rio_de_janeiro occurrence:rj:123

## Re-index a whole database

    php reindex.php SERVER DATABASE_NAME

Example:

    php reindex.php jb048 endemicas_rio_de_janeiro


## Re-index all databases

    php all.php jb48

## Remove a document from db and index

    cd rm
    ./rm.sh SERVER DATABASE ID

Example

    ./rm.sh jb048 endemicas_rio_de_janeiro occurrence:rj:123

## Remove a list of documents

Create a file with earch as a document ID

    cd rm
    ./rm_all.sh SERVER DATABASE FILE

Example:

    cd rm
    echo id1 > ids
    echo id2 >> ids
    echo id3 >> ids
    ./rm_all.sh jb948 endemicas_rio_de_janeiro ids

