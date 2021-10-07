#!/bin/bash
#############################################################################
# This helper script attempts to renames the MediaTek WiFi devices to have a
# consistent naming scheme, such as "mt7615_24g" and "mt7615_5g".  Also 
# brings up the WiFi network interfaces with the proper IP address and 
# starts hostapd on that interface if a configuration file is available.
#############################################################################

# Determine new default WiFi password:
[[ -f /boot/wifi.conf ]] && source /boot/wifi.conf
[[ -z "${WIFI_PASS}" ]] && WIFI_PASS=$(php /opt/bpi-r2-router-builder/router/includes/ajax-newpass.php)
if [[ ! -f /boot/wifi.conf ]]; then
	mount -o remount,rw /boot
	echo "WIFI_PASS=${WIFI_PASS}" > /boot/wifi.conf
	mount -o remount,ro /boot
fi

# Start renaming interfaces and launching hostapd APs:
unset RESTART_DNS
cd /sys/class/net
for MT in ($(lspci | grep MEDIATEK | grep -v bridge | awk '{print $1"="$NF}')); do
	PCI=$(echo $MT | cut -d"=" -f 1)
	MDL=$(echo $MT | cut -d"=" -f 2)
	if [[ ! -z "${PCI}" ]]; then
		IFACES=($(ls -l | grep "${PCI}" | awk '{print $9}' | grep -v "^mt${MDL}_"))
		for IFACE in ${IFACES[@]}; do
			# Is this the interface with the DBDC setting?  YES -> "mt7615_24g".  NO -> "mt7615_5g".
			NEW=mt${MDL}_24g
			[[ ! -f /sys/kernel/debug/ieee80211/$(basename $(ls -l ${IFACE}/phy80211 | awk '{print $NF}'))/mt76/dbdc ]] && NEW=mt${MDL}_5g

			# Change interface's password if it is "bananapi", and launch hostapd AP on that interface:
			if [[ -f /etc/hostapd/${NEW}.conf ]]; then
				if [[ "$(cat /etc/hostapd/${NEW}.conf | grep wpa_passphrase | cut -d"=" -f 2)" == "bananapi" ]]; then
					sed -i "s|wpa_passphrase=.*|wpa_passphrase=${WIFI_PASS}|g" /etc/hostapd/${NEW}.conf
				fi
			fi

			# Add udev rule to automatically change the interface name to the new name:
			MAC=$(cat ${IFACE}/address)
			sed -i "/${MAC}/d" /etc/udev/rules.d/70-persistent-net.rules
			echo "SUBSYSTEM==\"net\", DRIVERS==\"?*\", ATTR{address}==\"${MAC}\", NAME=\"${NEW}\"" >> /etc/udev/rules.d/70-persistent-net.rules

			# Create network interface configuration:
			IP=($(cat /etc/network/interfaces.d/* | grep address | awk '{print $2}' | sort -r | head -1 | sed "s|\.| |g"))
			OCT=$(expr ${IP[2]} + 1)
			ADDR=${IP[0]}.${IP[1]}.${OCT}
			cat << EOF > /etc/network/interfaces.d/${NEW}
auto ${NEW}
iface ${NEW} inet static
	address ${ADDR}.1
	netmask 255.255.255.0
EOF

			# Create configuration file for DNSMASQ (aka Pi-Hole):
			cat << EOF > /etc/dnsmasq.d/${NEW}.conf
interface=${NEW}
dhcp-range=${NEW},${ADDR}.100,${ADDR}.150,255.255.255.0,48h
dhcp-option=${NEW},3,${ADDR}.1
EOF
			RESTART_DNS=true

			# Rename the interface and bring the interface up:
			ip link set ${IFACE} down
			ip link set ${IFACE} name ${NEW}
			ifup ${IFACE}
		done
	fi
done

# Restart DNSMASQ or Pi-Hole if required:
[[ ! -z "${RESTART_DNS}" ]] && systemctl restart $([[ -e /etc/systemd/system/dnsmasq.service ]] && echo "pihole-FTL" || echo "dnsmasq")

exit 0
