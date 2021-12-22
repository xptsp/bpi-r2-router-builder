#!/bin/bash
#############################################################################
# This helper script takes care of any tasks that should occur before the
# networking service officially starts.  Tasks that occur here should not
# take very long to execute and should not rely on other services being up
# and running.
#############################################################################
[[ -f /etc/default/firewall ]] && source /etc/default/firewall

#############################################################################
# Determine new default WiFi password and change it in all hostapd configuration files:
#############################################################################
[[ -e /boot/wifi.conf ]] && source /boot/wifi.conf
[[ -z "${WIFI_PASS}" ]] && WIFI_PASS=$(php /opt/bpi-r2-router-builder/router/includes/subs/newpass.php)
if [[ ! -e /boot/wifi.conf ]]; then
	mount -o remount,rw /boot
	echo "WIFI_PASS=${WIFI_PASS}" > /boot/wifi.conf
	mount -o remount,ro /boot
fi
sed -i "s|^wpa_passphrase=bananapi$|wpa_passphrase=${WIFI_PASS}|g" /etc/hostapd/*.conf

#############################################################################
# Use the saved MAC address from the boot partition if one is available.
# Record the current MAC address if saved MAC address isn't available.
#############################################################################
/opt/bpi-r2-router-builder/helpers/router-helper.sh mac saved

#############################################################################
# Bring the "eth0" interface up if not already up:
#############################################################################
ifconfig eth0 | grep "UP," &> /dev/null || /sbin/ifup eth0

#############################################################################
# Enable DBDC on any MT76xx wifi card that supports it:
#############################################################################
for file in /sys/kernel/debug/ieee80211/*; do
	test -e $file/mt76/dbdc && echo 1 > $file/mt76/dbdc
done

#############################################################################
# Load support files for R2's onboard Wifi/BT hardware and set WiFi mode:
#############################################################################
if [[ ! -e /dev/wmtWifi ]]; then
	/usr/bin/wmt_loader &> /var/log/wmtloader.log
	sleep 3
fi
if [[ -c /dev/stpwmt ]]; then
	ps aux | grep stp_uart_launcher | grep -v grep >& /dev/null || /usr/bin/stp_uart_launcher -p /etc/firmware &> /var/log/stp_launcher.log &
	sleep 5
fi
modprobe wlan_gen2
echo $([[ "${ONBOARD_MODE:-"A"}" == "1" ]] && echo 1 || echo A) > /dev/wmtWifi

#############################################################################
# Rename the WiFi interfaces on the MT76xx wifi card:
#############################################################################
cd /sys/class/net
for IFACE in $(ls -l | grep pcie | awk '{print $9}'); do
	DEV="$(lspci -s $(basename $(ls -l ${IFACE}/device | awk '{print $NF}')) | grep MEDIATEK | awk '{print $NF}')"
	if [[ ! -z "${DEV}" ]]; then
		[[ -f /sys/kernel/debug/ieee80211/$(basename $(ls -l ${IFACE}/phy80211 | awk '{print $NF}'))/mt76/dbdc ]] && POST=24g || POST=5g
		ip link set ${IFACE} name mt${DEV}_${POST}
	fi
done

#############################################################################
# Return error code 0 to the caller:
#############################################################################
exit 0
