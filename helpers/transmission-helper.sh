#!/bin/bash
if [[ "${UID}" -ne 0 ]]; then
	sudo $0 $@
	exit $?
fi
FILE=/var/run/transmission-daemon.rule
JSON=/etc/transmission-daemon/settings.json
if [[ "$1" == "start" ]]; then
	# Set the WebUI credentials and port for the transmission-daemon:
	source /etc/default/transmission-autoremove
	sed -i "s|\"rpc-username\": \".*\",|\"rpc-username\": \"${USER}\",|g" ${JSON}
	sed -i "s|\"rpc-password\": \".*\",|\"rpc-password\": \"${PASS}\",|g" ${JSON}
	sed -i "s|\"rpc-port\": \".*\",|\"rpc-port\": ${PORT},|g" ${JSON}

	# Allow WebUI port to always be open for transmission-daemon:
	RULE="iptables -D OUTPUT ! -o lo -p tcp --sport $PORT -m owner --uid-owner vpn -j ACCEPT"
	${RULE/\-D/\-I}
	echo $RULE > $FILE
	chmod +x $FILE
elif [[ "$1" == "stop" ]]; then
	$FILE
	rm $FILE
fi
exit 0
