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
for FILE in $(ls | grep -v default); do
	OLD_IP=$(cat ${FILE} | grep listen | head -1 | awk '{print $2}' | cut -d: -f 1)
	[[ "${NEW_IP}" != "${OLD_IP}" ]] && sed -i "s|${OLD_IP}|${NEW_IP}|g" ${FILE}
done

#############################################################################
# Update transmission reverse proxy to match port address specified:
#############################################################################
if test -f /etc/default/transmission-daemon; then
	source /etc/default/transmission-daemon
	OLD_IP=$(cat transmission | grep listen | awk '{print $2}')
	[[ "${NEW_IP}:${TRANS_PORT};" != "${OLD_IP}" ]] && sed -i "s|listen ${NEW_IP}:.*;|listen ${NEW_IP}\:${TRANS_PORT};|g" transmission
fi

#############################################################################
# We are done rewrite the configuration files.  If requesting a reload, then
# reload the service as originally requested:
#############################################################################
[[ "$1" == "reload" ]] && /usr/sbin/nginx -g 'daemon on; master_process on;' -s reload

#############################################################################
# Return error code 0 to caller:
exit 0

