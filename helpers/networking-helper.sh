#!/bin/bash
#############################################################################
# This helper script takes care of any tasks that should occur before the
# networking service officially starts.  Tasks that occur here should not
# take very long to execute and should not rely on other services being up
# and running.
#############################################################################
[[ -f /etc/default/router-settings ]] && source /etc/default/router-settings

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
# Return error code 0 to the caller:
#############################################################################
exit 0
