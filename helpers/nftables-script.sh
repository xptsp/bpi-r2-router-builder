#!/bin/bash
#############################################################################
# This helper script makes copies the rules in "/etc/nftables.conf" to 
# "/tmp/", then modifies them to cover the expected network configuration 
# found under the "/etc/network/interfaces.d/" directory.  After modifying
# the rules, the script loads the new nftables rules.  
#############################################################################
function debug()
{
	STAGE=$1
	shift
	[[ ! -z "${DEBUG}" ]] && echo -n -e "\nDEBUG $STAGE: $@... "
}
function _nft()
{
	if ! nft ${DEBUG} $@; then
		echo "ERROR $?"
		exit $?
	fi
}

#############################################################################
for PARAM in $@; do
	case $PARAM in
		start|reload)
			CMD=$PARAM
			;;
		debug)
			DEBUG=-c && echo "NOTE: Debug mode started."
			;;
		*)
			echo "Syntax: $0 [start|reload|debug]"
			exit 1
			;;
	esac
done

#############################################################################
# Load router settings, copy the nftables ruleset we're using to the "/tmp" 
# folder, then change to the "/etc/network/interfaces.d/" directory.
#############################################################################
test -f /etc/default/router-settings && source /etc/default/router-settings
RULES=/tmp/nftables.conf
cp /etc/nftables.conf ${RULES}
cd /etc/network/interfaces.d/

#############################################################################
# Get a list of all interfaces that have the "masquerade" line in it.  These
# are the WAN interfaces that the rules will block incoming new connections on.
#############################################################################
IFACES=($(grep "masquerade" * | cut -d: -f 1))
DEV_WAN="$(echo ${IFACES[@]} | sed "s| |, |g")"
ELEMENTS="$([[ ! -z "${DEV_WAN}" ]] && echo " elements = { ${DEV_WAN} }")"
debug 1a DEV_WAN ${ELEMENTS}
sed -i "s|set DEV_WAN .*|set DEV_WAN \{ type ifname; ${ELEMENTS} \}|" ${RULES}

#############################################################################
# Get a list of all interfaces that have the "no_internet" line in it.  These
# are the WAN interfaces that the rules will block new outgoing connections on.
#############################################################################
IFACES=($(grep no_internet $(grep -L "masquerade" *) | cut -d: -f 1))
DEV_WAN_DENY="$(echo ${IFACES[@]} | sed "s| |, |g")"
ELEMENTS="$([[ ! -z "${DEV_WAN_DENY}" ]] && echo " elements = { ${DEV_WAN_DENY} }")"
debug 1b DEV_WAN_DENY ${ELEMENTS}
sed -i "s|set DEV_WAN_DENY .*|set DEV_WAN_DENY { type ifname; ${ELEMENTS} }|" ${RULES}

#############################################################################
# Get a list of all interfaces that have the "no_local" line in it.  These
# are the LAN interfaces that the rules will block new outgoing connections on.
#############################################################################
IFACES=($(grep no_local $(grep -L "masquerade" *) | cut -d: -f 1))
DEV_LAN_DENY="$(echo ${IFACES[@]} | sed "s| |, |g")"
ELEMENTS="$([[ ! -z "${DEV_LAN_DENY}" ]] && echo " elements = { ${DEV_LAN_DENY} }")"
debug 1c DEV_LAN_DENY ${ELEMENTS}
sed -i "s|set DEV_LAN_DENY .*|set DEV_LAN_DENY { type ifname; ${ELEMENTS} }|" ${RULES}

#############################################################################
# Get a list of all interfaces that DO NOT have the "masquerade" line in it AND
# have an address assigned.  These are the LAN interfaces that the rules will 
# allow communications to flow between, and to the WAN interfaces.  The default
# rules will automatically deny new incoming connections from the WAN interfaces
# to the LAN interfaces.
#############################################################################
IFACES=($(grep address $(grep -L "masquerade" *) | cut -d: -f 1))
DEV_LAN="$(echo ${IFACES[@]} | sed "s| |, |g")"
ELEMENTS="$([[ ! -z "${DEV_LAN}" ]] && echo " elements = { ${DEV_LAN} }")"
debug 1d DEV_LAN ${ELEMENTS}
sed -i "s|set DEV_LAN .*|set DEV_LAN { type ifname; ${ELEMENTS} }|" ${RULES}

#############################################################################
# Get the IP address/range associated with each LAN interface ONLY IF the
# interface is up and running.  Can't seem to correctly parse the address/range
# combination from the "/etc/network/interfaces.d/" files...
#############################################################################
ADDR=($(for IFACE in ${IFACES[@]}; do ip addr show $IFACE 2> /dev/null | grep " inet " | awk '{print $2}'; done))
INSIDE_NETWORK="$(echo ${ADDR[@]} | sed "s| |, |g")"
ELEMENTS="$([[ ! -z "${INSIDE_NETWORK}" ]] && echo " elements = { ${INSIDE_NETWORK} }")"
debug 1e DEV_IPs ${ELEMENTS}
sed -i "s|set INSIDE_NETWORK .*|set INSIDE_NETWORK { type ipv4_addr; flags interval; ${ELEMENTS} }|" ${RULES}

#############################################################################
# If we are starting or reloading the ruleset, do so.  Abort if errors:
#############################################################################
if [[ "$CMD" != "reload" ]]; then
	debug 2a Applying ruleset ${RULES}
	_nft -f ${RULES} ${DEBUG}
	if test -f /etc/persistent-nftables.conf; then 
		debug 2b Applying ruleset /etc/persistent-nftables.conf
		_nft -f /etc/persistent-nftables.conf 
	fi
fi

