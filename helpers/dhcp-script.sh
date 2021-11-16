#!/bin/bash
#############################################################################
# This helper script motifies the specified mosquitto server whenever a
# DHCP client is added or removed from the router.
#############################################################################
# Inspiration: https://jpmens.net/2013/10/21/tracking-dhcp-leases-with-dnsmasq/
#############################################################################
[[ -f /etc/default/dhcp-helper ]] && source /etc/default/dhcp-helper
op="${1:-"op"}"
mac="${2:-"mac"}"
ip="${3:-"ip"}"
[[ ! -z "${4}" ]] && hostname=" (${4})"
topic="network/dhcp/${mac}"
payload="${op} ${ip}${hostname}"

[[ ! -z "${mosquitto_addr}" ]] && mosquitto_addr="-h ${mosquitto_addr}"
[[ ! -z "${mosquitto_user}" ]] && mosquitto_user="-u ${mosquitto_user}"
[[ ! -z "${mosquitto_pass}" ]] && mosquitto_pass="-P ${mosquitto_pass}"
[[ ! -z "${mosquitto_port}" ]] && mosquitto_port="-p ${mosquitto_port}"
mosquitto_pub ${mosquitto_ip} ${mosquitto_user} ${mosquitto_pass} ${mosquitto_port} "${topic}" -m "${payload}"
exit 0
