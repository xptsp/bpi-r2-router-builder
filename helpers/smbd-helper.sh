#!/bin/bash
#############################################################################
# This helper script takes care of any tasks that should occur before the 
# samba (smbd) service officially starts.  Tasks that occur here should not
# take very long to execute and should not rely on other services being up
# and running.
#
# This script also provides support for automounting USB storage devices.
#############################################################################

if [[ "${UID}" -ne 0 ]]; then
	sudo $0 $@
	exit $?
fi


function check_params()
{
	if [[ -z "${1}" ]]; then
		echo "ERROR: Must specify device name to mount!"
		exit 1
	fi
	if [[ ! -e "/dev/${1}" ]]; then
		echo "ERROR: Invalid or non-existant device specified!"
		exit 1
	fi
}

function usb_mount()
{
	# Use the pmount command to mount to a directory with the volume label.
	# Failing that, mount to the device name.
	DEV=/dev/${1}
	[[ "$(blkid -o export ${DEV} | grep "TYPE=")" != "" ]] && return
	MEDIA=$(blkid ${DEV} -o export | grep "LABEL=" | cut -d"=" -f 2)
	LABEL=${MEDIA:="${1}"}
	MEDIA=/media/"${LABEL// /_}"
	/usr/bin/pmount ${DEV} ${MEDIA} && samba_share ${LABEL} ${MEDIA} ${1}
}

function samba_share()
{
	# Write Samba configuration for the device:
	cat << EOF >> /etc/samba/smb.d/smb.conf

[${1}]
comment=${1}
path=${2}
browseable=Yes
writeable=Yes
only guest=no
create mask=0755
directory mask=0755
public=no
#mount_dev=${3}
EOF
}

function share_folders()
{
	for file in $(ls /mnt 2> /dev/null); do e
		samba_share $(basename $file) $file
	done
}

function usb_umount()
{
	# Detect the filesystem type before attempting to mount the device:
	DEV=/dev/${1}
	TYPE=$(blkid -o export ${DEV}| grep "^TYPE=")

	# If we identified a filesystem on the device, use the pmount command to mount to a
	# directory with the volume label.  Failing that, mount to the device name.
	[[ ! -z "${TYPE}" ]] && /usr/bin/pumount ${DEV}
}

function remove_invalid()
{
	# Remove any invalid shares from the samba configuration:
	FILE=/etc/samba/smb.conf
	cat ${FILE} | grep "^\[" | cut -d[ -f 2 | cut -d] -f 1 | while read section; do 
		# Get the path variable in the specified section:
		DIR=$(sed -nr "/\[$section\]/,/\[/{/^path=/p}" ${FILE} | cut -d= -f 2)

		# If the "path" line exists within the section, but the specified path doesn't
		# exist, then remove the section from the configuration file:
		[[ ! -z "${DIR}" && ! -d ${DIR} ]] && remove_share ${DIR}
	done
}

function remove_share()
{
	test -f /tmp/smb.conf && rm /tmp/smb.conf
	WRITE=true
	while read line; do
		if [[ "${line}" =~ ^\[ ]]; then [[ "${line}" == "[$1]" ]] && WRITE=false || WRITE=true; fi
		[[ "${WRITE}" == "true" ]] && echo $line >> /tmp/smb.conf
	done < /etc/samba/smb.conf
	mv /tmp/smb.conf /etc/samba/smb.conf			
}

case "$1" in
	########################################################################
	"mount")
		check_params $2
		usb_mount $2
		;;
	########################################################################
	"umount")
		check_params $2
		usb_umount $2
		remove_invalid
		;;
	########################################################################
	"start")
		FILE=/etc/samba/smb.conf

		# Figure out which interfaces to bind to:
		cd /etc/network/interfaces.d
		IFACES=($(grep "address" $(grep -L "masquerade" *) | cut -d: -f 1))
		IFACES="${IFACES[@]}"
		sed -i "s|interfaces = .*$|interfaces = ${IFACES}|" ${FILE}

		# ADDED FUNCTION: Add the WebUI samba share if requested in "/boot/persistent.conf":
		test -f /boot/persistent.conf && source /boot/persistent.conf
		if ! grep -q "^\[router\]" /etc/samba/smb.conf; then
			[[ "${WEBUI_SHARE:=n}" == "y" ]] && samba_share router /home/pi|/opt/bpi-r2-router-builder/router
		elif [[ "${WEBUI_SHARE:=n}" == "n" ]]; then
			remove_share router
		fi

		# HACK: Remove all non-existant USB shares, then add anything missing:
		remove_invalid
		;;

	*)
		echo "Syntax: usb-helper.sh [start|mount|umount]"
		;;
esac
