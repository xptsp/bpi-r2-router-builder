#!/bin/bash
if [[ "$UID" -ne 0 ]]; then
	sudo $0 $@
	exit $?
fi
RED='\033[1;31m'
GREEN='\033[1;32m'
BLUE='\033[1;34m'
NC='\033[0m'
LORG=/var/opt/router-builder/bpi-r2-router-builder.list
LOLD=/tmp/builder.old
LNEW=/tmp/builder.new
FORCE_COPY=false
SKIP_COPY=false

#####################################################################################
# Prepare for logging files that have been linked:
#####################################################################################
cd $(dirname $0)
touch ${LOLD}
test -f ${LORG} && cp ${LORG} ${LOLD}
touch ${LNEW}

#####################################################################################
# Assuming the GIT command is available upon script execution, force a complete
# reset of the files and webui parts of the repository and pull any updated files:
#####################################################################################
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
	[[ "${RW[5]}" == *ro,* ]] && mount -o remount,rw /boot
	BOOTDEFAULT=$(cat /boot/bananapi/bpi-r2/linux/uEnv.txt | grep "bootmenu_default=" | cut -d"=" -f 2)
	KERNEL=$(cat /boot/bananapi/bpi-r2/linux/uEnv.txt | grep "kernel=" | cut -d"=" -f 2)
	cp uEnv.txt /boot/bananapi/bpi-r2/linux/
	sed -i "s|bootmenu_default=.*|bootmenu_default=${BOOTDEFAULT}|g" /boot/bananapi/bpi-r2/linux/uEnv.txt
	sed -i "s|kernel=.*|kernel=${KERNEL}|g" /boot/bananapi/bpi-r2/linux/uEnv.txt
	[[ "${RW[5]}" == *ro,* ]] && mount -o remount,ro /boot
fi

#####################################################################################
# Call rest of upgrade script from "misc" folder:
#####################################################################################
source misc/upgrade-helper.sh
echo "OK"