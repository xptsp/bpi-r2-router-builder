#!/usr/bin/env bash

#############################################################################################
# Variables are needed in the functions call later.
#############################################################################################
runUnattended=true
TABLE=$(grep -m 1 "^table inet " /etc/nftables.conf | awk '{print $3}')
TXT="pivpn-openvpn"
SKIP_MAIN=true

#############################################################################################
# Are we starting the service?  If so, do everything in this block:
#############################################################################################
if [[ "$1" == "start" ]]; then
	# Set variable "SKIP_MAIN" to "true" in order to skip execution of function "main" when sourcing the INSTALLER:
	source /usr/local/src/pivpn_install_modded.sh

	# If the server name has been decided, read it in now.  This is done before
	# reading "setupVars.conf" to avoid incorrectly overwriting the setting.
	[[ -f /etc/openvpn/.server_name ]] && source /etc/openvpn/.server_name

	# Set all the variables:
	source /etc/pivpn/setupVars.conf

	# If certain settings aren't set, try to set them automagically:
	[[ -z "${IPv4dev}" ]] && chooseInterface
	[[ -z "${pivpnHOST}" ]] && askPublicIPOrDNS
	[[ -z "${SERVER_NAME}" ]] && generateServerName

	# Generate server certificate and DH parameters if necessary.
	[[ ! -f /etc/openvpn/crl.pem ]] && GenerateOpenVPN

	# Create the "/etc/openvpn/server.conf" file if it doesn't already exist:
	FILE=/etc/openvpn/pivpn.conf
	if [[ ! -f ${FILE} ]]; then
		createServerConf
		sed -i "s|dev tun|dev pivpn\ndev-type tun|" ${FILE}
		echo "management 127.0.0.1 7505" >> ${FILE}
	fi

	# Configure OVPN if not already done so:
	test -f /etc/openvpn/easy-rsa/pki/Default.txt || confOVPN

	# Add the firewall rules to support PiVPN:
	nft insert rule inet ${TABLE} nat_postrouting oifname @DEV_WAN ip saddr ${pivpnNET}/${subnetClass} masquerade comment \"${TXT}\"
	nft insert rule inet ${TABLE} input_wan ${pivpnPROTO,,} dport ${pivpnPORT} accept comment \"${TXT}\"
	nft insert rule inet ${TABLE} forward iifname @DEV_WAN oifname ${pivpnDEV,,} ip daddr ${pivpnNET}/${subnetClass} ct state related,established accept comment \"${TXT}\"
	nft insert rule inet ${TABLE} forward iifname ${pivpnDEV,,} oifname @DEV_WAN ip saddr ${pivpnNET}/${subnetClass} accept comment \"${TXT}\"

#############################################################################################
# Are we stopping the service?  If so, remove the firewall rules:
#############################################################################################
elif [[ "$1" == "stop" ]]; then
	for CHAIN in $(_nft list table inet ${TABLE} | grep -v chain | grep "${TXT}" | awk '{print $2}'); do
		_nft -a list chain inet ${TABLE} ${CHAIN} | grep "${TXT}" | grep "handle" | awk '{print $NF}' | while read HANDLE; do
			[[ "${HANDLE}" -gt 0 ]] 2> /dev/null && _nft delete rule inet ${TABLE} ${CHAIN} handle ${HANDLE}
		done
	done
fi
