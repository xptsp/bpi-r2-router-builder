#!/bin/bash
#############################################################################
# This helper script takes care of any tasks that should occur before the 
# nginx service officially starts.  Tasks that occur here should not
# take very long to execute and should not rely on other services being up
# and running.
#############################################################################
CONFIG=/etc/nginx/openssl.config
KEY_FILE=/etc/ssl/private/nginx-selfsigned.key
CRT_FILE=/etc/ssl/certs/nginx-selfsigned.crt
DHPARAM=/etc/nginx/dhparam.pem

#############################################################################
# Generate configuration file to use with openssl.  If we couldn't get information about
# our IP, list the requested information is "Unspecified" and the country as "US":
#############################################################################
if [[ ! -f ${CONFIG} ]]; then
	# Try to get information from ipinfo.io about our IP address:
	IPINFO=/tmp/ipinfo.tmp
	wget -q http://ipinfo.io -O ${IPINFO}
	STATE=$(grep "region" ${IPINFO} | cut -d\" -f 4)
	CITY=$(grep "city" ${IPINFO} | cut -d\" -f 4)
	COUNTRY=$(grep "country" ${IPINFO} | cut -d\" -f 4)
	test -f ${IPINFO} && rm ${IPINFO}

	(
	echo "[req]"
	echo "default_bit         = 4096"
	echo "distinguished_name  = req_distinguished_name"
	echo "prompt              = no"
	echo
	echo "[req_distinguished_name]"
	echo "countryName         = ${COUNTRY:-"US"}"
	echo "stateOrProvinceName = ${STATE:-"Unspecified"}"
	echo "localityName        = ${CITY:-"Unspecified"}"
	echo "organizationName    = Self-signed CA"
	) > ${CONFIG}
fi

#############################################################################
# Generate the ngnix certificate and key files:
#############################################################################
if [[ ! -f ${KEY_FILE} || ! -f ${CRT_FILE} ]]; then
	openssl req -x509 -nodes -days 365 -newkey rsa:2048 -keyout ${KEY_FILE} -out ${CRT_FILE} -config ${CONFIG}
	[[ $? -ne 0 ]] && echo "ERROR: Failed to generate ${CRT_FILE}!" && exit 2
	update-ca-certificates
fi

#############################################################################
# Download a randomly selected 2048-bit dhparam file.  If that fails, generate one! 
# This takes long time, though.  If it still fails, abort with error message! 
#############################################################################
if [[ ! -f ${DHPARAM} ]]; then
	wget -q -O ${DHPARAM} https://2ton.com.au/getprimes/random/dhparam/2048
	if [[ $? -ne 0 ]]; then
		openssl dhparam -outform PEM -out ${DHPARAM} 2048
		[[ $? -ne 0 ]] && echo "ERROR: Failed to generate ${DHPARAM}" && exit 4
	fi
fi

#############################################################################
# Update webserver addresses to match IP address of interface "br0":
#############################################################################
cd /etc/nginx/sites-available
NEW_IP=$(cat /etc/network/interfaces.d/br0 | grep address | awk '{print $2}')
if [[ ! -z "${NEW_IP}" ]]; then
	for FILE in $(ls | egrep -ve "(default|pihole)"); do
		OLD_IP=$(cat ${FILE} | grep listen | head -1 | awk '{print $2}' | cut -d: -f 1)
		[[ "${NEW_IP}" != "${OLD_IP}" ]] && sed -i "s|${OLD_IP}|${NEW_IP}|g" ${FILE}
	done
fi

#############################################################################
# Change IP address that PiHole admin server is assigned to:  
#############################################################################
SECOND=$(ifconfig br0:1 | grep -m 1 "inet " | awk '{print $2}')
[[ ! -z "${SECOND}" ]] && NEW_IP=${SECOND}
OLD_IP=$(cat pihole | grep -m 1 "listen" | awk '{print $2}' | cut -d: -f 1)
[[ "${NEW_IP}" != "${OLD_IP}" ]] && sed -i "s|${OLD_IP}|${NEW_IP}|g" pihole

#############################################################################
# We are done rewrite the configuration files.  If requesting a reload, then
# reload the service as originally requested:
#############################################################################
[[ "$1" == "reload" ]] && /usr/sbin/nginx -g 'daemon on; master_process on;' -s reload

#############################################################################
# Return error code 0 to caller:
exit 0
