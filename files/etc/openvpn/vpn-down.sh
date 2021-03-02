#!/bin/bash

# Remove the VPN iptable rules that we inserted earlier:
if [[ -e /var/run/vpn_iptables.rules ]]; then
	sed -i "s|^\-A |-D |g" /var/run/vpn_iptables.rules
	iptables-restore --noflush /var/run/vpn_iptables.rules
fi

# Run update-resolv-conf script to set VPN DNS
/etc/openvpn/update-resolv-conf

exit 0
