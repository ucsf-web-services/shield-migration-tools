#!/bin/bash
#set -o errexit ; set -o nounset
#set -euo pipefail
#IFS=$'\n\t'

source ./bashes/functions.sh
source ./bashes/key_handler.sh

if [[ "${MIGRATION_DRY_RUN}" -eq 0 ]]; then
  DRY_RUN=
else
  DRY_RUN="--dry-run"
fi

verbose "Start: `date -u`" ""
MIGRATION_START=`date -u +%s`

MYSQL_OPTS="--complete-insert --disable-keys --single-transaction -u ${SRC_DB_USER} -h ${SRC_DB_HOST}"

echo " --------------------------------------------------------"
# Get Source Database Password
echo `now` "EXPORTING DATABASE FOR ${MIGRATION_SITE_NAME}  "
echo " --------------------------------------------------------"
# Dump Structure
echo `now` ">> Making sure ${SRC_EXPORT_DIR} exists"
mkdir -vp ${SRC_EXPORT_DIR}

echo `now` ">>> Generating list of tables"
TABLES=`mysql --skip-column-names -e 'show tables' -u ${SRC_DB_USER} -p${SRC_DB_PASS} -h ${SRC_DB_HOST} ${SRC_DB_NAME}`
echo `now` ">>> dumping structure to ${SRC_EXPORT_DIR}/${SRC_DB_NAME}.sql"
echo "mysqldump --no-data ${MYSQL_OPTS} --password=${SRC_DB_PASS} ${SRC_DB_NAME} > ${SRC_EXPORT_DIR}/${SRC_DB_SETTING_NAME}.${SRC_DB_NAME}.sql"
mysqldump --no-data ${MYSQL_OPTS} --password=${SRC_DB_PASS} ${SRC_DB_NAME} > ${SRC_EXPORT_DIR}/${SRC_DB_SETTING_NAME}.${SRC_DB_NAME}.sql

# Dump Data, Excluding Certain Tables, removing with cause seems to be causing issues.
echo `now` ">>> Generating list of tables to exclude data from."
TABLES2=`echo "$TABLES" | grep -Ev "${SRC_DB_SKIP_DATA}"`

echo `now` ">>> dumping SQL data... patience please."

echo "mysqldump --no-create-info ${MYSQL_OPTS} --password=${SRC_DB_PASS} ${SRC_DB_NAME} >> ${SRC_EXPORT_DIR}/${SRC_DB_SETTING_NAME}.${SRC_DB_NAME}.sql"
mysqldump --no-create-info ${MYSQL_OPTS} --password=${SRC_DB_PASS} ${SRC_DB_NAME} >> ${SRC_EXPORT_DIR}/${SRC_DB_SETTING_NAME}.${SRC_DB_NAME}.sql

echo `now` ">>> gzipping ${SRC_EXPORT_DIR}/${SRC_DB_SETTING_NAME}.${SRC_DB_NAME}.sql"
gzip -vf ${SRC_EXPORT_DIR}/${SRC_DB_SETTING_NAME}.${SRC_DB_NAME}.sql
SQLDUMP=${SRC_DB_SETTING_NAME}.${SRC_DB_NAME}.sql.gz
#gonna use the src_db_name on the other side to import the exact same db name, since the settings files are ready to go.
#SRC_DB_SETTING_NAME is what we will use as the when creating the database I believe.
echo `now` ">>> Saving export file details "
echo "${MIGRATION_SITE_NAME}|${SRC_DB_SETTING_NAME}|${SRC_DB_NAME}|${SQLDUMP}" >> ${SRC_EXPORT_DIR}/export_progress.csv

echo `now` ">> Making sure ${DST_IMPORT_DIR} exists"
${DST_SSH_CMD} "mkdir -vp ${DST_IMPORT_DIR}"

echo `now` ">>> copying to Acquia Cloud"

if [ "$SRC_SSH_LOGIN" == "$DST_SSH_LOGIN" ]; then
  echo "rsync --progress --recursive ${DRY_RUN} ${SRC_EXPORT_DIR}/${SQLDUMP} ${DST_IMPORT_DIR}/${SQLDUMP}"
  rsync --progress --recursive ${DRY_RUN} ${SRC_EXPORT_DIR}/${SQLDUMP} ${DST_IMPORT_DIR}/${SQLDUMP}
else
  echo "rsync --progress --recursive ${DRY_RUN} ${SRC_EXPORT_DIR}/${SQLDUMP} ${DST_SSH_LOGIN}@${DST_HOST}:${DST_IMPORT_DIR}/${SQLDUMP}"
  rsync --progress --recursive ${DRY_RUN} ${SRC_EXPORT_DIR}/${SQLDUMP} ${DST_SSH_LOGIN}@${DST_HOST}:${DST_IMPORT_DIR}/${SQLDUMP}
fi

MIGRATION_END=`date -u +%s`

RUNTIME=`expr $MIGRATION_END - $MIGRATION_START`
verbose "End: `date -u`, Runtime: `prettytime ${RUNTIME}`" ""
