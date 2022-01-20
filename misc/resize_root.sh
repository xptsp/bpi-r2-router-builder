#!/bin/bash

########################################################################################
# Expand the root partition to maximum size if not already done so:
########################################################################################
if [[ "$(fdisk -l /dev/mmcblk0 | grep p2 | awk '{print $3}')" -eq "4261887" ]]; then
	FREE=$(df -BM /dev/mmcblk0p2 | tail -1 | awk '{print $4}' | sed "s|M||g")
	sfdisk --delete /dev/mmcblk0 2
	echo 67584,,0x83 | sfdisk /dev/mmcblk0 -a --force
	partprobe /dev/mmcblk0
	resize2fs /dev/mmcblk0p2

	########################################################################################
	# Create empty image on the root partition:
	########################################################################################
	test -f /boot/persistent.conf && source /boot/persistent.conf
	if [[ -z "${PERSISTENT_SIZE}" ]]; then
		PERSISTENT_SIZE=$(( $(df -BM /dev/mmcblk0p2 | tail -1 | awk '{print $4}' | sed "s|M||g") - $FREE ))m
		sed -i '/^PERSISTENT_SIZE=/d' /boot/persistent.conf
		echo "PERSISTENT_SIZE=$PERSISTENT_SIZE" >> /boot/persistent.conf
	fi
	fallocate -l ${PERSISTENT_SIZE} /persistent.img
	mkfs.ext4 /persistent.img
fi
