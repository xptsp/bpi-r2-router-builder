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
[[ "$1" != "start" ]] && DEBUG=-c && echo "NOTE: Debug mode started.  Use \"start\" to activate ruleset."
 
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
ELEMENTS="$([[ ! -z "${STR}" ]] && echo " elements = { ${STR} }")"
[[ ! -z "$DEBUG" ]] && echo "DEBUG 1a: DEV_WAN ${ELEMENTS}"
sed -i "s|set DEV_WAN .*|set DEV_WAN \{ type ifname; ${ELEMENTS} \}|" ${RULES}

#############################################################################
# Get a list of all interfaces that have the "no_internet" line in it.  These
# are the WAN interfaces that the rules will block new outgoing connections on.
#############################################################################
IFACES=($(grep no_internet $(grep -L "masquerade" *) | cut -d: -f 1))
STR="$(echo ${IFACES[@]} | sed "s| |, |g")"
ELEMENTS="$([[ ! -z "${STR}" ]] && echo " elements = { ${STR} }")"
[[ ! -z "$DEBUG" ]] && echo "DEBUG 1b: DEV_WAN_DENY ${ELEMENTS}"
sed -i "s|set DEV_WAN_DENY .*|set DEV_WAN_DENY { type ifname; ${ELEMENTS} }|" ${RULES}

#############################################################################
# Get a list of all interfaces that have the "no_local" line in it.  These
# are the LAN interfaces that the rules will block new outgoing connections on.
#############################################################################
IFACES=($(grep no_local $(grep -L "masquerade" *) | cut -d: -f 1))
STR="$(echo ${IFACES[@]} | sed "s| |, |g")"
ELEMENTS="$([[ ! -z "${STR}" ]] && echo " elements = { ${STR} }")"
[[ ! -z "$DEBUG" ]] && echo "DEBUG 1c: DEV_LAN_DENY ${ELEMENTS}"
sed -i "s|set DEV_LAN_DENY .*|set DEV_LAN_DENY { type ifname; ${ELEMENTS} }|" ${RULES}

#############################################################################
# Get a list of all interfaces that DO NOT have the "masquerade" line in it AND
# have an address assigned.  These are the LAN interfaces that the rules will 
# allow communications to flow between, and to the WAN interfaces.  The default
# rules will automatically deny new incoming connections from the WAN interfaces
# to the LAN interfaces.
#############################################################################
IFACES=($(grep address $(grep -L "masquerade" *) | cut -d: -f 1))
STR="$(echo ${IFACES[@]} | sed "s| |, |g")"
ELEMENTS="$([[ ! -z "${STR}" ]] && echo " elements = { ${STR} }")"
[[ ! -z "$DEBUG" ]] && echo "DEBUG 1d: DEV_LAN ${ELEMENTS}"
sed -i "s|set DEV_LAN .*|set DEV_LAN { type ifname; ${ELEMENTS} }|" ${RULES}

#############################################################################
# Get the IP address/range associated with each LAN interface ONLY IF the
# interface is up and running.  Can't seem to correctly parse the address/range
# combination from the "/etc/network/interfaces.d/" files...
#############################################################################
ADDR=($(for IFACE in ${IFACES[@]}; do ip addr show $IFACE | grep " inet " | awk '{print $2}'; done))
STR="$(echo ${ADDR[@]} | sed "s| |, |g")"
ELEMENTS="$([[ ! -z "${STR}" ]] && echo " elements = { ${STR} }")"
[[ ! -z "$DEBUG" ]] && echo "DEBUG 1e: DEV_IPs ${ELEMENTS}"
sed -i "s|set DEV_IPs .*|set DEV_IPs { type ipv4_addr; flags interval; ${ELEMENTS} }|" ${RULES}

#############################################################################
# Get the hotspot address and put in the the ruleset:
#############################################################################
[[ ! -z "$DEBUG" ]] && echo "DEBUG 2a: getting hotspot address:port"
ADDR=($(cat /etc/nginx/sites-available/hotspot | grep "listen" | awk '{print $2}' | sed "s/:/ /"))
[[ ! -z "$DEBUG" ]] && echo "DEBUG 2b: PORTAL_ADDR = ${ADDR[0]}"
sed -i "s|define PORTAL_ADDR = .*|define PORTAL_ADDR = ${ADDR[0]}|" ${RULES}
[[ ! -z "$DEBUG" ]] && echo "DEBUG 2c: PORTAL_PORT = ${ADDR[1]}"
sed -i "s|define PORTAL_PORT = .*|define PORTAL_PORT = ${ADDR[1]}|" ${RULES}

#############################################################################
# Modify lines with "icmp" rule (option "allow_ping"):
#############################################################################
[[ ! -z "$DEBUG" ]] && echo "DEBUG 03: allow_ping = ${allow_ping:-"N"}"
if [[ "${allow_ping:-"N"}" == "N" ]]; then
	sed -i '/ icmp/s/^#\?/#/' ${RULES}						# Comment out
else
	sed -i '/ icmp/s/^#//' ${RULES}							# Uncomment
fi	 

#############################################################################
# Modify line with "drop port 853 from LAN" rule (option "allow_dot")
#############################################################################
[[ ! -z "$DEBUG" ]] && echo "DEBUG 04: allow_dot = ${allow_dot:-"N"}"
if [[ "${allow_dot:-"N"}" == "Y" ]]; then
	sed -i '/ 853 reject$/s/^#\?/#/' ${RULES}				# Comment out
else
	sed -i '/ 853 reject$/s/^#//' ${RULES}					# Uncomment
fi	 

#############################################################################
# Modify line with "drop port 113 from LAN" rule (option "allow_doq"):
#############################################################################
[[ ! -z "$DEBUG" ]] && echo "DEBUG 05: allow_doq = ${allow_doq:-"N"}"
if [[ "${allow_doq:-"N"}" == "Y" ]]; then
	sed -i '/ 8853 reject$/s/^#\?/#/' ${RULES}				# Comment out
else
	sed -i '/ 8853 reject$/s/^#//' ${RULES}					# Uncomment
fi	 

#############################################################################
# Modify lines with "pkttype multicast" rules (option "allow_multicast"):
#############################################################################
[[ ! -z "$DEBUG" ]] && echo "DEBUG 06: allow_multicast = ${allow_multicast:-"N"}"
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
[[ ! -z "$DEBUG" ]] && echo "DEBUG 07: allow_ident = ${allow_ident:-"N"}"
if [[ "${allow_ident:-"N"}" == "N" ]]; then
	sed -i '/ dport 113 accept$/s/^#\?/#/' ${RULES}			# Comment out
else
	sed -i '/ dport 113 accept$/s/^#//' ${RULES}			# Uncomment
fi	 

#############################################################################
# Load the ruleset and abort if non-zero exit code:
#############################################################################
[[ -z "$DEBUG" ]] && nft add table inet filter {}
nft -f ${RULES} ${DEBUG} || exit $?
[[ ! -z "$DEBUG" ]] && nano ${RULES}

#############################################################################
# Load any custom firewall rules and abort if non-zero exit code:
#############################################################################
if test -f /etc/nftables-added.conf; then nft -f /etc/nftables-added.conf || exit $?; fi

#############################################################################
# Normally, we exit with an error code of 0.  But if loading the ruleset 
# fails, we normally want the service to fail as well...
#############################################################################
#exit 0
