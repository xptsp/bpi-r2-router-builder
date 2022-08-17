#!/bin/bash
TABLE=$(grep -m 1 "^table inet " /etc/nftables.conf | awk '{print $3}')

########################################################################################
# Remove the ip rule for table "vpn":
########################################################################################
ip rule list | grep -q -c 0x4f56504e || ip rule add from all fwmark 0x4f56504e lookup vpn

########################################################################################
# Remove the VPN iptable rules that we inserted earlier:
########################################################################################
nft delete rule inet ${TABLE} output_wan handle $(nft -a list chain inet ${TABLE} output_wan | grep "VPN" | awk '{print $NF}')

########################################################################################
# Remove interface from the list of VPN interfaces:
########################################################################################
nft delete element inet ${TABLE} DEV_VPN_OUT { ${dev} } 

########################################################################################
# Run update-resolv-conf script to set VPN DNS
########################################################################################
/etc/openvpn/update-resolv-conf
