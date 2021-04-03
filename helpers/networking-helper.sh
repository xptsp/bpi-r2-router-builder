#!/bin/bash

# Enable DBDC on any MT76xx wifi card that supports it:
for file in /sys/kernel/debug/ieee80211/*; do 
        test -e $file/mt76/dbdc && echo 1 > $file/mt76/dbdc
done

# Rename the interfaces of the MT7615 card:
if ! test -e /var/run/mt7615_renamed; then
	sleep 1
	PCI=$(lspci | grep 7615 | cut -d" " -f 1)
	if [[ ! -z "${PCI}" ]]; then
		cd /sys/class/net
		ls -l | grep "${PCI}" | while read x; do
			LINE=($x)
			OLD=${LINE[-3]}
			NEW=mt_24g
			[[ "${OLD}" == "rename"* ]] && NEW=mt_5g

			# First command
			CMD="ip link set ${OLD} down"
			echo "CMD: ${CMD}" | tee -a /var/run/mt7615_renamed.log
			${CMD} | tee -a /var/run/mt7615_renamed.log
			echo "" | tee -a /var/run/mt7615_renamed.log

			# Second command
			CMD="ip link set ${OLD} name ${NEW}"
			echo "CMD: ${CMD}" | tee -a /var/run/mt7615_renamed.log
			${CMD} | tee -a /var/run/mt7615_renamed.log
			echo "" | tee -a /var/run/mt7615_renamed.log

			# Third command
			CMD="ip link set ${OLD} up"
			echo "CMD: ${CMD}" | tee -a /var/run/mt7615_renamed.log
			${CMD} | tee /var/run/mt7615_renamed.log
			echo "" | tee -a /var/run/mt7615_renamed.log
		done
	fi
fi

exit 0
