#!/bin/bash

# Bring the "eth0" interface up if not already up:
ifconfig eth0 | grep "UP," &> /dev/null || /sbin/ifup eth0

# Enable DBDC on any MT76xx wifi card that supports it:
for file in /sys/kernel/debug/ieee80211/*; do 
	test -e $file/mt76/dbdc && echo 1 > $file/mt76/dbdc
done
exit 0
