#!/usr/bin/env bash

#############################################################################################
# Variables are needed in the functions call later.
#############################################################################################
runUnattended=true
TABLE=$(grep -m 1 "^table inet " /etc/nftables.conf | awk '{print $3}')
[[ ! "$2" =~ ^pivpn(0|1) ]] && echo "2nd parameter must specify valid PiVPN device." && exit 1
TXT=$2-openvpn
FILE=/etc/openvpn/$2.conf

#############################################################################################
# Read in PiVPN variables:
#############################################################################################
# If the server name has been decided, read it in now.  This is done before
# reading "setupVars.conf" to avoid incorrectly overwriting the setting.
[[ -f /etc/openvpn/.server_name ]] && SERVER_NAME=$(cat /etc/openvpn/.server_name)

# Set all the variables:
source /etc/pivpn/openvpn/setupVars.conf

# Temporarily override the PiVPN device name:
pivpnDEV=${2}

#############################################################################################
# If we are starting the service, generate any supporting files we need to run PiVPN:  
#############################################################################################
if [[ "$1" == "start" && "$2" == "pivpn0" ]]; then
	# Make a copy of the settings files in temporary folder so we can modify them:
	cp /etc/pivpn/openvpn/setupVars.conf /tmp/setupVars.conf

	# Set variable "SKIP_MAIN" to "true" in order to skip execution of function "main" when sourcing the INSTALLER:
	SKIP_MAIN=true
	source /usr/local/src/modded_pivpn_install.sh

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
		echo "pivpnHOST=${pivpnHOST}" >> /tmp/setupVars.conf
	fi

	# Set subnet class if not already set:
	[[ -z "${subnetClass}" ]] && WRITE=true && subnetClass=255.255.255.0 && echo "subnetClass=255.255.255.0" >> /tmp/setupVars.conf
	[[ -z "${pivpnNET}" ]] && WRITE=TRUE && pivpnNET=10.8.0.0 && echo "pivpnNET=10.8.0.0" >> /tmp/setupVars.conf

	# If certain settings aren't set, try to set them automagically:
	[[ -z "${IPv4dev}" ]] && WRITE=true && chooseInterface
	[[ -z "${pivpnHOST}" ]] && WRITE=true && askPublicIPOrDNS
	[[ -z "${SERVER_NAME}" ]] && WRITE=true && generateServerName

	# Generate server certificate and DH parameters if necessary.
	[[ ! -f /etc/openvpn/crl.pem ]] && WRITE=true && GenerateOpenVPN

	# Create the "/etc/openvpn/server.conf" file if it doesn't already exist:
	FILE=/etc/openvpn/${pivpnDEV}.conf
	if [[ ! -f ${FILE} ]]; then
		createServerConf
		sed -i "s|dev tun|dev ${pivpnDEV}\ndev-type tun|" ${FILE}
		echo "management 127.0.0.1 7505 /etc/openvpn/.server_name" >> ${FILE}
	fi

	# Configure OVPN if not already done so:
	[[ ! -f /etc/openvpn/easy-rsa/pki/Default.txt ]] && WRITE=true && confOVPN

	# Write altered PiVPN configuration back to storage location:
	[[ "${WRITE}" == "true" ]] && mv /tmp/setupVars.conf /etc/pivpn/openvpn/setupVars.conf

#############################################################################################
# Are we initializing the DNS-only PiVPN server?
#############################################################################################
elif [[ "$1" == "start" && "$2" == "pivpn1" ]]; then
	# If "pivpn1.conf" doesn't exist, copy and modify the "pivpn0.conf" file for this:
	if [[ ! -f ${FILE} ]]; then
		cp /etc/openvpn/pivpn0.conf ${FILE}
		sed -i "s|^dev pivpn0|dev pivpn1|" ${FILE}
		sed -i "s|^port .*|port $(( $(grep -m 1 "^port "  ${FILE} | awk '{print $2}') + 1 ))|" ${FILE}
		IP=($(grep -m 1 "^server " ${FILE} | awk '{print $2}' | sed "s|\.| |g"))
		sed -i "s|${IP[0]}\.${IP[1]}\.${IP[2]}\.|${IP[0]}\.${IP[1]}\.$(( ${IP[2]} + 1 ))\.|g" ${FILE}
		sed -i "s|^push \"redirect-gateway|#push \"redirect-gateway|" ${FILE}
		PORT=$(grep -m 1 "^management" ${FILE} | awk '{print $3}')
		sed -i "s| ${PORT} | $(( ${PORT} + 1 )) |" ${FILE}
	fi
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
	pivpnNET=$(grep -m 1 "^server" ${FILE} | awk '{print $2}') 
	pivpnPORT=$(grep -m 1 "^port" ${FILE} | awk '{print $2}') 
	nft add rule inet ${TABLE} input_wan ${pivpnPROTO,,} dport ${pivpnPORT} accept comment \"${TXT}\"
	nft add rule inet ${TABLE} forward iifname ${pivpnDEV,,} oifname @DEV_WAN ip saddr ${pivpnNET}/${subnetClass} accept comment \"${TXT}\"
	nft insert rule inet ${TABLE} nat_postrouting oifname @DEV_WAN ip saddr ${pivpnNET}/${subnetClass} masquerade comment \"${TXT}\"
fi
