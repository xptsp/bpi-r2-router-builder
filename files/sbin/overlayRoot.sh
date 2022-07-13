#!/usr/bin/env bash
#  Read-only Root-FS for Raspian using overlayfs
#
#  Created 2017 by Pascal Suter @ DALCO AG, Switzerland to work on Raspian as custom init script
#  (raspbian does not use an initramfs on boot)
#  Modifications listed as 1.2 Mark Lister: github.com/marklister
#
#  This program is free software: you can redistribute it and/or modify
#  it under the terms of the GNU General Public License as published by
#  the Free Software Foundation, either version 3 of the License, or
#  (at your option) any later version.
#
#  This program is distributed in the hope that it will be useful,
#  but WITHOUT ANY WARRANTY; without even the implied warranty of
#  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
#  GNU General Public License for more details.
#
#	You should have received a copy of the GNU General Public License
#	along with this program.  If not, see
#	<http://www.gnu.org/licenses/>.
#
#
#  Tested with Raspbian mini, 2018-10-09
#
#  This script will mount the root filesystem read-only and overlay it with a temporary tempfs
#  which is read-write mounted. This is done using the overlayFS which is part of the linux kernel
#  since version 3.18.
#  when this script is in use, all changes made to anywhere in the root filesystem mount will be lost
#  upon reboot of the system. The SD card will only be accessed as read-only drive, which significantly
#  helps to prolong its life and prevent filesystem coruption in environments where the system is usually
#  not shut down properly
#

defaults(){

	# OverlayRoot config file override these defaults in /etc/overlayRoot.conf

	# What to do if the script fails
	# original = run the original /sbin/init
	# console = start a bash console. Useful for debugging

	ON_FAIL=original

	# Discover the root device using PARTUUID=xxx UUID=xxx or LABEL= xxx if the fstab detection fails.
	# Note PARTUUID does not work at present.
	# Default is "LABEL=BPI-ROOT". This makes the script work out of the box

	SECONDARY_ROOT_RESOLUTION="LABEL=BPI-ROOT"

	# The filesystem name to use for the RW partition
	# Default ROOT-RW

	RW_NAME=ROOT-RW

	# Discover the rw device using PARTUUID=xxx UUID=xxx or LABEL= xxx  if the fstab detection fails.
	# Note PARTUUID does not work at present.
	# Default is "LABEL=ROOT-RW".  This makes the script work out of the box if the user labels their partition

	SECONDARY_RW_RESOLUTION="LABEL=ROOT-RW"

	# What to do if the user has specified rw media in fstab and it is not found using primary and secondary lookup?
	# fail = follow the fail logic see ON_FAIL
	# tmpfs = mount a tmpfs at the root-rw location Default

	ON_RW_MEDIA_NOT_FOUND=tmpfs

	# An image file on the RW partition to use as the RW layer in the overlayfs.
	# Default: none
	RW_IMAGE_NAME=none

	# What to do if the specified image file doesn't exist.  Default is "ignore".
	# ignore = continue running script, assuming RW partition is RW layer.
	# create = create the image file, as large as requested.
	RW_IMAGE_CREATE=ignore
	
	# What size to create the image file.  Default is "all"
	# all = all remaining space on the partition
	# (x)g = x gigabytes (aka 10g)
	RW_IMAGE_SIZE=all
	
	LOGGING=warning

	LOG_FILE=/var/log/overlayRoot.log

	SECONDARY_REFORMAT=no

	#Read the selected configuration
	source /etc/overlayRoot.conf
}

defaults
rootmnt=""
RW="/mnt/$RW_NAME"  # Mount point for writable drive

FAILURES=0
WARNINGS=0

log_fail(){
	echo -e "[FAIL] $@" | tee -a /mnt/overlayRoot.log
	((FAILURES++))
}

log_warning(){
	if [ $LOGGING == "warning" ] || [ $LOGGING == "info" ]; then
		echo -e "[FAIL] $@" | tee -a /mnt/overlayRoot.log
	fi
	((WARNINGS++))
}

log_info(){
	if [ $LOGGING == "info" ]; then
		echo -e "[INFO] $1" | tee -a /mnt/overlayRoot.log
	fi
}

fail(){
	log_fail $@
	if [ $ON_FAIL == "original" ]; then
		exec /sbin/init
		exit 0
	elif [ $ON_FAIL == "console" ]; then
		exec /bin/bash # one can "exit" to original boot process
		exec /bin/init
	else
		exec /bin/bash
	fi
}

# Find a specific fstab entry
# $1=mountpoint
# $2=fstype (optional)
# returns 0 on success, 1 on failure (not found or no fstab)
read_fstab_entry() {
	# Not found by default.
	found=1

	for file in ${rootmnt?}/etc/fstab; do
		if [ -f "$file" ]; then
			# shellcheck disable=SC2034
			while read -r MNT_FSNAME MNT_DIR MNT_TYPE MNT_OPTS MNT_FREQ MNT_PASS MNT_JUNK; do
				case "$MNT_FSNAME" in
				  ""|\#*)
					continue;
					;;
				esac
				if [ "$MNT_DIR" = "$1" ]; then
					if [ -n "$2" ]; then
						[ "$MNT_TYPE" = "$2" ] || continue;
					fi
					found=0
					break 2
				fi
			done < "$file"
		fi
	done

	return $found
}

