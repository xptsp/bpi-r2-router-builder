#!/bin/bash

# Enable DBDC on any MT76xx wifi card that supports it:
for file in /sys/kernel/debug/ieee80211/*; do 
	if test -e $file/mt76/dbdc; then
		echo 1 > $file/mt76/dbdc
		iw dev $(basename $file) set power_save off
	fi
done
exit 0
