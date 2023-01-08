#!/bin/bash
#############################################################################
# This helper script attempts to automount USB storage devices as Samba 
# shares automatically.
#############################################################################
ACTION=$1
DEVBASE=$2
DEVICE="/dev/${DEVBASE}"

# See if this drive is already mounted
MOUNT_POINT=$(mount | grep ${DEVICE} | awk '{ print $3 }')

#############################################################################
if [[ "$1" == "mount" ]]; then
	# If already mounted, exit with error code 1:
	[[ -n ${MOUNT_POINT} ]] && exit 1

	# Get info for this drive: $ID_FS_LABEL, $ID_FS_UUID, and $ID_FS_TYPE
	eval $(/sbin/blkid -o udev ${DEV})

	# Figure out a mount point to use:
	LABEL=${ID_FS_LABEL:-"${DEVBASE}"}
	grep -q " /media/${LABEL} " /etc/mtab && LABEL+="-${DEVBASE}"
	MOUNT_POINT="/media/${LABEL}"
 	mkdir -p ${MOUNT_POINT}

	# Determine mounting options, then mount the USB drive:
    OPTS="rw,relatime"
    [[ ${ID_FS_TYPE} == "vfat" ]] && OPTS+=",users,gid=100,umask=000,shortname=mixed,utf8=1,flush"
    if ! mount -o ${OPTS} ${DEVICE} ${MOUNT_POINT}; then
        rmdir ${MOUNT_POINT}
        exit 1
    fi

	# If successfully mounted, write Samba configuration for the device:
	cat << EOF >> ${FILE}

[${1}]
comment=${2}
path=${MEDIA}
browseable=Yes
writeable=Yes
only guest=no
create mask=0755
directory mask=0755
public=no
#mount_dev=${DEV}
EOF
	fi
 
#############################################################################
elif [[ "$1" == "umount" ]]; then
	# Do a lazy unmount of the device:
    [[ -n ${MOUNT_POINT} ]] && umount -l ${DEVICE}

	# Delete all empty dirs in /media that aren't being used as mount points: 
	for f in /media/* ; do
		[[ -n $(find "$f" -maxdepth 0 -type d -empty) ]] && grep -q " $f " /etc/mtab || rmdir "$f"
	done

	# Remove any invalid shares from the samba configuration:
	cat ${FILE} | grep "^\[" | cut -d[ -f 2 | cut -d] -f 1 | while read section; do 
		# Get the path variable in the specified section:
		DIR=$(sed -nr "/\[$section\]/,/\[/{/^path=/p}" ${FILE} | cut -d= -f 2)

		# If the "path" line exists within the section, but the specified path doesn't
		# exist, then remove the section from the configuration file:
		if [[ ! -z "${DIR}" && ! -d ${DIR} ]]; then
			test -f /tmp/smb.conf && rm /tmp/smb.conf
			WRITE=true
			while read line; do
				if [[ "${line}" =~ ^\[ ]]; then [[ "${line}" == "[${DIR}]" ]] && WRITE=false || WRITE=true; fi
				[[ "${WRITE}" == "true" ]] && echo $line >> /tmp/smb.conf
			done < ${FILE}
			mv /tmp/smb.conf ${FILE}
		fi
	done			
fi

#############################################################################
# Reload samba configuration ONLY if it is running:
#############################################################################
systemctl -q is-active smbd && smbcontrol all reload-config

#############################################################################
# Exit with error code 0:
#############################################################################
exit 0
