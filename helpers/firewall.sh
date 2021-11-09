#!/bin/bash
#############################################################################
# This helper script establishes all of the iptables rules required by the
# WebUI configuration for our router to operate properly.
#############################################################################
# Comments starting with "CTA:" and iptables commands from source:
#	https://javapipe.com/blog/iptables-ddos-protection/
# Comments starting with "CTB:" and iptables commands from source:
#	https://offensivesecuritygeek.wordpress.com/2014/06/24/how-to-block-port-scans-using-iptables-only/
#############################################################################
if [[ "${UID}" -ne 0 ]]; then
	sudo $0 $@
	exit $?
fi

# Read the INTERNET from the configuration file:
[[ -f /etc/default/firewall ]] && source /etc/default/firewall

#############################################################################
# START => Initializes the optionless base iptable firewall configuration:
#############################################################################
if [[ "$1" == "start" ]]; then
	#############################################################################
	# CTB: Flush all the iptables Rules
	#############################################################################
	iptables -F

	#############################################################################
	# CTB: Accept loopback input
	#############################################################################
	iptables -A INPUT -i lo -p all -j ACCEPT

	#############################################################################
	# CTA: Set default policy to DROP for input, output and forwarding:
	#############################################################################
	iptables -P INPUT ACCEPT
	iptables -P FORWARD ACCEPT
	iptables -P OUTPUT ACCEPT

	#############################################################################
	# These are global rules that will always be set!
	#############################################################################
	# CTA: Allow masquerading to the wan port:
	iptables -t nat -A POSTROUTING -o wan -j MASQUERADE

7	# CTA: This rule blocks all packets that are not a SYN packet and don’t
	# belong to an established TCP connection.
	iptables -t mangle -A PREROUTING -m conntrack --ctstate INVALID -j DROP

	# CTA: This blocks all packets that are new (don’t belong to an established
	# connection) and don’t use the SYN flag.  This rule is similar to the “Block
	# Invalid Packets” one, but we found that it catches some packets that the other one doesn’t.
	iptables -t mangle -A PREROUTING -p tcp ! --syn -m conntrack --ctstate NEW -j DROP

	# CTA: The above iptables rule blocks new packets (only SYN packets can be
	# new packets as per the two previous rules) that use a TCP MSS value that
	# is not common. This helps to block dumb SYN floods.
	iptables -t mangle -A PREROUTING -p tcp -m conntrack --ctstate NEW -m tcpmss ! --mss 536:65535 -j DROP

	# CTA: The above ruleset blocks packets that use bogus TCP flags, ie. TCP flags that legitimate packets wouldn’t use.
	iptables -t mangle -A PREROUTING -p tcp --tcp-flags FIN,SYN FIN,SYN -j DROP
	iptables -t mangle -A PREROUTING -p tcp --tcp-flags SYN,RST SYN,RST -j DROP
	iptables -t mangle -A PREROUTING -p tcp --tcp-flags FIN,RST FIN,RST -j DROP
	iptables -t mangle -A PREROUTING -p tcp --tcp-flags FIN,ACK FIN -j DROP
	iptables -t mangle -A PREROUTING -p tcp --tcp-flags ACK,URG URG -j DROP
	iptables -t mangle -A PREROUTING -p tcp --tcp-flags ACK,PSH PSH -j DROP
	iptables -t mangle -A PREROUTING -p tcp --tcp-flags ALL NONE -j DROP

	# Redirect incoming port 67 to port 68:
	iptables -A INPUT -p udp -m udp --sport 67 --dport 68 -j ACCEPT

	# CTB: Droping all invalid packets
	iptables -A INPUT -m state --state INVALID -j DROP
	iptables -A FORWARD -m state --state INVALID -j DROP
	iptables -A OUTPUT -m state --state INVALID -j DROP

	# CTB: for SMURF attack protection
	iptables -A INPUT -p icmp -m icmp --icmp-type address-mask-request -j DROP
	iptables -A INPUT -p icmp -m icmp --icmp-type timestamp-request -j DROP
	iptables -A INPUT -p icmp -m icmp -m limit --limit 1/second -j ACCEPT

	# CTB: flooding of RST packets, smurf attack Rejection
	iptables -A INPUT -p tcp -m tcp --tcp-flags RST RST -m limit --limit 2/second --limit-burst 2 -j ACCEPT

	#############################################################################
	# Direct WAN interface to check SERVICES table for further rules:
	#############################################################################
	iptables -N SERVICES
	iptables -A INPUT -i wan -j SERVICES

	#############################################################################
	# Our "intervention" for miniupnpd to work properly:
	#############################################################################
	iptables -N MINIUPNPD
	iptables -A FORWARD -i wan ! -o wan -j MINIUPNPD

	#############################################################################
	# Configurable INTERNET chain:
	#############################################################################
	iptables -N INTERNET
	iptables -A INPUT -i wan -j INTERNET

	#############################################################################
	# Default network configuration:
	#############################################################################
	iptables -A INPUT -m conntrack --ctstate RELATED,ESTABLISHED -j ACCEPT
	iptables -A INPUT -i wan -j DROP
	iptables -A FORWARD -i wan ! -o wan -m conntrack --ctstate RELATED,ESTABLISHED -j ACCEPT
	iptables -A FORWARD -i wan ! -o wan -j DROP
