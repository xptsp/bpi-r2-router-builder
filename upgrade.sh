#!/bin/bash
if [[ "$UID" -ne 0 ]]; then
	sudo $0 $@
	exit $?
fi
RED='\033[1;31m'
GREEN='\033[1;32m'
BLUE='\033[1;34m'
NC='\033[0m'

#####################################################################################
# Assuming the GIT command is available upon script execution, force a complete
# reset of the files and webui parts of the repository and pull any updated files:
#####################################################################################
cd $(dirname $0)
GIT=($(whereis git | cut -d":" -f 2))
if [[ ! -z "${GIT[@]}" && -d $(dirname $0)/.git ]]; then
	find . -type f | grep -v git | while read file; do rm $file; done
	git reset --hard
	if ! git pull; then echo "ERROR"; exit; fi
	chown pi:pi -R router
fi

#####################################################################################
# Copy files to the boot partition ONLY IF MOUNTED!
#####################################################################################
RW=($(mount | grep " /boot " 2> /dev/null))
if [[ ! -z "${RW[5]}" ]]; then
	FILE=/boot/bananapi/bpi-r2/linux/uEnv.txt
	if test -f ${FILE}; then
		[[ "${RW[5]}" == *ro,* ]] && mount -o remount,rw /boot
		BOOTDEFAULT=$(cat ${FILE} | grep "bootmenu_default=" | cut -d"=" -f 2)
		KERNEL=$(cat ${FILE} | grep "kernel=" | cut -d"=" -f 2)
		cp uEnv.txt ${FILE}
		sed -i "s|bootmenu_default=.*|bootmenu_default=${BOOTDEFAULT}|g" ${FILE}
		sed -i "s|kernel=.*|kernel=${KERNEL}|g" ${FILE}
		[[ "${RW[5]}" == *ro,* ]] && mount -o remount,ro /boot
	fi
fi

#####################################################################################
# Call rest of upgrade script from "misc" folder:
#####################################################################################
source misc/upgrade-helper.sh
echo "OK"