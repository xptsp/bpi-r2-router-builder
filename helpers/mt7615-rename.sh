#!/bin/bash

MT=($(lspci | grep MEDIATEK | grep -v bridge))
PCI=${MT[0]}
MDL=${MT[-1]}
if [[ ! -z "${PCI}" ]]; then
	cd /sys/class/net
	IFACES=($(ls -l | grep "${PCI}" | awk '{print $9}' | grep -v "^mt${MDL}_"))
	for IFACE in ${IFACES[@]}; do
		pushd ${IFACE}
		NEW=mt${MDL}_24g
		IP=192.168.10.1
		if [[ ! -f /sys/kernel/debug/ieee80211/$(basename $(ls -l phy80211 | awk '{print $NF}'))/mt76/dbdc ]]; then
			NEW=mt${MDL}_5g
			IP=192.168.20.1
		fi
		popd
		ip link set ${IFACE} down
		ip addr flush dev ${IFACE}
		ip link set ${IFACE} name ${NEW}
		iw dev ${NEW} set power_save off
		iw dev ${NEW} set txpower fixed 2500
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
[[ -f /usr/local/bin/pihole ]] && pihole restartdns
exit 0
