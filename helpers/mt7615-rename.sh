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
		[[ -f /etc/hostapd/${NEW}.conf ]] && systemctl start hostapd@${NEW}
	done
fi
pihole restartdns
exit 0
