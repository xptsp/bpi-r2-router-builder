#!/bin/bash
#############################################################################
# This helper script takes care of any tasks that should occur before/after
# the hostapd service officially starts/stops.  Tasks that occur here should
# not take very long to execute and should not rely on other services being
# up and running.
#############################################################################

##############################################################################
# Are we starting hostapd on a particular interface?
# If so, check if interface is a valid configuration and report error if not:
##############################################################################
if [[ "$1" == "check" ]]; then
	[[ -z "$2" ]] && echo "ERROR: No hostapd configuration file specified as 2nd parameter!" && exit 2
	[[ ! -f /etc/hostapd/$2.conf ]] && echo "ERROR: Hostapd configuration for interface $2 is missing!  Aborting!" && exit 3
	IFACE=$(grep "^interface=" /etc/hostapd/$2.conf | cut -d= -f 2)
	[[ -z "${IFACE}" ]] && echo "ERROR: Missing interface line in hostapd configuration!  Aborting!" && exit 4
	if ! ifconfig ${IFACE} 2> /dev/null >& /dev/null; then 
		echo "ERROR: Interface ${IFACE} is missing!  Aborting!" && exit 5
	elif ! ifconfig ${IFACE} | grep "inet" >& /dev/null; then
		echo "ERROR: Interface ${IFACE} does not have an IP address!  Aborting!" && exit 6
	fi

##############################################################################
# If we stopping the "ap0" interface, reset the interface correctly:
##############################################################################
elif [[ "$1" == "stop" && "$2" == "ap0" ]]; then
	# Turn off the onboard interface and wait 5 seconds:
	echo 0  | tee /var/run/wmtWifi > /dev/wmtWifi
	sleep 5

	# Set the onboard interface mode and wait 2 seconds:
	onboard_wifi=$(grep "onboard_wifi" /etc/default/router-settings | cut -d= -f 2)
	echo ${onboard_wifi:="A"} | tee /var/run/wmtWifi > /dev/wmtWifi
	sleep 2
fi

##############################################################################
# Exit with error code 0
##############################################################################
exit 0
