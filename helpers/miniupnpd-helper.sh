#!/bin/bash

# Flush the rules in MINIUPNPD chains:
iptables -t nat -N MINIUPNPD >& /dev/null
iptables -t nat -F MINIUPNPD
iptables -N MINIUPNPD >& /dev/null
iptables -F MINIUPNPD
ip6tables -N MINIUPNPD >& /dev/null
ip6tables -F MINIUPNPD

# If we are starting the service, add a route for 239.0.0.0 to the routing table:
[[ "$1" == "start" ]] && route add -net 239.0.0.0 netmask 255.0.0.0 $(cat /etc/miniupnpd/miniupnpd.conf | grep "^listening_ip" | head -1 | cut -d= -f 2)

exit 0
