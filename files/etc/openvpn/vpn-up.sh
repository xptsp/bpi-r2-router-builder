#!/bin/bash

########################################################################################
# Add OpenVPN interface to list of VPN client devices:
########################################################################################
nft add element inet $(grep -m 1 "^table inet " /etc/nftables.conf | awk '{print $3}') DEV_VPN_CLIENT { ${dev} }

########################################################################################
# Configure network routing table "vpn":
########################################################################################
# Flush the existing routing table for table "vpn":
ip route flush table vpn

# Add an IP rule to use table "vpn" if fwmark "0x4f56504e" is set (if not already present):
# NOTE: "0x4f56504e" is "OVPN" converted to hexadecimal! :p  ==> CMD: "printf OVPN | xxd -p" <==
ip rule list | grep -q -c 0x4f56504e || ip rule add from all fwmark 0x4f56504e lookup vpn

# Set default routes to VPN gateway:
ip route replace default via ${route_vpn_gateway} dev ${dev} table vpn

# Set other routes from main network routing table for all interfaces except wan:
ip route show | grep -v "^default" | grep -v wan | while read route; do ip route add ${route} table vpn; done

# Flush IP routing cache:
ip route flush cache

########################################################################################
# Run update-resolv-resolved script to set VPN DNS
########################################################################################
/etc/openvpn/update-resolv-conf
