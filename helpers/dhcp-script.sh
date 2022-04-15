#!/bin/bash
#############################################################################
# This helper script motifies the specified mosquitto server whenever a
# DHCP client is added or removed from the router.
#############################################################################
# Inspiration: https://jpmens.net/2013/10/21/tracking-dhcp-leases-with-dnsmasq/
#############################################################################
[[ -f /etc/default/router-settings ]] && source /etc/default/router-settings

# Is the DHCP notification script enabled?  If not, exit script:
[[ "${enable_mosquitto}" != "Y" ]] && exit 0

# Determine the mosquitto server settings:
[[ ! -z "${mosquitto_addr}" ]] && mosquitto_addr="-h ${mosquitto_addr}"
[[ ! -z "${mosquitto_user}" ]] && mosquitto_user="-u ${mosquitto_user}"
[[ ! -z "${mosquitto_pass}" ]] && mosquitto_pass="-P ${mosquitto_pass}"
[[ ! -z "${mosquitto_port}" ]] && mosquitto_port="-p ${mosquitto_port}"

# Does the IP address passed fall under one of the interfaces specified?  If not, exit script:
IP=${3:-"ip"}
FOUND=false
cd /etc/network/interfaces.d
for file in ${mosquitto_ifaces}; do
	[[ "${IP}" =~ $(cat $file | grep "address" | awk '{print $2}' | awk -F '.' '{ print $1"."$2"."$3".";}') ]] && FOUND=true	
done
[[ "${FOUND}" == "false" ]] && exit 0

# Send the mosquitto notification:
mosquitto_pub ${mosquitto_ip} ${mosquitto_user} ${mosquitto_pass} ${mosquitto_port} -t "network/dhcp/${2:-"mac"}" -m "${1:-"op"} ${IP} \(${hostname}\)"
exit 0
