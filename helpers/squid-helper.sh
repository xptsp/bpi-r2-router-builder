#!/bin/bash
#############################################################################
# This helper script takes care of any tasks that should occur before the
# squid service officially starts.  Tasks that occur here should not take
# very long to execute and should not rely on other services being up
# and running.
#############################################################################
# Variables for our script:
SQUID_CERT_DIR=/etc/squid/cert
SQUID_CERT=${SQUID_CERT_DIR}/ca.pem
DHPARAM=${SQUID_CERT_DIR}/dhparam.pem
CA_DIR=/usr/local/share/ca-certificates
CA_CERT=${CA_DIR}/squid_proxyCA.crt
TXT="transparent-squid"

# Load router settings:
test -f /etc/default/router-settings && source /etc/default/router-settings

#############################################################################
# ACTION: init  => Initialize certificates and dhparam for squid:
#############################################################################
if [[ "$1" == "init" ]]; then
	# Return with error code 0 if this process is already done:
	test -f ${SQUID_CERT/pem/key} && test -f ${SQUID_CERT} && test -f ${SQUID_CERT/pem/der} && test -f ${DHPARAM} && test -f ${CA_CERT} && exit 0

	# We need to be root in order to execute everything after this:
	if [[ "${UID}" -ne 0 ]]; then
		sudo $0 $@
		exit $?
	fi

	# Create the openssl configuration file if it doesn't already exist:
	mkdir -p ${SQUID_CERT_DIR}
	test -f ${SQUID_CERT} && rm ${SQUID_CERT}

	# Generate a 2048-bit self-signed certificate key:
	openssl genrsa -out ${SQUID_CERT/pem/key} 2048
	[[ $? -ne 0 ]] && echo "ERROR: Failed to generate ${SQUID_CERT/pem/key}" && exit 1

	# Generate configuration file to use with openssl.  If we couldn't get information about
	# our IP, list the requested information is "Unspecified" and the country as "US":
	CONFIG=${SQUID_CERT_DIR}/openssl.config
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

	# Generate the certificate PEM file:
	openssl req -x509 -new -nodes -sha256 -days 3650 -key ${SQUID_CERT/pem/key} -out ${SQUID_CERT}  -config ${CONFIG}
	[[ $? -ne 0 ]] && echo "ERROR: Failed to generate ${SQUID_CERT}" && exit 2

	# Generate the DER file used from the PEM file:
	openssl x509 -in ${SQUID_CERT} -outform DER -out ${SQUID_CERT/pem/der}
	[[ $? -ne 0 ]] && echo "ERROR: Failed to generate ${SQUID_CERT/pem/der}" && exit 3

	# Download a randomly selected 2048-bit dhparam file.  If that fails, generate one!
	# This takes long time, though.  If it still fails, abort with error message!
	wget -q -O ${DHPARAM} https://2ton.com.au/getprimes/random/dhparam/2048
	[[ $? -ne 0 ]] && openssl dhparam -outform PEM -out ${DHPARAM} 2048 &

	# Change ownership and visibility of generated certificates:
	chown -R proxy:proxy ${SQUID_CERT_DIR}
	chmod 0400 ${SQUID_CERT}*

	# Add squid_proxyCA cert to system so it's trusted by default:
	mkdir -p ${CA_DIR}
	test -f ${CA_CERT} && rm ${CA_CERT}
	openssl x509 -inform PEM -in ${SQUID_CERT} -out ${CA_CERT}
	[[ $? -ne 0 ]] && echo "ERROR: Failed to generate ${CA_CERT}" && exit 4
	update-ca-certificates

#############################################################################
# ACTION: start  => Add firewall rules for transparent HTTP/HTTPS:
#############################################################################
elif [[ "$1" == "start" ]]; then
	# If no transparent proxying is requested, exit with code 0:
	[[ "${proxy_http}" != "Y" && "${proxy_https}" != "Y" ]] && exit 0

	# Determine what localhost ports are configured.  If none, exit with code 0:
	HTTP=($(cat /etc/squid/squid.conf | grep http_port | grep "127.0.0.1" | grep -v " ssl-bump " | awk '{print $2}'))
	HTTPS=($(cat /etc/squid/squid.conf | grep http_port | grep "127.0.0.1" | grep " ssl-bump " | awk '{print $2}'))
	PORT=$(echo ${HTTPS:-"${HTTP}"} | cut -d: -f 2)
	[[ -z "${PORT}" ]] && exit 0

	# Wait up to 30 seconds for service to have bound to the localhost ports.  
	# Exit with code 1 if specified ports aren't bound in 30 seconds!
	COUNT=30
	while ! ss -H -t -l -n sport = :${PORT} | grep "^LISTEN.*${HTTPS}"; do
		COUNT=$(( COUNT - 1 ))
		[[ ${COUNT} -eq 0 ]] && exit 1
		sleep 1
	done

	# Enable transparent HTTP (port 80) proxy if configured & requested:
	if [[ ! -z "${HTTP}" && "${proxy_https:-"N"}" == "Y" ]]; then
		nft add rule inet ${TABLE} nat_prerouting ip saddr != ${IP} ip daddr != ${IP} udp dport 80 counter dnat to ${HTTP} comment \"${TXT}\"
		nft add rule inet ${TABLE} nat_prerouting ip saddr != ${IP} ip daddr != ${IP} tcp dport 80 counter dnat to ${HTTP} comment \"${TXT}\"
	fi

	# Enable transparent HTTPS (port 443) proxy if configured & requested:
	if [[ ! -z "${HTTPS}" && "${proxy_http:-"N"}" == "Y" ]]; then
		nft add rule inet ${TABLE} nat_prerouting ip saddr != ${IP} ip daddr != ${IP} udp dport 443 counter dnat to ${HTTPS} comment \"${TXT}\"
		nft add rule inet ${TABLE} nat_prerouting ip saddr != ${IP} ip daddr != ${IP} tcp dport 443 counter dnat to ${HTTPS} comment \"${TXT}\"
	fi

#############################################################################
# ACTION: stop  => Remove firewall rules for transparent HTTP/HTTPS:
#############################################################################
elif [[ "$1" == "stop" ]]; then
	# Remove any transparent HTTP/HTTPS rules:
	nft -a list chain inet ${TABLE} nat_prerouting | grep "${TXT}" | awk '{print $NF}' | while read HANDLE; do
		[[ "${HANDLE}" -gt 0 ]] 2> /dev/null && nft delete rule inet ${TABLE} nat_prerouting handle ${HANDLE}
	done
fi

#############################################################################
# Exit with error code 0:
#############################################################################
exit 0
