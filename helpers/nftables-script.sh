#!/bin/bash
#############################################################################
# This helper script loads the default and persistent (if available)_ ruleset
# into the router for use.  It then parses the interface configuration files
# to figure out which interfaces belong to WAN and LAN groups.  It then adds
# a few rules to complete the nftables configuration. 
#############################################################################
RED='\033[1;31m'
GREEN='\033[1;32m'
BLUE='\033[1;34m'
NC='\033[0m'
TXT=nftables-script  # String used to identify rules added by this script!
TABLE=$(grep -m 1 "^table inet " /etc/nftables.conf | awk '{print $3}')
IP=$(cat /etc/network/interfaces.d/br0 | grep address | awk '{print $NF}')

function stage()
{
	[[ ! -z "${DEBUG}" ]] && echo -e "${GREEN}Stage $1:${NC} $2"
}
function _nft()
{
	[[ ! -z "${DEBUG}" ]] && echo -e "${BLUE}CMD:${NC} nft $@"
	nft ${DEBUG} $@
	ERR=$?
	if [[ ${ERR} -gt 0 ]]; then
		[[ ! -z "${DEBUG}" ]] && echo -e "${RED}ERROR:${NC} Error code $ERR was returned!  Aborting!"
		exit $ERR
	fi
}

#############################################################################
cd /etc/network/interfaces.d/
test -f /etc/default/router-settings && source /etc/default/router-settings
[[ "${DEBUG}" == "Y" ]] && DEBUG=-c && echo -e "${GREEN}NOTE:${NC} Debug mode set in router-settings."
for PARAM in $@; do
	case $PARAM in
		start|reload)
			CMD=$PARAM
			;;
		debug)
			[[ -z "${DEBUG}" ]] && DEBUG=-c && echo -e "${GREEN}NOTE:${NC} Debug mode started."
			;;
		*)
			echo "Syntax: $0 [start|reload|debug]"
			exit 1
			;;
	esac
done

#############################################################################
# Load the default and persistent (if available) rulesets.
#############################################################################
if [[ "$CMD" == "start" ]]; then
	_nft -f /etc/nftables.conf
	test -f /etc/persistent-nftables.conf && _nft -f /etc/persistent-nftables.conf
fi

#############################################################################
# If we are reloading the ruleset, remove any script-generated rules: 
#############################################################################
if [[ "$CMD" == "reload" ]]; then
	for CHAIN in $(_nft list table inet ${TABLE} | grep -v chain | grep "${TXT}" | awk '{print $2}'); do
		_nft -a list chain inet ${TABLE} ${CHAIN} | grep "${TXT}" | awk '{print $NF}' | while read HANDLE; do
			[[ "${HANDLE}" -gt 0 ]] 2> /dev/null && _nft delete rule inet ${TABLE} ${CHAIN} handle ${HANDLE}
		done
	done
fi

#############################################################################
# Add any rules to make our ${TABLE} settings work as expected:
#############################################################################

# Add rules to allow pings from WAN at a rate of 5 pings per second if option "allow_ping" is "Y":
stage 1a "Option allow_ping=${allow_ping:-"N"}"
if [[ "${allow_ping:-"N"}" == "Y" ]]; then
	_nft add rule inet ${TABLE} input_wan icmp type echo-request limit rate 5/second accept
	_nft add rule inet ${TABLE} input_wan icmpv6 type echo-request limit rate 5/second accept
fi

# Add rule accepting IDENT requests if option "allow_ident" is "Y": 
stage 1b "Option allow_ident=${allow_ident:-"N"}"
[[ "${allow_ident:-"N"}" == "Y" ]] && _nft add rule inet ${TABLE} input_wan tcp dport 113 counter accept comment \"${TXT}\"

# Add rule accepting multicast packets from WAN if option "allow_multicast" is "Y":
if [[ "${allow_multicast:-"N"}" == "Y" ]]; then
	stage 1c-1 "Option allow_multicast=${allow_multicast:-"N"}"
	_nft add rule inet ${TABLE} input_wan pkttype multicast counter accept comment \"${TXT}\"
else
	stage 1c-2 "Option allow_multicast=${allow_multicast:-"N"}"
	_nft add rule inet ${TABLE} output_wan pkttype multicast counter reject comment \"${TXT}\"
fi

# Add rule rejecting DoT (port 853) packets from LAN if option "allow_dot" is "N":
stage 1d "Option allow_dot=${allow_dot:-"N"}"
[[ "${allow_dot:-"N"}" == "N" ]] && _nft add rule inet ${TABLE} forward_wan meta l4proto {tcp, udp} @th,16,16 853 counter reject comment \"${TXT}\"

