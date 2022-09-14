#!/bin/bash

########################################################################################
# Add OpenVPN interface to list of VPN client devices:
########################################################################################
nft add element inet $(grep -m 1 "^table inet " /etc/nftables.conf | awk '{print $3}') DEV_VPN_CLIENT { ${dev} }

########################################################################################
# Configure network routing table "vpn":
########################################################################################
# Flush the existing routing table for table "vpn":
ip route flush table vpn >& /dev/null

# Add an IP rule to use table "vpn" if fwmark "0x4f56504e" is set (if not already present):
# NOTE: "0x4f56504e" is "OVPN" converted to hexadecimal! :p  ==> CMD: "printf OVPN | xxd -p" <==
ip rule list | grep -q -c 0x4f56504e || ip rule add from all fwmark 0x4f56504e lookup vpn

# Replace default gateway to VPN in IP route table vpn: 
ip route replace default via ${route_vpn_gateway} dev ${dev} table vpn

# Set route from "lo" to default gateway in IP route table vpn:
ip route append default via 127.0.0.1 dev lo table vpn

# Flush IP routing cache:
ip route flush cache

########################################################################################
# Restart transmission-daemon if running:
########################################################################################
systemctl -q is-active transmission-daemon && systemctl restart transmission-daemon

########################################################################################
# Run update-resolv-resolved script to set VPN DNS
########################################################################################
/etc/openvpn/update-resolv-conf