# Resolve device node from a name.  This expands any LABEL or UUID.
# $1=name
# Resolved name is echoed.
resolve_device() {
	DEV="$1"

	case "$DEV" in
	LABEL=* | UUID=* | PARTLABEL=* | PARTUUID=*)
		DEV="$(blkid -l -t "$DEV" -o device)" || return 1
		;;
	esac
	[ -e "$DEV" ] && echo "$DEV"
}

#Wait for a device to become available
# $1 device
# $2 timeout
await_device() {
	count=0
	if [ -z $2 ]; then TIMEOUT=60; else TIMEOUT=$2; fi
	result=1
	while [ $count -lt $TIMEOUT ];
	do
		log_info "Waiting for device $1 $count";
		test -e $1
		if [ $? -eq 0 ]; then
			log_info "$1 appeared after $count seconds";
			result=0
			break;
		fi
		sleep 1;
		((count++))
	done
	return $result
}

# Run the command specified in $1. Log the result. If the command fails and safe is selected abort to /bin/init
# Otherwise drop to a bash prompt.
run_protected_command(){
	if [[ ! -z "$1" ]]; then
		log_info "Run: $1"
		eval $1
		if [ $? -ne 0 ]; then
			log_fail "ERROR: error executing $1"
		fi
	fi
}

# Unpack default configuration stored in the boot partition:
unpack_default_config(){
	read_fstab_entry "/boot"
	log_info "[BOOT] Found $MNT_FSNAME for boot"
	resolve_device $MNT_FSNAME
	log_info "[BOOT] Resolved [$MNT_FSNAME] as [$DEV]"
	BOOT=/mnt/boot
	mkdir -p ${BOOT}
	mount -t $MNT_TYPE -o $MNT_OPTS $DEV ${BOOT}
	test -f ${BOOT}/bpiwrt.cfg && run_protected_command "unsquashfs -f -d ${RW} ${BOOT}/bpiwrt.cfg" 
	umount ${BOOT}
}

copy_builder(){
	local DIR=opt/bpi-r2-router-builder
	if ! test -d ${RW}/${DIR}; then
		log_info "[INFO] Copying bpi-r2-router-builder onto RW partition"
		cp -aR /mnt/lower/${DIR} ${RW}/${DIR}
	fi
}

################## BASIC SETUP ################################################################################

run_protected_command "mount -t proc proc /proc"

# check if overlayRoot is needed
for x in $(cat /proc/cmdline); do
	if [ "x$x" = "xnoOverlayRoot" ] ; then
		log_info "overlayRoot is disabled. continue init process."
		exec /sbin/init "$@"
	fi
done
run_protected_command "mount -t tmpfs inittemp /mnt"
run_protected_command "modprobe overlay"

######################### PHASE 1 DATA COLLECTION #############################################################

# ROOT
read_fstab_entry "/"
log_info "[ROOT] Found $MNT_FSNAME for root"
resolve_device $MNT_FSNAME
log_info "[ROOT] Resolved [$MNT_FSNAME] as [$DEV]"
if [ -z $DEV ]; then
	resolve_device $SECONDARY_ROOT_RESOLUTION
	log_info "[ROOT] Resolved device [$SECONDARY_ROOT_RESOLUTION] as [$DEV]"
fi

if [ -z $DEV ];  then
	log_fail "Can't resolve root device from [$MNT_FSNAME] or [$SECONDARY_ROOT_RESOLUTION].  Try changing entry to UUID or plain device"
fi

if ! test -e $DEV; then
	log_fail "Resolved root to $DEV but can't find the device"
fi

ROOT_MOUNT="mount -t $MNT_TYPE -o $MNT_OPTS,ro $DEV /mnt/lower"
RO_DEV=$DEV


# ROOT-RW
if read_fstab_entry $RW; then
	log_info "found fstab entry for $RW"

	# If we are using the SD card or EMMC, then we don't have to wait for the device :p
	if [[ ! "$MNT_FSNAME" =~ ^/dev/mmcblk ]]; then
		# Things don't go well if usb is not up or fsck is being performed
		# kludge -- wait for /dev/sda1
		await_device "/dev/sda1"  20  #Wait a generous amount of time for first device
		if ! resolve_device $MNT_FSNAME; then
			#log_info "No device found for $RW going to try for /dev/sdb1..."
			DEV="/dev/sdb1"
		fi
		#This time we are hopefully waiting for the actual device not /dev/sdb1
		await_device "$DEV" 5
		#Retry the lookup
	fi

	resolve_device $MNT_FSNAME
	log_info "Resolved [$MNT_FSNAME] as [$DEV]"
	if [ -z $DEV ]; then
		resolve_device $SECONDARY_RW_RESOLUTION
		log_info "Resolved [$SECONDARY_RW_RESOLUTION] as [$DEV]"
	fi
	await_device "$DEV" 20


	if [ -n $DEV ] && [ -e "$DEV" ]; then

		RW_MOUNT="mount -t $MNT_TYPE -o $MNT_OPTS $DEV $RW"

		# If reformatting has been requested, change the flag back to "do not reformat":
		unset RW_FORMAT
		[[ "$SECONDARY_REFORMAT" =~ (yes|YES) ]] && RW_FORMAT="mkfs.$MNT_TYPE -F $DEV -L $RW_NAME"
	else
		if ! test -e $DEV; then
			log_warning "Resolved root to $DEV but can't find the device"
		fi
		if [ $ON_RW_MEDIA_NOT_FOUND == "tmpfs" ]; then
			log_warning "Could not resolve the RW media or find it on $DEV"
			RW_MOUNT="mount -t tmpfs emergency-root-rw $RW"
		else
			log_fail "Rw media required but not found"
		fi
	fi
