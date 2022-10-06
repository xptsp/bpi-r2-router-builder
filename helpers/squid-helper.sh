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
if [[ $? -ne 0 ]]; then echo "ERROR: Failed to generate ${SQUID_CERT/pem/key}"; exit 1; fi

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
if [[ $? -ne 0 ]]; then echo "ERROR: Failed to generate ${SQUID_CERT}"; exit 2; fi

# Generate the DER file used from the PEM file: 
openssl x509 -in ${SQUID_CERT} -outform DER -out ${SQUID_CERT/pem/der}
if [[ $? -ne 0 ]]; then echo "ERROR: Failed to generate ${SQUID_CERT/pem/der}"; exit 3; fi

# Download a randomly selected 2048-bit dhparam file.  If that fails, generate one! 
# This takes long time, though.  If it still fails, abort with error message! 
wget -q -O ${DHPARAM} https://2ton.com.au/getprimes/random/dhparam/2048
[[ $? -ne 0 ]] && openssl dhparam -outform PEM -out ${DHPARAM} 2048
if [[ $? -ne 0 ]]; then echo "ERROR: Failed to generate ${DHPARAM}"; exit 4; fi

# Change ownership and visibility of generated certificates:
chown -R proxy:proxy ${SQUID_CERT_DIR}
chmod 0400 ${SQUID_CERT}*

# Add squid_proxyCA cert to system so it's trusted by default:
mkdir -p ${CA_DIR}
test -f ${CA_CERT} && rm ${CA_CERT}
openssl x509 -inform PEM -in ${SQUID_CERT} -out ${CA_CERT}
if [[ $? -ne 0 ]]; then echo "ERROR: Failed to generate ${CA_CERT}"; exit 5; fi
update-ca-certificates