fi

#############################################################################
# START/RELOAD => Setup any iptables rules for our WebUI configuration:
#############################################################################
if [[ "$1" == "start" || "$1" == "reload" ]]; then
	#############################################################################
	# Clear the "INTERNET" iptables chain of rules:
	#############################################################################
	iptables -F INTERNET

	#############################################################################
	# OPTION "disable_port_scan" => Disable ping response from internet
	#############################################################################
	[[ "${disable_port_scan:-"Y"}" == "Y" ]] && iptables -A INTERNET -p icmp --icmp-type echo-request -j DROP

	#############################################################################
	# OPTION "disable_port_scan" => Disable ping response from internet
	#############################################################################
	if [[ "${disable_port_scan:-"Y"}" == "Y" ]]; then
		# CTB: Create a chain port scan and add logging to it with your preferred prefix
		iptables -N PORTSCAN
		iptables -A PORTSCAN -j LOG --log-level 4 --log-prefix 'Blocked_scans '
		iptables -A PORTSCAN -j DROP

		# CTB: Create another chain UDP with custom logging
		iptables -N UDP
		iptables -A UDP -j LOG --log-level 4 --log-prefix 'UDP_FLOOD '
		iptables -A UDP -p udp -m state --state NEW -m recent --set --name UDP_FLOOD
		iptables -A UDP -j DROP

		# CTB: Continue processing for all connections for ports from 32768 to 61000.  These are mostly
		# used in ACK and don’t have too many services hosted here.
		iptables -A INTERNET -p tcp -m tcp --destination-port 32768:61000 -j RETURN

		# CTB: Anyone who previously tried to portscan or UDP flood us are locked out for an entire day.
		# Their IP’s are stored in a list called ‘PORTSCAN’:
		iptables -A INTERNET -m recent --name PORTSCAN --rcheck --seconds 86400 -j PORTSCAN
		iptables -A INTERNET -m recent --name UDP_FLOOD --rcheck --seconds 86400 -j PORTSCAN

		# CTB: Once the day has passed, remove them from the PORTSCAN list:
		iptables -A INTERNET -m recent --name PORTSCAN --remove
		iptables -A INTERNET -m recent --name UDP_FLOOD --remove

		# CTB: Anyone who does not match the above rules (open ports) is trying to access a port our sever does not
		# serve. So, as per design we consider them port scanners and we block them for an entire day
		# These rules add scanners to the PORTSCAN list, and log the attempt:
		iptables -A INTERNET -p tcp -m tcp -m recent -m state --state NEW --name PORTSCAN --set -j PORTSCAN

		# CTB: Same for UDP
		iptables -A INTERNET -p udp -m state --state NEW -m recent --set --name Domainscans
		iptables -A INTERNET -p udp -m state --state NEW -m recent --rcheck --seconds 5 --hitcount 5 --name Domainscans -j UDP
	fi

	#############################################################################
	# OPTION "disable_ident" => Block port 113 (IDENT) from internet
	#############################################################################
	[[ "${disable_ident:-"Y"}" == "Y" ]] && iptables -A INTERNET -p tcp --destination-port 113 -j DROP
fi