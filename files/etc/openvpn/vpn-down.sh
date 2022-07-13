#!/bin/bash

# Remove the ip rule for table "vpn":
ip rule list | grep -c 0x4f56504e >& /dev/null || ip rule add from all fwmark 0x4f56504e lookup vpn

# Remove the VPN iptable rules that we inserted earlier:
nft delete rule inet firewall output_wan handle $(nft -a list chain inet firewall output_wan | grep "VPN" | awk '{print $NF}')

# Remove interface from the list of VPN interfaces:
nft delete element inet firewall DEV_VPN { ${INTERFACE} } 

# Run update-resolv-conf script to set VPN DNS
/etc/openvpn/update-resolv-conf

exit 0
