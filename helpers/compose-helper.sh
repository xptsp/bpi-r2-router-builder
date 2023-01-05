#!/bin/bash
#############################################################################
# This helper script takes care of any tasks that should occur before the 
# docker service officially starts.  Tasks that occur here should not
# take very long to execute and should not rely on other services being up
# and running.
#############################################################################

# Change the default IP address that containers are bound to:
test -f /etc/default/docker-compose && source /etc/default/docker-compose
IFACE=${IFACE_$(echo ${1} | tr [:lower:] [:upper:])}
NEW_IP=$(cat /etc/network/interfaces.d/${IFACE:-"br0"} 2>&1 | grep "address" | head -1 | awk '{print $2}')
FILE=/etc/docker/compose.d/${1}.yaml
if [[ ! -z "${NEW_IP}" ]]; then
	OLD_IP=$(cat ${FILE} | grep 'com.docker.network.bridge.host_binding_ipv4' | awk '{print $2}')
	[[ "${NEW_IP}" != "${OLD_IP}" ]] && sed -i "s|com.docker.network.bridge.host_binding_ipv4: .*| com.docker.network.bridge.host_binding_ipv4: ${NEW_IP}|g" ${FILE}
fi

# Return error code 0 to caller:
exit 0
