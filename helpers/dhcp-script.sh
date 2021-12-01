#!/bin/bash
#############################################################################
# This helper script motifies the specified mosquitto server whenever a
# DHCP client is added or removed from the router.
#############################################################################
# Inspiration: https://jpmens.net/2013/10/21/tracking-dhcp-leases-with-dnsmasq/
#############################################################################
[[ -f /etc/default/dhcp-helper ]] && source /etc/default/dhcp-helper
[[ -z "${3}" ]] && exit 0
payload=(${1:-"op"} ${3:-"ip"} "(${hostname})")
[[ "${payload[2]}" == "()" ]] && unset payload[2]

[[ ! -z "${mosquitto_addr}" ]] && mosquitto_addr="-h ${mosquitto_addr}"
[[ ! -z "${mosquitto_user}" ]] && mosquitto_user="-u ${mosquitto_user}"
[[ ! -z "${mosquitto_pass}" ]] && mosquitto_pass="-P ${mosquitto_pass}"
[[ ! -z "${mosquitto_port}" ]] && mosquitto_port="-p ${mosquitto_port}"
mosquitto_pub ${mosquitto_ip} ${mosquitto_user} ${mosquitto_pass} ${mosquitto_port} "network/dhcp/${2:-"mac"}" -m "${payload[@]}"
exit 0
