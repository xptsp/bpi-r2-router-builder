#!/usr/bin/env bash

#############################################################################################
# Variables are needed in the functions call later.
#############################################################################################
runUnattended=true
TABLE=$(grep -m 1 "^table inet " /etc/nftables.conf | awk '{print $3}')
TXT=$2-wireguard
FILE=/etc/wireguard/$2.conf

#############################################################################################
# Read in PiVPN variables:
#############################################################################################
# Set all the variables:
source /etc/pivpn/wireguard/setupVars.conf

# Temporarily override the PiVPN device name:
pivpnDEV=${2}

#############################################################################################
# If we are starting the service, generate any supporting files we need to run PiVPN:
#############################################################################################
if [[ "$1" == "start" && "$2" == "wg0" ]]; then
	# Make a copy of the settings files in temporary folder so we can modify them:
	CFG=/etc/pivpn/openvpn/setupVars.conf

	# Set variable "SKIP_MAIN" to "true" in order to skip execution of function "main" when sourcing the INSTALLER:
	SKIP_MAIN=true
	source /opt/bpi-r2-router-builder/misc/modded_pivpn_install.sh

	# Determine IP address if one hasn't been specified already:
	WRITE=false
	if [ -z "${pivpnHOST}" ]; then
		WRITE=true
		if ! pivpnHOST=$(dig +short myip.opendns.com @resolver1.opendns.com); then
			if ! pivpnHOST=$(curl eth0.me)
			then
				echo "Unable to determine IP address.  Specify domain name or IP address in \"pivpnHOST\" variable."
				exit $?
			fi
		fi
		echo "pivpnHOST=${pivpnHOST}" >> ${CFG}
	fi

	# Set IP address and subnet if not already set:
	[[ -z "${subnetClass}" ]] && WRITE=true && subnetClass=255.255.255.0 && echo "subnetClass=255.255.255.0" >> ${CFG}
	[[ -z "${pivpnNET}" ]] && WRITE=TRUE && pivpnNET=10.6.0.0 && echo "pivpnNET=10.6.0.0" >> ${CFG}

	# Create the wireguard configuration file if it doesn't already exist:
	[[ ! -f ${FILE} ]] && setWireguardDefaultVars && ConfWireGuard
fi

#############################################################################################
# Remove any PiVPN nftables rules for this interface:
#############################################################################################
for CHAIN in $(nft list table inet ${TABLE} | grep chain | awk '{print $2}'); do
	nft -a list chain inet ${TABLE} ${CHAIN} | grep "${TXT}" | grep "handle" | awk '{print $NF}' | while read HANDLE; do
		[[ "${HANDLE}" -gt 0 ]] 2> /dev/null && nft delete rule inet ${TABLE} ${CHAIN} handle ${HANDLE}
	done
done

#############################################################################################
# Add the necessary firewall rules for this interface if we are starting a service:
#############################################################################################
if [[ "$1" == "start" ]]; then
	pivpnNET=$(grep -m 1 "^Address" ${FILE} | awk '{print $3}' | cut -d/ -f 1) 
	pivpnPORT=$(grep -m 1 "^ListenPort" ${FILE} | awk '{print $3}')

	# Allow everything in through the server interface:
	nft add rule inet ${TABLE} input iifname ${pivpnDEV} accept comment \"${TXT}\"

	# Masquerade all communication to this interface:
	nft insert rule inet ${TABLE} nat_postrouting oifname ${IPv4dev} ip saddr ${pivpnNET}/${subnetClass} masquerade comment \"${TXT}\"

	# Allow the server port to be accepted by the firewall:
	nft insert rule inet ${TABLE} input_wan iifname ${IPv4dev} udp dport ${pivpnPORT} accept comment \"${TXT}\"

	# Allow this interface to access the internet, but only allow established/related connections back:
	nft insert rule inet ${TABLE} forward_vpn_server iifname ${IPv4dev} oifname ${pivpnDEV} ip daddr ${pivpnNET}/${subnetClass} ct state related,established accept comment \"${TXT}\"
	nft insert rule inet ${TABLE} forward_vpn_server iifname ${pivpnDEV} oifname ${IPv4dev} ip saddr ${pivpnNET}/${subnetClass} accept comment \"${TXT}\"

	# Allow this interface and the local network communication bi-directionally:
	nft insert rule inet ${TABLE} forward_vpn_server iifname @DEV_LAN oifname ${pivpnDEV} ip daddr ${pivpnNET}/${subnetClass} ct state related,established accept comment \"${TXT}\"
	nft insert rule inet ${TABLE} forward_vpn_server iifname ${pivpnDEV} oifname @DEV_LAN ip saddr ${pivpnNET}/${subnetClass} accept comment \"${TXT}\"
fi
