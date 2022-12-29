#!/bin/bash

##############################################################################
# Function dealing with IP addresses
##############################################################################
check_ip()
{
	[[ "$(grep -m 1 " ${2}$" ${FILE} | awk '{print $1}')" != "$1" ]] && sed -i "s|.* ${2}$|$1 $2|g" ${FILE}
}	

##############################################################################
# Figure out what the IP addresses for the Pi-Hole interface are:
##############################################################################
source /etc/pihole/setupVars.conf
IFACE=${PIHOLE_INTERFACE:-"br0"}
test -d /sys/class/net/${IFACE} || exit 0
IP=($(ip addr show ${IFACE} | grep "inet " | awk '{print $2}' | cut -d/ -f 1))
[[ -z "${IP[0]}" ]] && exit 0
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
HOST=$(hostname)
check_ip ${IP1} ${HOST} 
check_ip ${IP1} ${HOST}.local

##############################################################################
# Change mapped IP addresses for "wpad" and "wpad.local":
##############################################################################
check_ip ${IP1} wpad
check_ip ${IP1} wpad.local

##############################################################################
# Change mapped IP addresses for "pi.hole" and "pihole.local":
##############################################################################
check_ip ${IP2} pi.hole
check_ip ${IP2} pihole.local

##############################################################################
# If IP address in "pihole-FTL.conf" is incorrect, fix the URL:  
##############################################################################
FILE=/etc/pihole/pihole-FTL.conf
[[ "$(grep "LOCAL_IPV4=" ${FILE} | cut -d= -f 2)" != "${IP2}" ]] && sed -i "s|LOCAL_IPV4=.*|LOCAL_IPV4=${IP2}|" ${FILE}

##############################################################################
# If IP address in "setupVars.conf" is incorrect, fix the URL:  
##############################################################################
FILE=/etc/pihole/setupVars.conf
[[ "$(grep "IPV4_ADDRESS=" ${FILE} | cut -d= -f 2)" != "${IP2}" ]] && sed -i "s|IPV4_ADDRESS=.*|LOCAL_IPV4=${IP2}|" ${FILE}

##############################################################################
# Return error code 0:
##############################################################################
exit 0
