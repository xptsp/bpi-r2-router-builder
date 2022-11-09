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
	source /usr/local/src/modded_pivpn_install.sh

	# If the server name has been decided, read it in now.  This is done before
	# reading "setupVars.conf" to avoid incorrectly overwriting the setting.
	[[ -f /etc/openvpn/.server_name ]] && source /etc/openvpn/.server_name

	# Set all the variables:
	source /etc/pivpn/setupVars.conf

	# Determine IP address if one hasn't been specified already:
	if [ -z "${pivpnHOST}" ]; then
		if ! pivpnHOST=$(dig +short myip.opendns.com @resolver1.opendns.com); then
			if ! pivpnHOST=$(curl eth0.me)
			then
				echo "Unable to determine IP address.  Specify domain name or IP address in \"pivpnHOST\" variable."
				exit $?
			fi
		fi
	fi

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

	# Remove any existing firewall rules for PiVPN: 
	$0 stop

	# Add the firewall rules to support PiVPN:
	nft insert rule inet ${TABLE} input_wan ${pivpnPROTO,,} dport ${pivpnPORT} accept comment \"${TXT}\"
	nft insert rule inet ${TABLE} forward iifname @DEV_WAN oifname ${pivpnDEV,,} ip daddr ${pivpnNET}/${subnetClass} ct state related,established accept comment \"${TXT}\"
	nft insert rule inet ${TABLE} forward iifname ${pivpnDEV,,} oifname @DEV_WAN ip saddr ${pivpnNET}/${subnetClass} accept comment \"${TXT}\"

	# Since PiVPN debug function cannot "see" the nftables masquerade rule, add an iptables rule for masquerade: 
	iptables -t nat -A POSTROUTING -s "${pivpnNET}/${subnetClass}" -o "${IPv4dev}" -j MASQUERADE -m comment --comment "openvpn-nat-rule"

#############################################################################################
# Are we stopping the service?  If so, remove the firewall rules:
#############################################################################################
elif [[ "$1" == "stop" ]]; then
	# Remove any PiVPN nftables rules: 
	for CHAIN in $(_nft list table inet ${TABLE} | grep chain | awk '{print $2}'); do
		_nft -a list chain inet ${TABLE} ${CHAIN} | grep "${TXT}" | grep "handle" | awk '{print $NF}' | while read HANDLE; do
			[[ "${HANDLE}" -gt 0 ]] 2> /dev/null && _nft delete rule inet ${TABLE} ${CHAIN} handle ${HANDLE}
		done
	done
	
	# Remove the PiVPN iptables rule: 
	iptables -t nat -D POSTROUTING -s "${pivpnNET}/${subnetClass}" -o "${IPv4dev}" -j MASQUERADE -m comment --comment "openvpn-nat-rule"
fi
