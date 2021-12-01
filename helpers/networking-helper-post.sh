#!/bin/bash
#############################################################################
# This helper script attempts to renames the MediaTek WiFi devices to have a
# consistent naming scheme, such as "mt7615_24g" and "mt7615_5g".  Also 
# brings up the WiFi network interfaces with the proper IP address and 
# starts hostapd on that interface if a configuration file is available.
#############################################################################

# Determine new default WiFi password and change it in all hostapd configuration files:
[[ -f /boot/wifi.conf ]] && source /boot/wifi.conf
[[ -z "${WIFI_PASS}" ]] && WIFI_PASS=$(php /opt/bpi-r2-router-builder/router/includes/ajax-newpass.php)
if [[ ! -f /boot/wifi.conf ]]; then
	mount -o remount,rw /boot
	echo "WIFI_PASS=${WIFI_PASS}" > /boot/wifi.conf
	mount -o remount,ro /boot
fi
sed -i "s|wpa_passphrase=bananapi|wpa_passphrase=${WIFI_PASS}|g" /etc/hostapd/*.conf

# Rename wireless interfaces according to their physical index number.
# Ex: Wireless interface with physical index number 1 would be named "wradio1".
cd /sys/class/net
for DIR in $(iw dev | grep "Interface" | awk '{print $2}'); do
	if [[ -f ${DIR}/phy80211/index ]]; then
		IFACE=$(basename $DIR)
		INDEX=$(cat ${DIR}/phy80211/index)

		# If DNSMASQ configuration exists for this interface, get the IP address assigned.
		# Otherwise, create a default DNSMASQ configuration for the interface:
		FILE=/etc/dnsmasq.d/${IFACE}.conf
		if [[ ! -f ${FILE} ]]; then
			IP_ADDR=192.168.$((20 + ${INDEX} ))
			echo "interface=${IFACE}" > ${FILE}
			echo "dhcp-range=${IFACE},${IP_ADDR}.100,${IP_ADDR}.150,255.255.255.0,48h" >> ${FILE}
			echo "dhcp-option=${IFACE},3,${IP_ADDR}.1" >> ${FILE}
			IP_ADDR=${IP_ADDR}.1
		else
			IP_ADDR=$(cat ${FILE} | grep dhcp-option | cut -d, -f 3)
		fi

		# Rename the interface and bring the interface up:
		ip link set ${IFACE} up
		ip addr add ${IP_ADDR}/24 dev ${IFACE}

		# Launch hostapd AP on that interface if a configuration file exists:
		[[ -f /etc/hostapd/${IFACE}.conf ]] && systemctl start hostapd@${IFACE}
	fi
done
exit 0
