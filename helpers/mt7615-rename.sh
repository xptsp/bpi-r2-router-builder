#!/bin/bash

MT=($(lspci | grep MEDIATEK | grep -v bridge))
PCI=${MT[0]}
MDL=${MT[-1]}
if [[ ! -z "${PCI}" ]]; then
	cd /sys/class/net
	IFACES=($(ls -l | grep "${PCI}" | awk '{print $9}' | grep -v "^mt${MDL}_"))
	for IFACE in ${IFACES[@]}; do
		NEW=mt${MDL}_24g
		[[ ! -f /sys/kernel/debug/ieee80211/$(basename $(ls -l ${IFACE}/phy80211 | awk '{print $NF}'))/mt76/dbdc ]] && NEW=mt${MDL}_5g
		ip link set ${IFACE} down
		ip addr flush dev ${IFACE}
		ip link set ${IFACE} name ${NEW}
		iw dev ${NEW} set power_save off
		ip link set ${NEW} up
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
exit 0
