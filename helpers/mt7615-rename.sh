#!/bin/bash

# Determine new default WiFi password:
[[ -f /boot/wifi.conf ]] && source /boot/wifi.conf
[[ -z "${WIFI_PASS}" ]] && WIFI_PASS=$(php /opt/bpi-r2-router-builder/router/includes/ajax-newpass.php)
if [[ ! -f /boot/wifi.conf ]]; then
	mount -o remount,rw /boot
	echo "WIFI_PASS=${WIFI_PASS}" > /boot/wifi.conf
	mount -o remount,ro /boot
fi

# Start renaming interfaces and launching hostapd APs:
MT=($(lspci | grep MEDIATEK | grep -v bridge))
PCI=${MT[0]}
MDL=${MT[-1]}
if [[ ! -z "${PCI}" ]]; then
	cd /sys/class/net
	IFACES=($(ls -l | grep "${PCI}" | awk '{print $9}' | grep -v "^mt${MDL}_"))
	for IFACE in ${IFACES[@]}; do
		# Is this the interface with the DBDC setting?  YES -> "mt7615_24g".  NO -> "mt7615_5g".
		NEW=mt${MDL}_24g
		[[ ! -f /sys/kernel/debug/ieee80211/$(basename $(ls -l ${IFACE}/phy80211 | awk '{print $NF}'))/mt76/dbdc ]] && NEW=mt${MDL}_5g

		# We need the IP address from the network interfaces file:
		IP=$(cat /etc/network/interfaces.d/${NEW} 2> /dev/null | grep address | awk '{print $2}')

		# Rename the interface, turn off power save mode, and set new IP address:
		ip link set ${IFACE} down
		ip addr flush dev ${IFACE}
		ip link set ${IFACE} name ${NEW}
		iw dev ${NEW} set power_save off
		ip link set ${NEW} up
		ifconfig ${NEW} ${IP}/24

		# Change interface's password if it is "bananapi", and launch hostapd AP on that interface:
		if [[ -f /etc/hostapd/${NEW}.conf ]]; then
			if [[ "$(cat /etc/hostapd/${NEW}.conf | grep wpa_passphrase | cut -d"=" -f 2)" == "bananapi" ]]; then
				sed -i "s|wpa_passphrase=.*|wpa_passphrase=${WIFI_PASS}|g" /etc/hostapd/${NEW}.conf
			fi
			systemctl start hostapd@${NEW}
		fi
	done
fi
exit 0