else
	log_info "No rw fstab entry, will mount a tmpfs"
	RW_MOUNT="mount -t tmpfs tmp-root-rw $RW"
fi

####################### PHASE 2 SANITY CHECK AND ABORT HANDLING ###############################################

if [ $FAILURES -gt 0 ]; then
	fail "Fix $FAILURES failures and maybe $WARNINGS warnings before overlayRoot will work"
fi

###################### PHASE 3 ACTUALLY DO STUFF ##############################################################

# create a writable fs to then create our mountpoints
mkdir /mnt/lower
mkdir $RW
mkdir /mnt/newroot

[[ ! -z "$RW_FORMAT" ]] && run_protected_command "$RW_FORMAT"
run_protected_command "$RW_MOUNT"
run_protected_command "$ROOT_MOUNT"
[[ ! -z "$RW_FORMAT" ]] && run_protected_command "unpack_default_config"
copy_builder

# we need to see if we need to format and/or mount an image file on the rw partition:
if [[ ! -z "${RW_IMAGE_FILE}" && "${RW_IMAGE_FILE}" != "none" ]]; then
	EXT4=/mnt/ext4
	mkdir $EXT4
	if [[ ! -f $RW/${RW_IMAGE_FILE} && "${RW_IMAGE_CREATE}" == "create" ]]; then
		[[ "${RW_IMAGE_SIZE}" == "all" ]] && RW_IMAGE_SIZE=$(df -BM --output=used $DEV | tail -1)
		run_protected_command "fallocate -l ${RW_IMAGE_SIZE} $RW/${RW_IMAGE_FILE}"
		run_protected_command "mkfs.ext4 $RW/${RW_IMAGE_FILE}"
	fi
	[[ ! -f $RW/${RW_IMAGE_FILE} ]] && run_protected_command "mount -o loop $RW/${RW_IMAGE_FILE} $EXT4 && RW=$EXT4"
fi

mkdir -p $RW/upper
mkdir -p $RW/work

run_protected_command "mount -t overlay -o lowerdir=/mnt/lower,upperdir=$RW/upper,workdir=$RW/work overlayfs-root /mnt/newroot"

# create mountpoints inside the new root filesystem-overlay
mkdir -p /mnt/newroot/ro
mkdir -p /mnt/newroot/rw

# remove root mount from fstab (non-permanent modification on tmpfs rw media)
if ! test -e /mnt/newroot/etc/fstab || cat /mnt/newroot/etc/fstab | grep -e "^$RO_DEV" >& /dev/null; then
	sed "s|^${RO_DEV} |#${RO_DEV}|g" /mnt/lower/etc/fstab > /mnt/newroot/etc/fstab
	sed -i "s|^${SECONDARY_RW_RESOLUTION} |#${SECONDARY_RW_RESOLUTION}|g" /mnt/newroot/etc/fstab
	echo "" >> /mnt/newroot/etc/fstab
	echo "# the original overlay mount points has been commented out by" >> /mnt/newroot/etc/fstab
	echo "# overlayRoot.sh.  this is only a temporary modification, the" >> /mnt/newroot/etc/fstab
	echo "# original fstab stored on the disk can be found in /ro/etc/fstab" >> /mnt/newroot/etc/fstab
fi

# change to the new overlay root
cd /mnt/newroot
cat /mnt/overlayRoot.log >> /mnt/newroot/$LOG_FILE
pivot_root . mnt
exec chroot . sh -c "$(cat <<END
# move ro and rw mounts to the new root
mount --move /mnt/mnt/lower/ /ro
if [ $? -ne 0 ]; then
	echo "ERROR: could not move ro-root into newroot"
	/bin/bash
fi
mount --move /mnt/$RW /rw
if [ $? -ne 0 ]; then
	echo "ERROR: could not move tempfs rw mount into newroot"
	/bin/bash
fi
# unmount unneeded mounts so we can unmout the old readonly root
umount /mnt/mnt
umount /mnt/proc
umount /mnt/dev
umount /mnt
# continue with regular init
exec /sbin/init
END
)"
