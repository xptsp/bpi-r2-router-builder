#!/bin/bash
#############################################################################
# This helper script attempts to automount USB storage devices as Samba 
# shares automatically.
#############################################################################

if [[ "${UID}" -ne 0 ]]; then
	sudo $0 $@
	exit $?
fi
FILE=/etc/samba/smb.conf

#############################################################################
if [[ "$1" == "mount" ]]; then
	# Use the pmount command to mount to a directory with the volume label.
	# Failing that, mount to the device name.
	DEV=/dev/${2}
	[[ "$(blkid -o export ${DEV} | grep "TYPE=")" != "" ]] && return
	MEDIA=$(blkid ${DEV} -o export | grep "LABEL=" | cut -d"=" -f 2)
	LABEL=${MEDIA:="${2}"}
	MEDIA=/media/"${LABEL// /_}"
	
	# If successfully mounted, write Samba configuration for the device:
	if /usr/bin/pmount ${DEV} ${MEDIA}; then
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
	# Detect the filesystem type before attempting to mount the device:
	DEV=/dev/${2}
	TYPE=$(blkid -o export ${DEV}| grep "^TYPE=")

	# If we identified a filesystem on the device, use the pmount command to mount to a
	# directory with the volume label.  Failing that, mount to the device name.
	[[ ! -z "${TYPE}" ]] && /usr/bin/pumount ${DEV}

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
# Restart the "smbd" service:
systemctl restart smbd

#############################################################################
# Exit with error code 0:
#############################################################################
exit 0
