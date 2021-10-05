#!/bin/bash
#############################################################################
# This helper script takes care of any tasks that should occur before the 
# networking service officially starts.  Tasks that occur here should not
# take very long to execute and should not rely on other services being up
# and running.
#############################################################################

# Use the saved MAC address from the boot partition if one is available.
# Record the current MAC address if saved MAC address isn't available.
/opt/bpi-r2-router-builder/helpers/router-helper.sh mac saved

# Bring the "eth0" interface up if not already up:
ifconfig eth0 | grep "UP," &> /dev/null || /sbin/ifup eth0

# Enable DBDC on any MT76xx wifi card that supports it:
for file in /sys/kernel/debug/ieee80211/*; do 
	test -e $file/mt76/dbdc && echo 1 > $file/mt76/dbdc
done

# Return error code 0 to the caller:
exit 0
