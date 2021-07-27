#!/bin/bash

PCI=$(lspci | grep MEDIATEK | grep 7615 | cut -d" " -f 1)
if [[ ! -z "${PCI}" ]]; then
	cd /sys/class/net
	IFACES=($(ls -l | grep "${PCI}" | awk '{print $9}' | grep -v "^mt7615_"))
	for IFACE in ${IFACES[@]}; do
		NEW=mt7615_24g && IP=192.168.10.1
		[[ "${IFACE}" == "rename"* ]] && NEW=mt7615_5g && IP=192.168.20.1
		ip link set ${IFACE} down
		ip addr flush dev ${IFACE} 
		ip link set ${IFACE} name ${NEW}
		ip link set ${NEW} up
		ip addr add ${IP}/24 dev ${NEW}
		if [[ -f /etc/hostapd/${NEW}.conf ]]; then
			if [[ "$(cat /etc/hostapd/${NEW}.conf | grep wpa_passphrase | cut -d"=" -f 2)" == "bananapi" ]]; then
				if [[ -f /boot/wifi.conf ]]; then
					source /boot/wifi.conf
				else
					PASS=$(curl http://localhost/ajax/newpass)
					mount -o remount,rw /boot
					echo "PASS=$PASS" > /boot/wifi.conf
					mount -o remount,ro /boot
				fi
				sed -i "s|wpa_passphrase=.*|wpa_passphrase=${PASS}|g" /etc/hostapd/${NEW}.conf
			fi			
			systemctl start hostapd@${NEW}
		fi
	done
fi
pihole restartdns
exit 0
