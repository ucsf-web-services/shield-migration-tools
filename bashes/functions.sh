#!/bin/bash
IMPORT_TIMESTAMP=$(date +%Y-%m-%d)
PS4="\D{%T} >> "
# Do not edit below this line
function now {
  date -u +%T
}

#export -fx now

function comment {
  VERBOSE_COMMENT=$1
  echo "         #"
  echo "         #  ${VERBOSE_COMMENT}"
  echo "         #"
}

#export -fx comment

function verbose {
  comment "$1"
  VERBOSE_CMD=$2
  if [[ ! -z "${VERBOSE_CMD}" ]]; then
    echo `now` ">> ${VERBOSE_CMD}"
    if [[ "${MIGRATION_DRY_RUN}" -eq 0 ]]; then
      ${VERBOSE_CMD}
    else
      VERBOSE_RVAL=1
    fi
  else
    VERBOSE_RVAL=2
  fi
}

#export -fx verbose

function prettytime {
  printf ""%dh\ %dm\ %ds"\n" $(($1/3600)) $(($1%3600/60)) $(($1%60))
}

#export -fx prettytime
