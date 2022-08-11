#!/bin/bash
TABLE=$(grep -m 1 "^table inet " /etc/nftables.conf | awk '{print $3}')

########################################################################################
# Do not change these variables.  They are dynamically set:
########################################################################################
export INTERFACE=$(cat /etc/openvpn/vpn.conf | grep "dev " | cut -d" " -f 2)
export GATEWAYIP=$(ip address show $INTERFACE | egrep -o '([0-9]{1,3}\.){3}[0-9]{1,3}' | egrep -v '255|(127\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3})' | tail -n1)

########################################################################################
# Add ${TABLE} rules to set up OpenVPN split tunnel:
########################################################################################
# Add OpenVPN interface to list of VPN devices:
nft add element inet ${TABLE} DEV_VPN { ${INTERFACE} }

# Reject connections not from inside network going over WAN interfaces:
nft add rule inet ${TABLE} output_wan ip saddr != @INSIDE_NETWORK reject comment \"VPN\"

########################################################################################
# Configure routes for the packets marked with the "OVPN" flag.
# NOTE: "0x4f56504e" is "OVPN" converted to hexadecimal! :p  ==> CMD: "printf OVPN | xxd -p" <==
########################################################################################
ip rule list | grep -c 0x4f56504e >& /dev/null || ip rule add from all fwmark 0x4f56504e lookup vpn
ip route replace default via $GATEWAYIP table vpn
ip route append default via 127.0.0.1 dev lo table vpn
ip route flush cache

########################################################################################
# Run update-systemd-resolved script to set VPN DNS
########################################################################################
/etc/openvpn/update-systemd-resolved
exit 0
