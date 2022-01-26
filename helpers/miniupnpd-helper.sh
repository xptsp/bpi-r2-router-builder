#!/bin/bash

# Flush the rules in MINIUPNPD chains:
iptables -t nat -F MINIUPNPD
iptables -F MINIUPNPD
ip6tables -F MINIUPNPD

# If we are starting the service, we need to put the IP address of WAN interface in the config file:
if [[ "1" == "start" ]]; then
	FILE=/etc/miniupnpd/miniupnpd.conf
	IFACE=$(cat ${FILE} | grep "^ext_ifname=" | head -1 | cut -d= -f 2)
	sed -i "/^ext_ip=/d" ${FILE}
	echo "ext_ip=$(ifconfig ${IFACE} | grep "inet " | head -1 | awk '{print $2}')" >> ${FILE}
	route add -net 239.0.0.0 netmask 255.0.0.0 $(cat ${FILE} | grep "^listening_ip" | head -1 | cut -d= -f 2)
fi
exit 0
