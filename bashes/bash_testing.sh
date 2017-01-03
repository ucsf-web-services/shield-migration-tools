#!/bin/bash
source ./bashes/functions.sh
source ./bashes/key_handler.sh

IMPORT_TIMESTAMP=$(date -u +""%F-%s"")
PS4="\D{%T} >> "
verbose "Start: `date -u`" ""
MIGRATION_START=`date -u +%s`

#ssh_build
echo "--complete-insert --disable-keys --single-transaction -u ${SRC_DB_USER} -h ${SRC_DB_HOST}"

# Get Source Database Password
echo `now` "Please provide the password for ${SRC_DB_USER} on db ${SRC_DB_NAME} hosted at ${SRC_DB_HOST}:"
#read -se SRC_DB_PASS

# Dump Structure
echo `now` ">> Making sure ${SRC_EXPORT_DIR} exists"
echo "mkdir -vp ${SRC_EXPORT_DIR}"

echo `now` ">>> Generating list of tables"
echo "mysql --skip-column-names -e 'show tables' -u ${SRC_DB_USER} -p${SRC_DB_PASS} -h ${SRC_DB_HOST} ${SRC_DB_NAME}"
echo ">>> dumping structure to ${SRC_EXPORT_DIR}/${SRC_DB_NAME}.${IMPORT_TIMESTAMP}.sql"
echo "mysqldump ${MYSQL_OPTS} --no-data --password=${SRC_DB_PASS} ${SRC_DB_NAME} ${TABLES} >> ${SRC_EXPORT_DIR}/${SRC_DB_NAME}.${IMPORT_TIMESTAMP}.sql"

# Dump Data, Excluding Certain Tables
#echo `now` ">>> Generating list of tables to exclude data from."
#echo "TABLES2= $TABLES | grep -Ev \"${SRC_DB_SKIP_DATA}\""
#echo `now` ">>> dumping data to ${SRC_EXPORT_DIR}/${SRC_DB_NAME}.${IMPORT_TIMESTAMP}.sql... patience please."
#echo "mysqldump ${MYSQL_OPTS} --no-create-info --password=${SRC_DB_PASS} ${SRC_DB_NAME} ${TABLES2}"
#echo ">> ${SRC_EXPORT_DIR}/${SRC_DB_NAME}.${IMPORT_TIMESTAMP}.sql"

# echo `now` ">>> gzipping ${SRC_EXPORT_DIR}/${SRC_DB_NAME}.${IMPORT_TIMESTAMP}.sql"
# echo "gzip -vf ${SRC_EXPORT_DIR}/${SRC_DB_NAME}.${IMPORT_TIMESTAMP}.sql"
# echo "SQLDUMP=${SRC_DB_NAME}.${IMPORT_TIMESTAMP}.sql.gz"
#
# echo `now` ">> Making sure ${DST_IMPORT_DIR} exists"
# echo "${SSH_CMD} mkdir -vp ${DST_IMPORT_DIR}"
#
# echo `now` ">>> copying to Acquia Cloud"
# echo "scp -i ${SSH_KEY} ${SRC_EXPORT_DIR}/${SQLDUMP} ${ACQUIA_SITEGROUP}@${DST_HOST}:${DST_IMPORT_DIR}"
#
#
 MIGRATION_END=`date -u +%s`
#
 RUNTIME=`expr $MIGRATION_END - $MIGRATION_START`
 verbose "End: `date -u`, Runtime: `prettytime ${RUNTIME}`" ""