#############################################################################
# If we are reloading, reload the sets as necessary:
#############################################################################
if [[ "$CMD" == "reload" ]]; then
	# Refresh the list of WAN interfaces:
	debug 3a Purging DEV_WAN 
	_nft flush set inet firewall DEV_WAN
	debug 3b Adding elements to DEV_WAN 
	[[ ! -z "${DEV_WAN}" ]] && _nft add element inet firewall DEV_WAN { ${DEV_WAN} }

	# Refresh the list of LAN interfaces: 
	debug 3c Purging DEV_LAN
	_nft flush set inet firewall DEV_LAN
	debug 3d Adding elements to DEV_LAN 
	[[ ! -z "${DEV_LAN}" ]] && _nft add element inet firewall DEV_LAN { ${DEV_LAN} }

	# Refresh the list of LAN interfaces denied access to WAN interfaces:
	debug 3e Purging DEV_WAN_DENY
	_nft flush set inet firewall DEV_WAN_DENY
	debug 3f Adding elements to DEV_WAN_DENY	
	[[ ! -z "${DEV_WAN_DENY}" ]] && _nft add element inet firewall DEV_WAN_DENY { ${DEV_WAN_DENY} }

	# Refresh the list of LAN interfaces denied access to other LAN interfaces: 
	debug 3g Purging DEV_LAN_DENY
	_nft flush set inet firewall DEV_LAN_DENY
	debug 3h Adding elements to DEV_LAN_DENY	
	[[ ! -z "${DEV_LAN_DENY}" ]] && _nft add element inet firewall DEV_LAN_DENY { ${DEV_LAN_DENY} }

	# Refresh the list of IP addresses of LAN interfaces: 
	debug 3i Purging INSIDE_NETWORK
	_nft flush set inet firewall INSIDE_NETWORK
	debug 3j Adding elements to INSIDE_NETWORK	
	[[ ! -z "${INSIDE_NETWORK}" ]] && _nft add element inet firewall INSIDE_NETWORK { ${INSIDE_NETWORK} }
fi

#############################################################################
# Add any rules to make our firewall settings work as expected:
#############################################################################
# This is the string we are going to use to identify rules added by this script: 
TXT=nftables-script

# Remove script-generated rules from the ruleset: 
for TABLE in $(_nft list table inet firewall | grep chain | awk '{print $2}'); do
	for HANDLE in $(_nft -a list chain inet firewall ${TABLE} | grep "${TXT}" | awk '{print $NF}'); do 
		debug '4 ' Purging rule handle $HANDLE 
		_nft delete rule inet firewall ${TABLE} handle ${HANDLE}
	done
done

# Add rules to allow pings from WAN at a rate of 5 pings per second if option "allow_ping" is "Y":
debug 4a Option allow_ping=${allow_ping:-"N"}
if [[ "${allow_ping:-"N"}" == "Y" ]]; then
	_nft add rule inet firewall input_wan icmp type echo-request limit rate 5/second accept
	_nft add rule inet firewall input_wan icmpv6 type echo-request limit rate 5/second accept
fi

# Add rule accepting IDENT requests if option "allow_ident" is "Y": 
debug 4b Option allow_ident=${allow_ident:-"N"}
[[ "${allow_ident:-"N"}" == "Y" ]] && _nft add rule inet firewall input_wan tcp dport 113 counter accept comment \"${TXT}\"

# Add rule accepting multicast packets from WAN if option "allow_multicast" is "Y":
if [[ "${allow_multicast:-"N"}" == "Y" ]]; then
	debug 4c-1 Option allow_multicast=${allow_multicast:-"N"}
	_nft add rule inet firewall input_wan pkttype multicast counter accept comment \"${TXT}\"
else
	debug 4c-2 Option allow_multicast=${allow_multicast:-"N"}
	_nft add rule inet firewall output_wan pkttype multicast counter reject comment \"${TXT}\"
fi

# Add rule rejecting DoT (port 853) packets from LAN if option "allow_dot" is "N":
debug 4d Option allow_dot=${allow_dot:-"N"}
[[ "${allow_dot:-"N"}" == "N" ]] && _nft add rule inet firewall forward_wan meta l4proto {tcp, udp} @th,16,16 853 counter reject comment \"${TXT}\"

# Add rule rejecting DoQ (port 8853) packets from LAN if option "allow_doq" is "N":
debug 4d Option allow_doq=${allow_doq:-"N"}
[[ "${allow_doq:-"N"}" == "N" ]] && _nft add rule inet firewall forward_wan meta l4proto {tcp, udp} @th,16,16 8853 counter reject comment \"${TXT}\"

# Add a jump to the DDoS protection rules in "mangle_prerouting" chain if option "disable_ddos" is "N":
debug 4e Option disable_ddos=${disable_ddos:-"N"}
[[ "${disable_ddos:-"N"}" == "N" ]] && _nft add rule inet firewall mangle_prerouting jump mangle_prerouting_ddos comment \"${TXT}\"

# Add DNS redirect rules ONLY if option "redirect_dns" is "Y":
debug 4f Option redirect_dns=${redirect_dns:-"Y"}
if [[ "${redirect_dns:-"Y"}" == "Y" ]]; then
	IP=$(cat /etc/network/interfaces.d/br0 | grep address | awk '{print $NF}')
	_nft add rule inet firewall nat_prerouting_lan ip saddr != ${IP} ip daddr != ${IP} udp dport 53 counter dnat to ${IP} comment \"${TXT}\"
	_nft add rule inet firewall nat_prerouting_lan ip saddr != ${IP} ip daddr != ${IP} tcp dport 53 counter dnat to ${IP} comment \"${TXT}\"
fi

#############################################################################
# Normally, we exit with an error code of 0.  But if loading the ruleset 
# fails, we normally want the service to fail as well...
#############################################################################
echo ""
#exit 0
