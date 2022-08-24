#!/bin/bash

########################################################################################
# Remove all of the IP routings for table "vpn":
########################################################################################
ip route flush table vpn

########################################################################################
# Remove interface from the list of VPN client interfaces:
########################################################################################
TABLE=$(grep -m 1 "^table inet " /etc/nftables.conf | awk '{print $3}')
nft delete element inet ${TABLE} DEV_VPN_CLIENT { ${dev} } 

########################################################################################
# Run update-resolv-conf script to set VPN DNS
########################################################################################
/etc/openvpn/update-resolv-conf
