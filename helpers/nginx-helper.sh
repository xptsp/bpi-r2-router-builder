#!/bin/bash
#############################################################################
# This helper script takes care of any tasks that should occur before the 
# SSH service officially starts.  Tasks that occur here should not
# take very long to execute and should not rely on other services being up
# and running.
#############################################################################
if [[ "${UID}" -ne 0 ]]; then
	sudo $0 $@
	exit $?
fi

#############################################################################
# Update webserver addresses to match IP address of interface "br0":
#############################################################################
cd /etc/nginx/sites-available
NEW_IP=$(cat /etc/network/interfaces.d/br0 | grep address | awk '{print $2}')
if [[ ! -z "${NEW_IP}" ]]; then
	for FILE in $(ls | egrep -ve "(default|transmission|pihole)"); do
		OLD_IP=$(cat ${FILE} | grep listen | head -1 | awk '{print $2}' | cut -d: -f 1)
		[[ "${NEW_IP}" != "${OLD_IP}" ]] && sed -i "s|${OLD_IP}|${NEW_IP}|g" ${FILE}
	done
fi

#############################################################################
# Update transmission reverse proxy to match port address specified:
#############################################################################
if test -f /etc/default/transmission-daemon; then
	source /etc/default/transmission-daemon
	TRANS_IFACE=${TRANS_IFACE:-"br0"}
	[[ "${TRANS_IFACE}" != "br0" ]] && NEW_IP=$(cat /etc/network/interfaces.d/${TRANS_IFACE} | grep address | awk '{print $2}')
	if [[ ! -z "${NEW_IP}" ]]; then
		OLD_IP=$(cat transmission | grep listen | awk '{print $2}')
		[[ "${NEW_IP}:${TRANS_PORT:-"9091"};" != "${OLD_IP}" ]] && sed -i "s|listen ${NEW_IP}:.*;|listen ${NEW_IP}\:${TRANS_PORT:-"9091"};|g" transmission
	fi
fi

#############################################################################
# Change IP address that PiHole admin server is assigned to:  
#############################################################################
SECOND=$(ifconfig br0:1 | grep " inet " | awk '{print $2}')
[[ ! -z "${SECOND}" ]] && NEW_IP=${SECOND}
OLD_IP=$(cat pihole | grep listen | head -1 | awk '{print $2}' | cut -d: -f 1)
[[ "${NEW_IP}" != "${OLD_IP}" ]] && sed -i "s|${OLD_IP}|${NEW_IP}|g" pihole

#############################################################################
# We are done rewrite the configuration files.  If requesting a reload, then
# reload the service as originally requested:
#############################################################################
[[ "$1" == "reload" ]] && /usr/sbin/nginx -g 'daemon on; master_process on;' -s reload

#############################################################################
# Return error code 0 to caller:
exit 0
