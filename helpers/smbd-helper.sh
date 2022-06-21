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
	MEDIA=$(blkid ${DEV} -o export | grep "LABEL=" | cut -d"=" -f 2)
	LABEL=${MEDIA:="${1}"}
	MEDIA=/media/"${LABEL// /_}"
	/usr/bin/pmount --umask 000 ${DEV} ${MEDIA} && samba_share ${LABEL} ${MEDIA} ${1}
}

function samba_share()
{
	# Write Samba configuration for the device:
	test -x /usr/bin/smbcontrol && cat << EOF > /etc/samba/smb.d/${1}.conf
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

function add_shares()
{
	test -x /usr/bin/smbcontrol || exit 0

	# Include Samba share in the configuration:
	ls /etc/samba/smb.d/* 2> /dev/null | sed -e 's/^/include = /' > /etc/samba/includes.conf

	# Reload the Samba configuration:
	smbcontrol all reload-config
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

function remove_shares()
{
	# Write Samba configuration for the device:
	if ls /etc/samba/smb.d/*.conf >& /dev/null; then
		for conf in /etc/samba/smb.d/*.conf; do
			DIR=$(cat ${conf} | grep "path=" | cut -d"=" -f 2)
			if [[ "${DIR}" =~ ^/media ]]; then
				mount | grep ${DIR} >& /dev/null || rm -rf ${DIR}
				test -d "${DIR}" || rm ${conf}
			fi
		done
	fi
}

case "$1" in
	########################################################################
	"mount")
		check_params $2
		usb_mount $2
		add_shares
		;;
	########################################################################
	"umount")
		check_params $2
		usb_umount $2
		remove_shares
		add_shares
		;;
	########################################################################
	"start")
		# Figure out which interfaces to bind to:
		cd /etc/network/interfaces.d
		IFACES="$(grep "address" $(grep -L "masquerade" *) | cut -d: -f 1)"
		sed -i "s|interfaces = .*$|interfaces = ${IFACES}|" ${FILE}

		# Make sure that the include line is at the top of the "smb.conf" file:
		FILE=/etc/samba/smb.conf
		grep -q -e "include = /etc/samba/includes.conf" ${FILE} || sed -i "1s|^|include = /etc/samba/includes.conf\n\n|" ${FILE}

		# ADDED FUNCTION: Add the WebUI samba share if requested in "/boot/persistent.conf":
		test -f /boot/persistent.conf && source /boot/persistent.conf
		if ! test -f /etc/samba/smb.d/webui.conf; then
			if [[ "${WEBUI_SHARE:=n}" == "y" ]]; then
				sed "s|\[pi\]|\[router\]|g" /etc/samba/smb.d/pi.conf > /etc/samba/smb.d/webui.conf
				sed -i "s|comment=.*|comment=WebUI folder|g" /etc/samba/smb.d/webui.conf
				sed -i "s|/home/pi|/opt/bpi-r2-router-builder/router|g" /etc/samba/smb.d/webui.conf
			fi
		elif [[ "${WEBUI_SHARE:=n}" == "n" ]]; then
			rm /etc/samba/smb.d/webui.conf
		fi

		# HACK: Remove all non-existant USB shares, then add anything missing:
		remove_shares
		add_shares
		;;

	*)
		echo "Syntax: usb-helper.sh [start|mount|umount]"
		;;
esac
