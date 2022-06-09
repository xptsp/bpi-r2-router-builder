#!/bin/bash
#############################################################################
# This helper script makes copies the rules in "/etc/nftables.conf" to 
# "/tmp/", then modifies them to cover the expected network configuration 
# found under the "/etc/network/interfaces.d/" directory.  After modifying
# the rules, the script loads the new nftables rules.  
#############################################################################
# How to do certain things with "nft" command:
# - List a chain in the table:     nft list chain inet firewall <chain_name>
# - List the elements in a map:    nft list map inet firewall <map_name>
# - Get handle for rule in chain:  nft -a list chain inet firewall <chain_name> | grep "<search_spec>" | awk '{print $NF}'
# - Delete rule from a chain:      nft delete rule inet firewall <chain_name} handle <handle>
# - Add an element to the map:     nft add element inet firewall port_forward { 80 : 192.168.1.1 . 80 }
# - Remove an element to the map:  nft delete element inet firewall port_forward { 80 : 192.168.1.1 . 80 }
#############################################################################
test -f /etc/default/router-settings && source /etc/default/router-settings
 
#############################################################################
# Copy the nftables ruleset we're using to the "/tmp" folder, then change
# to the "/etc/network/interfaces.d/" directory.
#############################################################################
RULES=/etc/nftables.conf
cd /etc/network/interfaces.d/

#############################################################################
# Get a list of all interfaces that have the "masquerade" line in it.  These
# are the WAN interfaces that the rules will block incoming new connections on.
#############################################################################
IFACES=($(grep "masquerade" * | cut -d: -f 1))
STR="$(echo ${IFACES[@]} | sed "s| |, |g")"
sed -i "s|^define DEV_WAN = .*|define DEV_WAN = \{ ${STR:-"no_net"} \}|" ${RULES}

#############################################################################
# Get a list of all interfaces that DO NOT have the "masquerade" line in it AND
# have an address assigned.  These are the LAN interfaces that the rules will 
# allow communications to flow between, and to the WAN interfaces.  The default
# rules will automatically deny new incoming connections from the WAN interfaces
# to the LAN interfaces.
#############################################################################
IFACES=($(grep address $(grep -L "masquerade" *) | cut -d: -f 1))
STR="$(echo ${IFACES[@]} | sed "s| |, |g")"
sed -i "s|^define DEV_LAN = .*|define DEV_LAN = \{ ${STR:-"no_net"} \}|" ${RULES}

#############################################################################
# Get a list of all interfaces that have the "no_internet" line in it.  These
# are the WAN interfaces that the rules will block incoming new connections on.
# Interfaces found can also be a part of the list of LAN interfaces, but never
# the WAN interfaces.
#############################################################################
IFACES=($(grep no_internet $(grep -L "masquerade" *) | cut -d: -f 1))
STR="$(echo ${IFACES[@]} | sed "s| |, |g")"
sed -i "s|^define DEV_NO_NET = .*|define DEV_NO_NET = \{ ${STR:-"no_net"} \}|" ${RULES}

#############################################################################
# Replace the Pi-Hole IP address with the one from the "br0" interface:
#############################################################################
sed -i "s|^define PIHOLE_IPv4 = .*|define PIHOLE_IPv4 = \"$(cat br0 | grep "address" | head -1 | awk '{print $2}')\"|" ${RULES}

#############################################################################
# Modify line with "redirect DNS to PiHole" rule (option "redirect_dns"):
#############################################################################
if [[ "${redirect_dns:-"N"}" == "N" ]]; then
	sed -i '/ dport 53 dnat/s/^#\?/#/' ${RULES}				# Comment out
else
	sed -i '/ dport 53 dnat/s/^#//' ${RULES}				# Uncomment
fi	 

#############################################################################
# Modify lines with "icmp" rule (option "allow_ping"):
#############################################################################
if [[ "${allow_ping:-"N"}" == "N" ]]; then
	sed -i '/ icmp/s/^#\?/#/' ${RULES}						# Comment out
else
	sed -i '/ icmp/s/^#//' ${RULES}							# Uncomment
fi	 

#############################################################################
# Modify line with "drop port 853 from LAN" rule (option "allow_dot")
#############################################################################
if [[ "${allow_dot:-"N"}" == "Y" ]]; then
	sed -i '/ 853 reject$/s/^#\?/#/' ${RULES}				# Comment out
else
	sed -i '/ 853 reject$/s/^#//' ${RULES}					# Uncomment
fi	 

#############################################################################
# Modify line with "drop port 113 from LAN" rule (option "allow_doq"):
#############################################################################
if [[ "${allow_doq:-"N"}" == "Y" ]]; then
	sed -i '/ 8853 reject$/s/^#\?/#/' ${RULES}				# Comment out
else
	sed -i '/ 8853 reject$/s/^#//' ${RULES}					# Uncomment
fi	 

#############################################################################
# Modify lines with "pkttype multicast" rules (option "allow_multicast"):
#############################################################################
if [[ "${allow_multicast:-"N"}" == "N" ]]; then
	sed -i '/pkttype multicast allow$/s/^#\?/#/' ${RULES}	# Comment out
	sed -i '/pkttype multicast reject$/s/^#//' ${RULES}		# Uncomment
else
	sed -i '/pkttype multicast allow$/s/^#//' ${RULES}		# Uncomment
	sed -i '/pkttype multicast reject$/s/^#\?/#/' ${RULES}	# Comment out
fi

#############################################################################
# Modify line with "port 113 allow" rule (option "allow_ident"):
#############################################################################
if [[ "${allow_ident:-"N"}" == "N" ]]; then
	sed -i '/ dport 113 allow$/s/^#\?/#/' ${RULES}			# Comment out
else
	sed -i '/ dport 113 allow$/s/^#//' ${RULES}				# Uncomment
fi	 

#############################################################################
# Load the ruleset:
#############################################################################
nft -f ${RULES}

#############################################################################
# Normally, we exit with an error code of 0.  But loading the ruleset fails,
# we normally want the service to fail as well...
#############################################################################
#exit 0
