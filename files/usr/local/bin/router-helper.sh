#!/bin/bash
if [[ "${UID}" -ne 0 ]]; then
	sudo $0 $@
	exit $?
fi

###########################################################################
# Functions & code that dealing directly with remounting root filesystem
# and preparation for chroot operations
###########################################################################
function remount_rw()
{
	[[ "$1" == "nofail" ]] && ! test -d /ro && return
	if ! test -d /ro; then
		if [[ "$(cat /etc/debian_chroot)" == "CHROOT" ]]; then
			echo "Already in chroot environment!"
		elif ! cat /boot/bananapi/bpi-r2/linux/uEnv.txt | grep "=/sbin/overlayRoot.sh" >& /dev/null; then
			echo "ERROR: Overlay script line missing.  Add 'bootopts=init=/sbin/overlayRoot.sh' to \"/boot/bananapi/bpi-r2/linux/uEnv.txt\" to enable."
		elif cat /boot/bananapi/bpi-r2/linux/uEnv.txt | grep "noOverlayRoot$" >& /dev/null; then
			echo "ERROR: Overlay script has been disabled.  Remove \"noOverlayRoot\" from \"/boot/bananapi/bpi-r2/linux/uEnv.txt\" to reenable."
		else
			echo "ERROR: Readonly filesystem not available!"
		fi
		exit 1
	fi
	if ! mount -o remount,rw $(cat /ro/etc/fstab | grep " / " | cut -d" " -f 1) /ro; then
		echo "ERROR: Unable to properly remount root filesystem!"
		exit 1
	fi
}
function remount_ro()
{
	test -d /ro && mount -o remount,ro $RO_DEV /ro
}
trap 'remount_ro' SIGINT

###########################################################################
# Main branching code
###########################################################################
case $1 in
	mount_ro)
		remount_ro

	chroot_ro|chroot-ro)
		remount_rw
		echo "CHROOT" > /tmp/debian_chroot
		mount --bind /tmp/debian_chroot /ro/etc/debian_chroot
		chroot /ro
		umount /ro/etc/debian_chroot
		rm /tmp/debian_chroot
		remount_ro
		;;

	update_tools|update-tools)
		remount_rw nofail
		DIR=$(test -d /ro && echo "/ro")/opt/bpi-r2-router-builder
		cd ${DIR}
		git reset --hard
		git pull
		${DIR}/upgrade.sh
		remount_ro
		;;

	*)
		echo "Syntax: $(basename $0) [command] [options]"
		;;
esac
