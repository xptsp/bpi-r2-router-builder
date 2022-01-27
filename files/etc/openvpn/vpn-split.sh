#!/bin/bash

####################################################################
# Abort with error code 1 if  client certificate doesn't exist:
####################################################################
CERT=/etc/openvpn/client.conf
test -f ${CERT} && exit 1 

####################################################################
# Exit with error code 0 if  split-tunnel certificate exists:
####################################################################
SPLIT=/etc/openvpn/split.conf
test -f ${SPLIT} && exit 0

####################################################################
# Modify the client certificate for a split-tunnel VPN:
####################################################################
cp ${CERT} ${SPLIT}
sed -i "/^(auth-user-pass|script-security|block-outside-dns)/d" ${SPLIT}
sed -i "s|;comp-lzo|comp-lzo|g" ${SPLIT}
sed -i "s|dev tun|dev vpn_out\ndev-type tun|g" ${SPLIT}
cat << EOF >> ${SPLIT}

#user authorization stuff:
auth-user-pass /etc/openvpn/.vpn_creds
auth-nocache
route-noexec

#up and down scripts to be executed when VPN starts or stops
script-security 2
up /etc/openvpn/vpn-split.sh
down /etc/openvpn/vpn-down.sh
down-pre

# prevent DNS leakage
dhcp-option DOMAIN-ROUTE .
EOF
fi
exit 0