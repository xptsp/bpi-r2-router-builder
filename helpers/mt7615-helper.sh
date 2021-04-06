#!/bin/bash

# Enable DBDC on any MT76xx wifi card that supports it:
for file in /sys/kernel/debug/ieee80211/*; do 
        test -e $file/mt76/dbdc && echo 1 > $file/mt76/dbdc
done

# Rename the interfaces of the MT7615 card:
LOG_FILE=/var/run/mt7615-helper.log
SPC="--------"
sleep 1
PCI=$(lspci | grep MEDIATEK | grep 7615 | cut -d" " -f 1)
if [[ ! -z "${PCI}" ]]; then
	cd /sys/class/net
	IFACES=($(ls -l | grep "${PCI}" | awk '{print $9}' | grep -v "^mt_"))
	for IFACE in ${IFACES[@]}; do
		echo "Network interface: $IFACE"
		NEW=mt_24g
		[[ "${IFACE}" == "rename"* ]] && NEW=mt_50g

		# First command
		(CMD="ip link set ${IFACE} down"
		echo "$SPC CMD: ${CMD} $SPC"
		${CMD}
		echo -e "$SPC\n"

		# Second command
		MAC=$(ifconfig mt_24g | grep ether | awk '{print $2}')
		MAC=${MAC:0:${#MAC}-1}0
		CMD="ifconfig ${IFACE} hw ether ${MAC}"
		echo "$SPC CMD: ${CMD} $SPC"
		${CMD}
		echo -e "$SPC\n"

		# Third command
		CMD="ip link set ${IFACE} name ${NEW}"
		echo "$SPC CMD: ${CMD} $SPC"
		${CMD}
		echo -e "$SPC\n"

		# Fourth command
		CMD="ip link set ${NEW} up"
		echo "$SPC CMD: ${CMD} $SPC"
		${CMD}
		echo -e "$SPC\n") >& $LOG_FILE
	done
fi
exit 0
