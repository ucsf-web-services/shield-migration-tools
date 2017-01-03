#!/bin/bash
CLOUDAPI="https://cloudapi.acquia.com/v1/sites/${CLOUD_SUB}"
hash curl 2>/dev/null && HAS_CURL=true || HAS_CURL=false
INSIGHT="https://insight.acquia.com/node/uuid/${CLOUD_UUID}"
test -f ${SSH_KEY} && KEY_EXISTS=true || KEY_EXISTS=false
test -d ${SRC_EXPORT_DIR} || mkdir ${SRC_EXPORT_DIR}

function acquia_config() {
  cat <<EOF
Host ${1}
HostName ${1}
User ${2}
ServerAliveInterval 60
ForwardAgent yes
StrictHostKeyChecking no
IdentityFile ${HOME}/.ssh/acquia.key

EOF
}

export -f acquia_config

function gen_keypair() {
  if [[ ! -f $SSH_KEY || $HAS_CURL ]]; then
    rm -f $SSH_KEY*
    ssh-keygen -q -b 4096 -C "Acquia Migration" -t rsa -N "" -f $SSH_KEY
  fi
}

function gen_ssh_config() {
  if [[ ! -f ${SSH_CONFIG} ]]; then
    for i in ${DST_VCS_REMOTE} ${ACQUIA_HOSTNAME}; do
      echo $i \
        | cut -d\@ -f2 \
        | cut -d\: -f1 \
        | while read line; do acquia_config ${line} ${ACQUIA_SITEGROUP} \
        >> ${SSH_CONFIG} ; done
    done
  fi
}

function add_pubkey() {
  local PUBKEY=`cat ${SSH_KEY}.pub`
  if [[ $HAS_CURL ]]; then
    RESPONSE=`curl -s -u ${CLOUD_CREDS} \
      -X POST --data-binary "{\"ssh_pub_key\":\"${PUBKEY}\"}" \
      "${CLOUDAPI}"/sshkeys.json?nickname=acquia_migration_script`

    TASKID=`echo $RESPONSE \
      | grep -o '\"id.*' \
      | grep -o "[0-9]*" \
      | grep -m 1 "[0-9]*"`

    STATUS='null'
    until [[ $STATUS =~ ^error|done$ ]]; do
      STATUS=`curl -s -u $CLOUD_CREDS \
      "${CLOUDAPI}"/tasks/"${TASKID}".json \
      | grep -o 'state.*' \
      | grep -o '[a-z]*' \
      | sed -n 2p`
      echo -ne "ADDING SSH KEY: ${STATUS}\033[0K\r"
      sleep 5
    done

    SSHID=`echo $RESPONSE \
      | grep -o "sshkeyid.*" \
      | grep -o "[0-9]*" \
      | grep -m 1 "[0-9]*"`

    echo ${SSHID} >> ${SRC_EXPORT_DIR}/sshid.txt

  else
    if [[ ! ${KEY_EXISTS} ]]; then
      echo "Copy the below public key to the Acquia Network Dashboard:"
      echo ${PUBKEY}
      echo ''
      echo ${INSIGHT}/cloud/users
      echo ''
      read -p "Press [Enter] key to continue..."
    fi
  fi
}

function rm_pubkey() {
  if [[ $HAS_CURL ]]; then
    cat ${SRC_EXPORT_DIR}/sshid.txt \
      | xargs -I {} curl -s -u $CLOUD_CREDS -X DELETE \
        "${CLOUDAPI}"/sshkeys/{}.json \
        -o /dev/null
    rm -f ${SRC_EXPORT_DIR}/sshid.txt
  fi
}

function rm_keypair() {
  if [[ $HAS_CURL ]]; then
    rm -f ${SSH_KEY}*
  fi
}

function ssh_build() {
  echo `now` ">> Establishing secure connection to Acquia Cloud"
  gen_keypair
  gen_ssh_config
  add_pubkey
}

function ssh_destroy() {
  rm_pubkey
  rm_keypair
}
