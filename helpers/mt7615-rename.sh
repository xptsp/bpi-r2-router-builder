#!/bin/bash

PCI=$(lspci | grep MEDIATEK | grep 7615 | cut -d" " -f 1)
if [[ ! -z "${PCI}" ]]; then
	cd /sys/class/net
	IFACES=($(ls -l | grep "${PCI}" | awk '{print $9}' | grep -v "^mt7615_"))
	for IFACE in ${IFACES[@]}; do
		NEW=mt7615_24g
		IP=192.168.10.1
		if [[ "${IFACE}" == "rename"* ]]; then
			NEW=mt7615_5g
			IP=192.168.20.1
		fi
		ifconfig ${IFACE} down
		ip link set ${IFACE} name ${NEW}
		ifconfig ${NEW} up
		ifconfig ${NEW} ${IP} netmask 255.255.255.0
		systemctl start hostapd@${NEW}
	done
fi
systemctl start pihole-FTL
exit 0
