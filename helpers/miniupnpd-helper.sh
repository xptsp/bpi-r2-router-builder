#!/bin/bash

# Flush the rules in MINIUPNPD chains:
iptables -t nat -N MINIUPNPD >& /dev/null
iptables -t nat -F MINIUPNPD
iptables -N MINIUPNPD >& /dev/null
iptables -F MINIUPNPD
ip6tables -N MINIUPNPD >& /dev/null
ip6tables -F MINIUPNPD
exit 0
