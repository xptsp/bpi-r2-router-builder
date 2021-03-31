#!/bin/bash
FILE=/var/run/transmission-daemon.rule
if [[ "$1" == "start" ]]; then
	# Set the WebUI credentials and port for the transmission-daemon:
	source /etc/default/transmission-autoremove
	sed -i "s|\"rpc-username\": \".*\",|\"rpc-username\": \"$USER\",|g" /etc/transmission-daemon/settings.json
	sed -i "s|\"rpc-password\": \".*\",|\"rpc-password\": \"$PASS\",|g" /etc/transmission-daemon/settings.json
	sed -i "s|\"rpc-port\": \".*\",|\"rpc-port\": $PORT,|g" /etc/transmission-daemon/settings.json

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
