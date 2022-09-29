#!/bin/bash
#############################################################################
# This helper script takes care of any tasks that should occur before the
# squid service officially starts.  Tasks that occur here should not take
# very long to execute and should not rely on other services being up
# and running.
#############################################################################
# Variables for our script:
CERT_DIR=/etc/squid/cert
CERT=${CERT_DIR}/squid_proxyCA.pem
CA_DIR=/usr/local/share/ca-certificates
CA_CERT=${CA_DIR}/squid_proxyCA.crt

# Return with error code 0 if this process is already done:
test -f ${CERT} && test -d ${CA_CERT} && exit 0

# We need to be root in order to execute everything after this:
if [[ "${UID}" -ne 0 ]]; then
	sudo $0 $@
	exit $?
fi

# Create the openssl configuration file if it doesn't already exist:
rm -rf ${CERT_DIR}
mkdir -p ${CERT_DIR}
CONFIG=${CERT_DIR}/openssl.config
if [[ ! -f ${CONFIG} ]]; then
	# Try to get information from ipinfo.io about our IP address:
	IPINFO=/tmp/ipinfo.tmp
	wget -q http://ipinfo.io -O ${IPINFO}
	STATE=$(grep "region" ${IPINFO} | cut -d\" -f 4)
	CITY=$(grep "city" ${IPINFO} | cut -d\" -f 4)
	COUNTRY=$(grep "country" ${IPINFO} | cut -d\" -f 4)
	rm ${IPINFO}

	# Generate configuration file to use with openssl.  If we couldn't get information about
	# our IP, list the requested information is "Unspecified" and the country as "US":
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
	echo "organizationName    = BPiWRT"
	) > ${CONFIG}
fi

# Generate self-signed CA certificate/key, both in the same file:
openssl req -new -newkey rsa:4096 -sha256 -days 3650 -nodes -x509 -keyout ${CERT} -out ${CERT} -config ${CONFIG}
if [[ $? -ne 0 ]]; then
	echo "ERROR: Failed to generate ${CERT}"
	exit 1
fi
chown -R proxy:proxy ${CERT_DIR}
chmod 0400 ${CERT}*

# Add squid_proxyCA cert to system so it's trusted by default:
rm -rf ${CA_DIR}
mkdir -p ${CA_DIR}
openssl x509 -inform PEM -in ${CERT} -out ${CA_CERT}
if [[ $? -ne 0 ]]; then
	echo "ERROR: Failed to generate ${CA_CERT}"
	exit 2
fi
update-ca-certificates

# Configure squid to generate certs on the fly:
/usr/lib/squid/security_file_certgen -c -s /var/spool/squid/ssl_db -M 4MB
chown -R proxy:proxy /var/spool/squid
