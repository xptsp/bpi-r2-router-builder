#!/bin/bash

# Enable DBDC on any MT76xx wifi card that supports it:
for file in /sys/kernel/debug/ieee80211/*; do 
        test -e $file/mt76/dbdc && echo 1 > $file/mt76/dbdc
done
exit 0
