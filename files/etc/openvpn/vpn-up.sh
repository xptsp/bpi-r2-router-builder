#!/bin/bash
########################################################################################
# Do not change these variables.  They are dynamically set:
########################################################################################
export VPNUSER=vpn
export NETIF=wan
export LOCALIP=$(ip address show $NETIF | egrep -o '([0-9]{1,3}\.){3}[0-9]{1,3}' | egrep -v '255|(127\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3})' | tail -n1)/16
export INTERFACE=$(cat /etc/openvpn/vpn.conf | grep "dev " | cut -d" " -f 2)
export GATEWAYIP=$(ip address show $INTERFACE | egrep -o '([0-9]{1,3}\.){3}[0-9]{1,3}' | egrep -v '255|(127\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3})' | tail -n1)

########################################################################################
# Remove the iptables rule denying access to interface from user vpn:
########################################################################################
iptables -D OUTPUT ! -o lo -m owner --uid-owner $VPNUSER -j DROP

########################################################################################
# Create our VPN iptables rules file so we can reverse it when closing the vpn:
########################################################################################
cat << EOF > /var/run/vpn_iptables.rules
*nat
# All packets on $INTERFACE needs to be masqueraded
-A POSTROUTING -o $INTERFACE -j MASQUERADE

COMMIT

*filter
# Allow responses
-A INPUT -i $INTERFACE -m conntrack --ctstate ESTABLISHED -j ACCEPT

# Block everything incoming on $INTERFACE to prevent accidental exposing of ports
-A INPUT -i $INTERFACE -j REJECT

# Let $VPNUSER access lo and $INTERFACE
-A OUTPUT -o lo -m owner --uid-owner $VPNUSER -j ACCEPT
-A OUTPUT -o $INTERFACE -m owner --uid-owner $VPNUSER -j ACCEPT

# Reject connections from predator IP going over $NETIF
-A OUTPUT ! --src $LOCALIP -o $NETIF -j REJECT

COMMIT

*mangle
# Mark packets from $VPNUSER
-A OUTPUT -j CONNMARK --restore-mark
-A OUTPUT ! --dest $LOCALIP -m owner --uid-owner $VPNUSER -j MARK --set-mark 0x1
-A OUTPUT --dest $LOCALIP -p udp --dport 53 -m owner --uid-owner $VPNUSER -j MARK --set-mark 0x1
-A OUTPUT --dest $LOCALIP -p tcp --dport 53 -m owner --uid-owner $VPNUSER -j MARK --set-mark 0x1
-A OUTPUT ! --src $LOCALIP -j MARK --set-mark 0x1
-A OUTPUT -j CONNMARK --save-mark

COMMIT
EOF

########################################################################################
# Actually add the iptables rules to the system:
########################################################################################
iptables-restore --no-flush /var/run/vpn_iptables.rules

########################################################################################
# Configure routes for the packets we just marked:
########################################################################################
if [[ `ip rule list | grep -c 0x1` == 0 ]]; then
 	ip rule add from all fwmark 0x1 lookup $VPNUSER
fi
ip route replace default via $GATEWAYIP table $VPNUSER
ip route append default via 127.0.0.1 dev lo table $VPNUSER
ip route flush cache

########################################################################################
# Run update-systemd-resolved script to set VPN DNS
########################################################################################
/etc/openvpn/update-systemd-resolved
exit 0
