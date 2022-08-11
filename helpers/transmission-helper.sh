#!/bin/bash
TABLE=$(grep -m 1 "^table inet " /etc/nftables.conf | awk '{print $3}')

#############################################################################
# This helper script takes care of any tasks that should occur before the 
# transmission service officially starts.  Tasks that occur here should not
# take very long to execute and should not rely on other services being up
# and running.
#############################################################################
if [[ "${UID}" -ne 0 ]]; then
	sudo $0 $@
	exit $?
fi

# Forward all traffic on the peer port to the transmission daemon:
[[ "$1" == "start" ]] && ACTION=add || ACTION=delete
PEER=$(cat /etc/transmission-daemon/settings.json | egrep -o '"peer-port": [0-9]*' | awk '{print $2}')
nft ${ACTION} element inet ${TABLE} ACCEPT_PORT_TCP { ${PEER:-"51543"} } 

# Add routing to the network routing table so "lo" goes through "br0"... (?)
ip route ${ACTION/delete/del} 127.0.0.0/8 via $(cat /etc/network/interfaces.d/br0 | grep 'address' | awk '{print $2}')

# Change the transmission-daemon WebUI to choice in Router WebUI:
DIR=/usr/share/transmission
WEB=${DIR}/web
if ! test -L ${WEB}; then
	test -d ${DIR}/original && rm -rf ${DIR}/original
	mv ${WEB} ${DIR}/original
fi
TRANS_WEBUI=${TRANS_WEBUI:-"combustion-release"}
! test -d ${DIR}/${TRANS_WEBUI} && TRANS_WEBUI=original
! test -d ${DIR}/${TRANS_WEBUI} && exit 1
CUR=$(ls -l ${WEB} | awk '{print $NF}')
if [[ "${CUR}" != "${DIR}/${TRANS_WEBUI}" ]]; then
	unlink ${WEB}
	ln -sf ${DIR}/${TRANS_WEBUI} ${WEB}
fi

# Return error code 0 to caller:
exit 0
