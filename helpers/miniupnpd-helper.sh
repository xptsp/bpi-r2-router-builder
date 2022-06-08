#!/bin/bash

# If we are starting the service, add a route for 239.0.0.0 for each interface that miniupnpd listens on:
if [[ "$1" == "start" ]]; then
	for iface in $(cat /etc/miniupnpd/miniupnpd.conf | grep "^listening_ip" | cut -d= -f 2); do
		route add -net 224.0.0.0 netmask 240.0.0.0 $iface
	done
fi
exit 0
