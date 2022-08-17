#!/bin/bash
#############################################################################
# This helper script modifies the OpenVPN certificate so that it will
# properly work with our operating system.  Otherwise, starting OpenVPN
# services tends to block all internet access...  (Don't understand why)
#############################################################################

####################################################################
# If certificate doesn't exit, exit with code 1:
####################################################################
CERT=/etc/openvpn/${1}.conf
test -f ${CERT} || exit 1

####################################################################
# If certificate modifications are already done, exit with code 0:
####################################################################
grep -q "route-noexec" ${CERT} && exit 0

####################################################################
# Modify the client certificate for a split-tunnel VPN:
####################################################################
sed -i "/^(auth-user-pass|script-security|block-outside-dns)/d" ${CERT}
sed -i "s|^;comp-lzo|comp-lzo|g" ${CERT}
cat << EOF >> ${CERT}

#user authorization stuff:
auth-user-pass /etc/openvpn/.creds_$1
auth-nocache
route-noexec

#up and down scripts to be executed when VPN starts or stops
script-security 2
up /etc/openvpn/vpn-up.sh
down /etc/openvpn/vpn-down.sh
down-pre

# prevent DNS leakage
dhcp-option DOMAIN-ROUTE .
EOF
exit 0
