#!/bin/bash

FILE=/etc/udev/rules.d/70-persistent-net.rules
PCI=$(lspci | grep MEDIATEK | grep 7615 | cut -d" " -f 1)
if [[ ! -z "${PCI}" ]]; then
	cd /sys/class/net
	IFACES=($(ls -l | grep "${PCI}" | awk '{print $9}' | grep -v "^mt_"))
	for IFACE in ${IFACES[@]}; do
		# Change MAC address:
		MAC=$(ifconfig ${IFACE} | grep ether | awk '{print $2}')
		NEW=wlan_24g
		[[ "${IFACE}" == "rename"* ]] && NEW=wlan_5g
		if ! cat ${FILE} | grep ${NEW}; then
			if ! cat /etc/hostapd/*.conf | grep "^bss=" | grep ${IFACE} >& /dev/null; then
				echo "SUBSYSTEM==\"net\", DRIVERS==\"?*\", ATTR{address}==\"${MAC}\", NAME=\"${NEW}\"" >> $FILE
				ifconfig ${IFACE} down
				ip link set ${IFACE} name ${NEW}
				ifconfig ${NEW} up
			fi
		fi
	done
fi
exit 0
