#!/bin/bash
#############################################################################
# This helper script takes care of any tasks that should occur before the 
# transmission service officially starts.  Tasks that occur here should not
# take very long to execute and should not rely on other services being up
# and running.
#############################################################################
JSON=/etc/transmission-daemon/settings.json
if [[ "$1" == "start" ]]; then
	# Set the WebUI credentials and port for the transmission-daemon:
	source /etc/default/transmission-autoremove
	sed -i "s|\"rpc-username\": \".*\",|\"rpc-username\": \"${USER}\",|g" ${JSON}
	sed -i "s|\"rpc-password\": \".*\",|\"rpc-password\": \"${PASS}\",|g" ${JSON}
	sed -i "s|\"rpc-port\": \".*\",|\"rpc-port\": ${PORT},|g" ${JSON}
fi
exit 0
