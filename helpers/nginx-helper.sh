#!/bin/bash
#############################################################################
# This helper script takes care of any tasks that should occur before the 
# nginx service officially starts.  Tasks that occur here should not
# take very long to execute and should not rely on other services being up
# and running.
#############################################################################

#############################################################################
# Update webserver addresses to match IP address of interface "br0":
#############################################################################
cd /etc/nginx/sites-available
NEW_IP=$(cat /etc/network/interfaces.d/br0 | grep address | awk '{print $2}')
if [[ ! -z "${NEW_IP}" ]]; then
	for FILE in $(ls | egrep -ve "(default|pihole)"); do
		OLD_IP=$(cat ${FILE} | grep listen | head -1 | awk '{print $2}' | cut -d: -f 1)
		[[ "${NEW_IP}" != "${OLD_IP}" ]] && sed -i "s|${OLD_IP}|${NEW_IP}|g" ${FILE}
	done
fi

#############################################################################
# Change IP address that PiHole admin server is assigned to:  
#############################################################################
SECOND=$(ifconfig br0:1 | grep -m 1 "inet " | awk '{print $2}')
[[ ! -z "${SECOND}" ]] && NEW_IP=${SECOND}
OLD_IP=$(cat pihole | grep -m 1 "listen" | awk '{print $2}' | cut -d: -f 1)
[[ "${NEW_IP}" != "${OLD_IP}" ]] && sed -i "s|${OLD_IP}|${NEW_IP}|g" pihole

#############################################################################
# We are done rewrite the configuration files.  If requesting a reload, then
# reload the service as originally requested:
#############################################################################
[[ "$1" == "reload" ]] && /usr/sbin/nginx -g 'daemon on; master_process on;' -s reload

#############################################################################
# Return error code 0 to caller:
exit 0
