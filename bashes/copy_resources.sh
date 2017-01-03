#!/bin/bash

set -euo pipefail
IFS=$'\n\t'

source ./bashes/functions.sh
source ./bashes/key_handler.sh

if [[ "${MIGRATION_DRY_RUN}" -eq 0 ]]; then
  DRY_RUN=
else
  DRY_RUN="--dry-run"
fi

if [ "$SRC_SSH_LOGIN" == "$DST_SSH_LOGIN" ]; then
  echo "We are copying locally between ${SRC_DIRECTORY} and ${DST_DIRECTORY}."
  echo `now` ">> Copying from ${SRC_DIRECTORY} to ${DST_DIRECTORY}"
  echo "rsync --progress --recursive ${DRY_RUN} ${SRC_DIRECTORY}/sites/${MIGRATION_SITE_NAME}/files/  ${DST_DIRECTORY}/sites/${MIGRATION_SITE_NAME}/files/"
  rsync --progress --recursive ${DRY_RUN} ${SRC_DIRECTORY}/sites/${MIGRATION_SITE_NAME}/files/  ${DST_DIRECTORY}/sites/${MIGRATION_SITE_NAME}/files/
  echo "Now syncing sites private files."
  echo "rsync --progress --recursive ${DRY_RUN} ${SRC_DIRECTORY}/sites/${MIGRATION_SITE_NAME}/files-private/ ${DST_DIRECTORY}/sites/${MIGRATION_SITE_NAME}/files-private/"
  rsync --progress --recursive ${DRY_RUN} ${SRC_DIRECTORY}/sites/${MIGRATION_SITE_NAME}/files-private/ ${DST_DIRECTORY}/sites/${MIGRATION_SITE_NAME}/files-private/

else
  echo "We must Rsync the files from this server to the other server."
  #echo "${SRC_SSH_CMD}"
  #${SRC_SSH_CMD} don't think this is actually needed cause this will log you into the server your trying to transfer files from...not what we want.
  echo "rsync --progress --recursive ${DRY_RUN} ${SRC_SSH_LOGIN}@${SRC_HOST}:${SRC_DIRECTORY}/sites/${MIGRATION_SITE_NAME}/files/ ${DST_DIRECTORY}/sites/${MIGRATION_SITE_NAME}/files/"
  rsync --progress --recursive ${DRY_RUN} ${SRC_SSH_LOGIN}@${SRC_HOST}:${SRC_DIRECTORY}/sites/${MIGRATION_SITE_NAME}/files/ ${DST_DIRECTORY}/sites/${MIGRATION_SITE_NAME}/files/
  echo "Now syncing sites private files."
  echo "rsync --progress --recursive ${DRY_RUN} ${SRC_SSH_LOGIN}@${SRC_HOST}:${SRC_DIRECTORY}/sites/${MIGRATION_SITE_NAME}/files-private/ ${DST_DIRECTORY}/sites/${MIGRATION_SITE_NAME}/files-private/"
  rsync --progress --recursive ${DRY_RUN} ${SRC_SSH_LOGIN}@${SRC_HOST}:${SRC_DIRECTORY}/sites/${MIGRATION_SITE_NAME}/files-private/   ${DST_DIRECTORY}/sites/${MIGRATION_SITE_NAME}/files-private/
fi
