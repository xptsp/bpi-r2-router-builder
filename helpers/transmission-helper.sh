#!/bin/bash
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

# Modify the configuration file to match the expected configuration values from WebUI:
JSON=/etc/transmission-daemon/settings.json
if [[ "$1" == "start" ]]; then
	# Set the WebUI credentials and port for the transmission-daemon:
	source /etc/default/transmission-daemon
	sed -i "s|\"rpc-username\": \".*\",|\"rpc-username\": \"${TRANS_USER:-"pi"}\",|g" ${JSON}
	sed -i "s|\"rpc-password\": \".*\",|\"rpc-password\": \"${TRANS_PASS:-"bananapi"}\",|g" ${JSON}
fi

# Forward all traffic on the peer port to the transmission daemon:
PEER=$(cat $JSON | egrep -o '"peer-port": [0-9]*' | cut -d: -f 2)
BR0=$(cat /etc/network/interfaces.d/br0 | grep 'address' | awk '{print $2}')
if [[ "$1" == "start" ]]; then
	ip route add 127.0.0.0/8 via ${BR0}
	iptables -I PREROUTING -t nat -p tcp --dport ${PEER:-"51543"} -j DNAT --to 127.0.0.1:${PEER:-"51543"}
	iptables -I FORWARD -p tcp -d 127.0.0.1 --dport ${PEER:-"51543"} -j ACCEPT
elif [[ "$1" == "stop" ]]; then
	ip route del 127.0.0.0/8 via ${BR0}
	iptables -D PREROUTING -t nat -p tcp --dport ${PEER:-"51543"} -j DNAT --to 127.0.0.1:${PEER:-"51543"}
	iptables -D FORWARD -p tcp -d 127.0.0.1 --dport ${PEER:-"51543"} -j ACCEPT
fi

# Change the transmission-daemon WebUI to choice in Router WebUI:
DIR=/usr/share/transmission
WEB=${DIR}/web
if ! test -L ${WEB}; then
	test -d ${DIR}/original && rm -rf ${DIR}/original
	mv ${WEB} ${DIR}/original
fi
TRANS_WEBUI=${TRANS_WEBUI:-"transmission-web-control"}
! test -d ${DIR}/${TRANS_WEBUI} && TRANS_WEBUI=original
! test -d ${DIR}/${TRANS_WEBUI} && exit 1
CUR=$(ls -l ${WEB} | awk '{print $NF}')
if [[ "${CUR}" != "${DIR}/${TRANS_WEBUI}" ]]; then
	unlink ${WEB}
	ln -sf ${DIR}/${TRANS_WEBUI} ${WEB}
fi

# Return error code 0 to caller:
exit 0