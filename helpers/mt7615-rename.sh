#!/bin/bash

PCI=$(lspci | grep MEDIATEK | grep 7615 | cut -d" " -f 1)
if [[ ! -z "${PCI}" ]]; then
	cd /sys/class/net
	IFACES=($(ls -l | grep "${PCI}" | awk '{print $9}' | grep -v "^mt7615_"))
	for IFACE in ${IFACES[@]}; do
		# Change MAC address:
		MAC=$(ifconfig ${IFACE} | grep ether | awk '{print $2}')
		NEW=mt7615_24g
		[[ "${IFACE}" == "rename"* ]] && NEW=mt7615_5g
		ifconfig ${IFACE} down
		ip link set ${IFACE} name ${NEW}
		ifconfig ${NEW} up
		systemctl start hostapd@${NEW}
	done
fi
exit 0
