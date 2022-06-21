#!/bin/bash
#############################################################################
# This helper script makes copies the rules in "/etc/nftables.conf" to 
# "/tmp/", then modifies them to cover the expected network configuration 
# found under the "/etc/network/interfaces.d/" directory.  After modifying
# the rules, the script loads the new nftables rules.  
#############################################################################
# Chains:
# - List a chain in the table:     nft list chain inet firewall <chain>
# - Add a rule to chain in table:  nft add rule inet firewall <chain> <rule>
# - Get handle for rule in chain:  nft -a list chain inet firewall <chain> | grep "<search_spec>" | awk '{print $NF}'
# - Delete rule from a chain:      nft delete rule inet firewall <chain> handle <handle>
# - Flush a chain:                 nft flush chain inet firewall <chain>
#############################################################################
# Maps & Sets:
# - Add an element:                nft add element inet firewall <map> { <data>, [<data>}... }
# - Remove an element:             nft delete element inet firewall <map> { data>, [<data>}... }
# Maps:
# - List the elements in a map:    nft list map inet firewall <map>
# - Flush contents of a map:       nft flush map inet firewall <map>
# Set:
# - List the elements in a map:    nft list set inet firewall <set>
# - Flush contents of a map:       nft flush set inet firewall <set>
#############################################################################
test -f /etc/default/router-settings && source /etc/default/router-settings
 
#############################################################################
# Wait for the /tmp folder to be mounted before continuing:
#############################################################################
while ! mount | grep " /tmp " >& /dev/null; do sleep 1; done

#############################################################################
# Copy the nftables ruleset we're using to the "/tmp" folder, then change
# to the "/etc/network/interfaces.d/" directory.
#############################################################################
RULES=/tmp/nftables.conf
cp /etc/nftables.conf ${RULES}
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
	sed -i '/pkttype multicast accept$/s/^#\?/#/' ${RULES}	# Comment out
	sed -i '/pkttype multicast reject$/s/^#//' ${RULES}		# Uncomment
else
	sed -i '/pkttype multicast accept$/s/^#//' ${RULES}		# Uncomment
	sed -i '/pkttype multicast reject$/s/^#\?/#/' ${RULES}	# Comment out
fi

#############################################################################
# Modify line with "port 113 accept" rule (option "allow_ident"):
#############################################################################
if [[ "${allow_ident:-"N"}" == "N" ]]; then
	sed -i '/ dport 113 accept$/s/^#\?/#/' ${RULES}			# Comment out
else
	sed -i '/ dport 113 accept$/s/^#//' ${RULES}			# Uncomment
fi	 

#############################################################################
# Load the ruleset and abort if non-zero exit code:
#############################################################################
nft add table ip filter {}
nft -f ${RULES} || exit $?

#############################################################################
# Load any custom firewall rules and abort if non-zero exit code:
#############################################################################
if test -f /etc/nftables-added.conf; then nft -f /etc/nftables-added.conf || exit $?; fi

#############################################################################
# Normally, we exit with an error code of 0.  But if loading the ruleset 
# fails, we normally want the service to fail as well...
#############################################################################
#exit 0
