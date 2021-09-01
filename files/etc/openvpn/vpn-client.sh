#!/bin/bash
FILE=/etc/openvpn/${1:-"client"}.conf

############################################################################
# If client openvpn configuration doesn't exist, abort with error code 1:
############################################################################
[[ ! -f ${FILE} ]] && exit 1

############################################################################
# If we found the phrase "auth-user-pass", return error code 0:
############################################################################
grep auth-user-pass ${FILE} >& /dev/null && exit 0

############################################################################
# Modify the OpenVPN ovpn configuration file to set up split tunnel VPN:
############################################################################
cp ${FILE} /tmp/vpn.conf
egrep -v "(auth-user-pass|script-security|block-outside-dns)" ${FILE} | tee ${FILE}
sed -i "s|;comp-lzo|comp-lzo|g" ${FILE}
sed -i "s|dev tun|dev vpn_out\ndev-type tun|g" ${FILE}
cat << EOF >> ${FILE}

#user authorization stuff:
auth-user-pass /etc/openvpn/.vpn_creds
auth-nocache
route-noexec

#up and down scripts to be executed when VPN starts or stops
script-security 2
up /opt/modify_ubuntu_kit/files/vpn_iptables.sh
down /etc/openvpn/update-systemd-resolved
down-pre

# prevent DNS leakage
dhcp-option DOMAIN-ROUTE .
EOF
fi
exit 0

