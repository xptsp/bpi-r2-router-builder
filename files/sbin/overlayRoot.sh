#!/usr/bin/env bash
#################################################################################################
# OverlayRoot config settings.  Override these defaults in /etc/overlayRoot.conf
#################################################################################################
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

LOGGING=warning

LOG_FILE=/var/log/overlayRoot.log

SECONDARY_REFORMAT=no

#################################################################################################
# Logging functions
#################################################################################################
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

#################################################################################################
# Find a specific fstab entry
# $1=mountpoint
# $2=fstype (optional)
# returns 0 on success, 1 on failure (not found or no fstab)
#################################################################################################
read_fstab_entry() {
	# Not found by default.
	found=1

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
	done < /etc/fstab
	return $found
}

#################################################################################################
# Resolve device node from a name.  This expands any LABEL or UUID.  $1 is name
# Resolved name is echoed.
#################################################################################################
resolve_device() {
	DEV="$1"

	case "$DEV" in
		LABEL=* | UUID=* | PARTLABEL=* | PARTUUID=*)
			DEV="$(blkid -l -t "$DEV" -o device)" || return 1
			;;
	esac
	[ -e "$DEV" ] && echo "$DEV"
}

#################################################################################################
#Wait for a device to become available.  $1 is device, $2 is timeout period
#################################################################################################
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

#################################################################################################
# Run the command specified in $1. Log the result. If the command fails and safe is selected
# abort to /bin/init.  Otherwise drop to a bash prompt.
#################################################################################################
run_protected_command(){
	if [[ ! -z "$1" ]]; then
		log_info "Run: $1"
		eval $1
		if [ $? -ne 0 ]; then
			log_fail "ERROR: error executing $1"
		fi
	fi
}


################## BASIC SETUP ################################################################################
source /etc/overlayRoot.conf

RW="/mnt/$RW_NAME"  # Mount point for writable drive
FAILURES=0
WARNINGS=0

# check if overlayRoot is needed
mount -t proc proc /proc || fail "ERROR: error executing \"mount -t proc proc /proc\"!"
for x in $(cat /proc/cmdline); do
	if [ "$x" = "noOverlayRoot" ] ; then
		log_info "overlayRoot is disabled. continue init process."
		exec /sbin/init "$@"
	fi
done
mount -t tmpfs inittemp /mnt || fail "ERROR: error executing \"mount -t tmpfs inittemp /mnt\"!"
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

ROOT_MOUNT="mount -t $MNT_TYPE -o $MNT_OPTS $DEV /mnt/ro"
RO_DEV=$DEV


# ROOT-RW
if read_fstab_entry $RW_NAME; then
	log_info "found fstab entry for $RW_NAME"

	# If we are using the SD card or EMMC, then we don't have to wait for the device :p
	if [[ "$MNT_FSNAME" =~ ^/dev/  ]]; then
		if [[ ! "$MNT_FSNAME" =~ ^/dev/mmcblk ]]; then
			# Things don't go well if usb is not up or fsck is being performed
			# kludge -- wait for /dev/sda1
			await_device "/dev/sda1"  20  #Wait a generous amount of time for first device
			if ! resolve_device $MNT_FSNAME; then
				#log_info "No device found for /mnt/rw going to try for /dev/sdb1..."
				DEV="/dev/sdb1"
			fi
			#This time we are hopefully waiting for the actual device not /dev/sdb1
			await_device "$DEV" 5
			#Retry the lookup
		fi
	fi

	resolve_device $MNT_FSNAME
	log_info "Resolved [$MNT_FSNAME] as [$DEV]"
	if [ -z $DEV ]; then
		resolve_device $SECONDARY_RW_RESOLUTION
		log_info "Resolved [$SECONDARY_RW_RESOLUTION] as [$DEV]"
	fi
	await_device "$DEV" 20


	if [ -n $DEV ] && [ -e "$DEV" ]; then

			RW_MOUNT="mount -t $MNT_TYPE -o $MNT_OPTS $DEV /mnt/rw"

			# If reformatting has been requested, change the flag back to "do not reformat":
			unset RW_FORMAT
			[[ "$SECONDARY_REFORMAT" =~ (yes|YES) ]] && RW_FORMAT="mkfs.$MNT_TYPE -F $DEV -L $RW_NAME"
	else
		if ! test -e $DEV; then
			log_warning "Resolved root to $DEV but can't find the device"
		fi
		if [ $ON_RW_MEDIA_NOT_FOUND == "tmpfs" ]; then
			log_warning "Could not resolve the RW media or find it on $DEV"
			RW_MOUNT="mount -t tmpfs emergency-root-rw /mnt/rw"
		else
			log_fail "Rw media required but not found"
		fi
	fi
else
	log_info "No rw fstab entry, will mount a tmpfs"
	RW_MOUNT="mount -t tmpfs tmp-root-rw /mnt/rw"
fi

####################### PHASE 2 SANITY CHECK AND ABORT HANDLING ###############################################

if [ $FAILURES -gt 0 ]; then
	fail "Fix $FAILURES failures and maybe $WARNINGS warnings before overlayRoot will work"
fi

###################### PHASE 3 ACTUALLY DO STUFF ##############################################################

# create a writable fs to then create our mountpoints
mkdir /mnt/ro
mkdir /mnt/rw
mkdir /mnt/root

run_protected_command "$ROOT_MOUNT"
[[ ! -z "/mnt/rw_FORMAT" ]] && run_protected_command "/mnt/rw_FORMAT"
run_protected_command "/mnt/rw_MOUNT"

mkdir -p /mnt/rw/upper
mkdir -p /mnt/rw/work

run_protected_command "mount -t overlay -o lowerdir=/mnt/ro,upperdir=/mnt/rw/upper,workdir=/mnt/rw/work overlayfs-root /mnt/root"

# remove root mount from fstab (non-permanent modification on tmpfs rw media)
if ! test -e /mnt/root/etc/fstab || cat /mnt/root/etc/fstab | grep -e "^$RO_DEV" >& /dev/null; then
	sed "s|^${RO_DEV} |#${RO_DEV}|g" /mnt/ro/etc/fstab > /mnt/root/etc/fstab
	sed -i "s|^${SECONDARY_RW_RESOLUTION} |#${SECONDARY_RW_RESOLUTION}|g" /mnt/root/etc/fstab
	echo "" >> /mnt/root/etc/fstab
	echo "# the original overlay mount points has been commented out by" >> /mnt/root/etc/fstab
	echo "# overlayRoot.sh.  this is only a temporary modification, the" >> /mnt/root/etc/fstab
	echo "# original fstab stored on the disk can be found in /ro/etc/fstab" >> /mnt/root/etc/fstab
fi

# change to the new overlay root
cd /mnt/root
cat /mnt/overlayRoot.log >> /mnt/root/$LOG_FILE
pivot_root . mnt
exec chroot . sh -c "$(cat <<END
# unmount unneeded mounts so we can unmout the old root
umount /mnt/mnt/ro
umount /mnt/mnt/upper
umount /mnt/mnt
umount /mnt/proc
umount /mnt/dev
umount /mnt

# continue with regular init
exec /sbin/init
END
)"
