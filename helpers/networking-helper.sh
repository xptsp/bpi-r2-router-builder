#!/bin/bash
#############################################################################
# This helper script takes care of any tasks that should occur before the
# networking service officially starts.  Tasks that occur here should not
# take very long to execute and should not rely on other services being up
# and running.
#############################################################################
[[ -f /etc/default/router-settings ]] && source /etc/default/router-settings

#############################################################################
# Enable DBDC on any MT76xx wifi card that supports it:
#############################################################################
for file in /sys/kernel/debug/ieee80211/*; do
	test -e $file/mt76/dbdc && echo 1 > $file/mt76/dbdc
done

#############################################################################
# Determine new default WiFi password and change it in all hostapd configuration files:
#############################################################################
[[ -e /boot/persistent.conf ]] && source /boot/persistent.conf
if [[ -z "${WIFI_PASS}" ]]; then
	# Generate new default password and replace "bananapi" with new pass:
	WIFI_PASS=$(php /opt/bpi-r2-router-builder/router/includes/subs/newpass.php)
	sed -i "s|^wpa_passphrase=bananapi$|wpa_passphrase=${WIFI_PASS}|g" /etc/hostapd/*.conf

	# Write the new password to the persistent configuration file:
	mount -o remount,rw /boot
	[[ -f /boot/persistent.conf ]] && sed -i "/^WIFI_PASS=/d" /boot/persistent.conf
	echo "WIFI_PASS=${WIFI_PASS}" >> /boot/persistent.conf
	mount -o remount,ro /boot
fi

#############################################################################
# Use the saved MAC address from the boot partition if one is available.
# Record the current MAC address if saved MAC address isn't available.
#############################################################################
/opt/bpi-r2-router-builder/helpers/router-helper.sh mac saved

#############################################################################
# Clear the reformatting flag in "/etc/overlayRoot.conf":
#############################################################################
/opt/bpi-r2-router-builder/helpers/router-helper.sh defaults unpack

#############################################################################
# Bring the "eth0" interface up if not already up:
#############################################################################
ifconfig eth0 2> /dev/null | grep "UP," &> /dev/null || /sbin/ifup eth0 >& /dev/null
ifconfig eth1 2> /dev/null | grep "UP," &> /dev/null || /sbin/ifup eth1 >& /dev/null

#############################################################################
# Rename the WiFi interfaces on the MT76xx wifi card:
#############################################################################
cd /sys/class/net
LIST=($(ls -l | grep pcie | awk '{print $9}'))
if [[ ! -z "${LIST[@]}" ]]; then
	for IFACE in ${LIST[@]}; do
		DEV="$(lspci -s $(basename $(ls -l ${IFACE}/device | awk '{print $NF}')) | grep MEDIATEK | awk '{print $NF}')"
		if [[ ! -z "${DEV}" ]]; then
			# Rename wireless interface:
			[[ -f /sys/kernel/debug/ieee80211/$(basename $(ls -l ${IFACE}/phy80211 | awk '{print $NF}'))/mt76/dbdc ]] && POST=24g || POST=5g
			NEW=mt${DEV}_${POST}
			ip link set ${IFACE} name ${NEW}
			
			# Create secondary wireless interface on wireless cards (if possible):
			iw dev ${NEW} interface add ${NEW}_0 type managed
			REN=$(dmesg | grep ${NEW}_0 | head -1 | awk '{print $5}' | sed 's|:||g')
			[[ "$REN" != "${NEW}_0" ]] && ip link set ${REN} name ${NEW}_0
			
			# Increment last digit of MAC address of new secondary wireless interface:
			MAC=$(ip addr show ${NEW} | grep ether | awk '{print $2}')
			ip link set dev ${NEW}_0 address ${MAC:0:16}$(( (${MAC:16:1} + 1) % 10 ))			
		fi
	done
fi

#############################################################################
# Make sure all wireless interfaces are brought up:
#############################################################################
for IFACE in $(iw dev | grep Interface | awk '{print $NF}'); do
	FILE=/etc/network/interfaces.d/${IFACE}
	if ! test -f ${FILE}; then
		echo "auto ${IFACE}" > ${FILE}
		echo "allow-hotplug ${IFACE}" >> ${FILE}
		echo "iface ${IFACE} inet manual" >> ${FILE}
	fi
	iwconfig ${IFACE} txpower 30
done

#############################################################################
# Return error code 0 to the caller:
#############################################################################
exit 0
