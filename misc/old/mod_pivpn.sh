#!/bin/bash

MODDED=/usr/local/src/modded_pivpn_install.sh
cp ${DIR}/auto_install/install.sh ${MODDED}
sed -i 's|setStaticIPv4(){|setStaticIPv4(){\n\treturn;|g' ${MODDED}
sed -i "/restartServices$/d" ${MODDED}
sed -i "/confLogging$/d" ${MODDED}
sed -i 's|confOVPN$|createOVPNuser|g' ${MODDED}
sed -i '/confNetwork$/d' ${MODDED}
sed -i "s|confOpenVPN(){|generateServerName(){|" ${MODDED}
sed -i "s|# Backup the openvpn folder|echo \"SERVER_NAME=\$SERVER_NAME\" >> /etc/openvpn/.server_name\n}\n\nbackupOpenVPN(){\n\t# Backup  the openvpn folder|" ${MODDED}
sed -i "s|\tif \[ -f /etc/openvpn/server.conf \]; then|}\n\nconfOpenVPN(){\n\tif [ -f /etc/openvpn/server.conf ]; then|" ${MODDED}
sed -i 's|\tcd /etc/openvpn/easy-rsa|}\n\nGenerateOpenVPN() {\n\tcd  /etc/openvpn/easy-rsa|' ${MODDED}
sed -i "s|  if ! getent passwd openvpn; then|}\n\ncreateOVPNuser(){\n  if ! getent  passwd openvpn >\& /dev/null; then|" ${MODDED}
sed -i "s|  \${SUDOE} chown \"\$debianOvpnUserGroup\" /etc/openvpn/crl.pem|}\n\ncreateServerConf(){\n\t\${SUDOE}  chown \"\$debianOvpnUserGroup\" /etc/openvpn/crl.pem|" ${MODDED}
sed -i "s|whiptail --msgbox --backtitle \"Setup OpenVPN\"|echo; #whiptail --msgbox --backtitle \"Setup OpenVPN\"|g" ${MODDED}
sed -i "s|main \"\$@\"|[[ -z \"\${SKIP_MAIN}\" ]] \&\& main \"\$@\"|g" ${MODDED}
sed -i "s|\${SUDOE} install -m 644 \"\${pivpnFilesDir}\"/files/etc/openvpn/easy-rsa/pki/ffdhe\"\${pivpnENCRYPT}\".pem pki/dh\"\${pivpnENCRYPT}\".pem|curl https://2ton.com.au/getprimes/random/dhparam/\${pivpnENCRYPT} -o pki/dh\${pivpnENCRYPT}.pem|" ${MODDED}
sed -i "s|if [ \"\$USING_UFW\" -eq 0 ]; then|if [ \"\$USING_UFW\" -eq 2 ]; then|" ${MODDED}
sed -i "s|server.conf|pivpn.conf|g" ${MODDED}
sed -i "s|pivpn.config.txt|server_config.txt|" ${MODDED}
