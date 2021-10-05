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
	# Use the pmount command to mount to a directory with the volume label.
	# Failing that, mount to the device name.
	DEV=/dev/${1}
	/usr/bin/pumount ${DEV}
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
	"start")
		check_params $2
		usb_mount $2
		add_shares
		;;
	"stop")
		check_params $2
		usb_umount $2
		remove_shares
		add_shares
		;;
	"prep")
		remove_shares
		add_shares
		;;
	*)
		echo "Syntax: usb-helper.sh [start|stop|prep]"
		;;
esac
