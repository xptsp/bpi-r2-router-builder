#!/bin/bash

##############################################################################
# Function dealing with IP addresses
##############################################################################
check_ip()
{
	[[ "$(grep -m 1 "${1}$" ${FILE} | awk '{print $1}')" != "$2" ]] && sed -i "s|.*${1}$|$2 $1|g" ${FILE}
}	

##############################################################################
# Figure out what the IP addresses for interface "br0" are:
##############################################################################
IP=($(ip addr show br0 | grep "inet " | awk '{print $2}' | cut -d/ -f 1))
IP1=${IP[0]}
IP2=${IP[1]:-"${IP[0]}"}

##############################################################################
# If WPAD URL is incorrect, fix the URL:  
##############################################################################
FILE=/etc/dnsmasq.d/wpad.conf
OLD=$(grep "dhcp-option" ${FILE} | cut -d\" -f 2 | cut -d/ -f 3) 
[[ "${OLD}" != "${IP1}" ]] && sed -i "s|${OLD}|${IP1}|" ${FILE}

##############################################################################
# Change mapped IP addresses for "bpiwrt" and "bpiwrt.local":
##############################################################################
FILE=/etc/pihole/custom.list
check_ip bpiwrt ${IP1}
check_ip bpiwrt.local ${IP1}

##############################################################################
# Change mapped IP addresses for "wpad" and "wpad.local":
##############################################################################
check_ip wpad ${IP1}
check_ip wpad.local ${IP1}

##############################################################################
# Change mapped IP addresses for "pi.hole" and "pihole.local":
##############################################################################
check_ip pi.hole ${IP2}
check_ip pihole.local ${IP2}

##############################################################################
# If IP address in "pihole-FTL.conf" is incorrect, fix the URL:  
##############################################################################
FILE=/etc/pihole/pihole-FTL.conf
[[ "$(grep "LOCAL_IPV4=" ${FILE} | cut -d= -f 2)" != "${IP2}" ]] && sed -i "s|LOCAL_IPV4=.*|LOCAL_IPV4=${IP2}|" ${FILE}

##############################################################################
# If IP address in "setupVars.conf" is incorrect, fix the URL:  
##############################################################################
FILE=/etc/pihole/setupVars.conf
[[ "$(grep "LOCAL_IPV4=" ${FILE} | cut -d= -f 2)" != "${IP2}" ]] && sed -i "s|LOCAL_IPV4=.*|LOCAL_IPV4=${IP2}|" ${FILE}

##############################################################################
# Return error code 0:
##############################################################################
exit 0
`
