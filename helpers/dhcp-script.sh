#!/bin/bash
#############################################################################
# This helper script motifies the specified mosquitto server whenever a
# DHCP client is added or removed from the router.
#############################################################################
# Inspiration: https://jpmens.net/2013/10/21/tracking-dhcp-leases-with-dnsmasq/
#############################################################################
[[ -f /etc/default/router-settings ]] && source /etc/default/router-settings
[[ "${enable_mosquitto}" != "Y" ]] && exit 0
[[ ! -z "${mosquitto_addr}" ]] && mosquitto_addr="-h ${mosquitto_addr}"
[[ ! -z "${mosquitto_user}" ]] && mosquitto_user="-u ${mosquitto_user}"
[[ ! -z "${mosquitto_pass}" ]] && mosquitto_pass="-P ${mosquitto_pass}"
[[ ! -z "${mosquitto_port}" ]] && mosquitto_port="-p ${mosquitto_port}"
mosquitto_pub ${mosquitto_ip} ${mosquitto_user} ${mosquitto_pass} ${mosquitto_port} -t "network/dhcp/${2:-"mac"}" -m "${1:-"op"} ${3:-"ip"} \(${hostname}\)"
exit 0