# Add a jump to the DDoS protection rules in "mangle_prerouting" chain if option "disable_ddos" is "N":
stage 1e "Option disable_ddos=${disable_ddos:-"N"}"
[[ "${disable_ddos:-"N"}" == "N" ]] && _nft add rule inet ${TABLE} mangle_prerouting jump mangle_prerouting_ddos comment \"${TXT}\"

# Add DNS redirect rules ONLY if option "redirect_dns" is "Y":
stage 1f "Option redirect_dns=${redirect_dns:-"Y"}"
if [[ "${redirect_dns:-"Y"}" == "Y" ]]; then
	_nft add rule inet ${TABLE} nat_prerouting ip saddr != ${IP} ip daddr != ${IP} udp dport 53 counter dnat to ${IP} comment \"${TXT}\"
	_nft add rule inet ${TABLE} nat_prerouting ip saddr != ${IP} ip daddr != ${IP} tcp dport 53 counter dnat to ${IP} comment \"${TXT}\"
fi

#############################################################################
# Get a list of all interfaces that have the "masquerade" line in it.  These
# are the WAN interfaces that the rules will block incoming new connections on.
#############################################################################
IFACES=($(grep "masquerade" * | cut -d: -f 1))
STR="$(echo ${IFACES[@]} | sed "s| |, |g")"
ELEMENTS="$([[ ! -z "${STR}" ]] && echo " elements = { ${STR} }")"
stage 2 "DEV_WAN ${ELEMENTS/ =/}"
_nft flush set inet ${TABLE} DEV_WAN
[[ ! -z "${DEV_WAN}" ]] && _nft add element inet ${TABLE} DEV_WAN { ${DEV_WAN} }

#############################################################################
# Get a list of all interfaces that have the "no_internet" line in it.  These
# are the WAN interfaces that the rules will block new outgoing connections on.
#############################################################################
IFACES=($(grep no_internet $(grep -L "masquerade" *) | cut -d: -f 1))
STR="$(echo ${IFACES[@]} | sed "s| |, |g")"
ELEMENTS="$([[ ! -z "${STR}" ]] && echo " elements = { ${STR} }")"
stage 3 "DEV_WAN_DENY ${ELEMENTS/ =/}"
_nft flush set inet ${TABLE} DEV_WAN_DENY
[[ ! -z "${DEV_WAN_DENY}" ]] && _nft add element inet ${TABLE} DEV_WAN_DENY { ${DEV_WAN_DENY} }

#############################################################################
# Get a list of all interfaces that have the "no_local" line in it.  These
# are the LAN interfaces that the rules will block new outgoing connections on.
#############################################################################
IFACES=($(grep no_local $(grep -L "masquerade" *) | cut -d: -f 1))
STR="$(echo ${IFACES[@]} | sed "s| |, |g")"
ELEMENTS="$([[ ! -z "${STR}" ]] && echo " elements = { ${STR} }")"
stage 4 "DEV_LAN_DENY ${ELEMENTS/ =/}"
_nft flush set inet ${TABLE} DEV_LAN_DENY
[[ ! -z "${DEV_LAN_DENY}" ]] && _nft add element inet ${TABLE} DEV_LAN_DENY { ${DEV_LAN_DENY} }

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
stage 5 "DEV_LAN ${ELEMENTS/ =/}"
_nft flush set inet ${TABLE} DEV_LAN
[[ ! -z "${DEV_LAN}" ]] && _nft add element inet ${TABLE} DEV_LAN { ${DEV_LAN} }

#############################################################################
# Get the IP address/range associated with each LAN interface ONLY IF the
# interface is up and running.  Can't seem to correctly parse the address/range
# combination from the "/etc/network/interfaces.d/" files...
#############################################################################
ADDR=($(for IFACE in ${IFACES[@]}; do ip addr show $IFACE 2> /dev/null | grep " inet " | awk '{print $2}'; done))
STR="$(echo ${ADDR[@]} | sed "s| |, |g")"
ELEMENTS="$([[ ! -z "${STR}" ]] && echo " elements = { ${STR} }")"
stage 6 "INSIDE_NETWORK ${ELEMENTS/ =/}"
_nft flush set inet ${TABLE} INSIDE_NETWORK
[[ ! -z "${INSIDE_NETWORK}" ]] && _nft add element inet ${TABLE} INSIDE_NETWORK { ${INSIDE_NETWORK} }

#############################################################################
# Return error code 0 because we got here without errors:
#############################################################################
exit 0
