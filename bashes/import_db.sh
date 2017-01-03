#!/bin/bash
#set -euo pipefail
#IFS=$'\n\t'

echo "Starting Import of Database"
echo "Changing to the active ${ACQUIA_SITEGROUP_DST}.${DST_ENV} directory [ /var/www/html/${ACQUIA_SITEGROUP_DST}.${DST_ENV}/docroot/ ]"

cd /var/www/html/${ACQUIA_SITEGROUP_DST}.${DST_ENV}/docroot/

echo "Now running database import for db name [ ${ACQUIA_DB} ] with source [ ${RAW_DB_FILE} ]"
echo "drush @${ACQUIA_SITEGROUP_DST}.${DST_ENV} ah-db-import --db=${ACQUIA_DB} --drop --force ${DST_IMPORT_DIR}/${RAW_DB_FILE}"

if [[ -e "${DST_IMPORT_DIR}/${RAW_DB_FILE}" ]] ; then
  drush @${ACQUIA_SITEGROUP_DST}.${DST_ENV} ah-db-import --db=${ACQUIA_DB} --drop --force ${DST_IMPORT_DIR}/${RAW_DB_FILE}
else
  echo "Source file [ ${DST_IMPORT_DIR}/${RAW_DB_FILE} ] does not exist."
fi
