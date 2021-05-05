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
# Files to copy only:
#####################################################################################
COPY_ONLY=(
	/etc/network/interfaces.d/
	/etc/dnsmasq.d/
	/etc/hostapd/
	/etc/fstab
	/etc/rc.local
	/etc/transmission-daemon/settings.json
	/etc/default/
	/etc/overlayRoot.conf
	/etc/pihole/
	/etc/pivpn/
	/root/
)

#####################################################################################
# Function that deals with copying and/or linking individual files:
#####################################################################################
function replace()
{
	DEST=/${2:-"$1"}
	COPY=false
	SRC=$(echo ${PWD}/$1 | sed "s|/ro/|/|g")
	for MATCH in ${COPY_ONLY[@]}; do 
		[[ "${DEST}" == "${MATCH}"* ]] && COPY=true
	done
	mkdir -p $(dirname ${DEST})
	if [[ "$COPY" == "true" ]]; then
		if ! test -f ${DEST}; then
			echo -e -n "Copying ${BLUE}${SRC}${NC}... "
			if ! cp ${SRC} ${DEST}; then
				echo -e "${RED}FAIL!${NC}"
			else
				echo -e "${GREEN}Success!${NC}"
			fi
		fi
	else
		INFO=$(ls -l /${DEST} | awk '{print $NF}')
		if [[ ! "${INFO}" == "${SRC}" ]]; then
			rm ${DEST}
			echo -e -n "Linking ${BLUE}${SRC}${NC}... "
			if ! ln -sf ${SRC} ${DEST}; then
				echo -e "${RED}FAIL!${NC}"
			else
				echo -e "${GREEN}Success!${NC}"
			fi
		fi
	fi
}

#####################################################################################
# Force a complete reset of the repository and pull any updated files:
#####################################################################################
cd $(dirname $0)
rm -rf router
rm -rf files
git reset --hard
git pull
# Make user "pi" owner of the router UI
chown pi:pi -R router
systemctl daemon-reload
systemctl restart smbd

#####################################################################################
# Copy files to the boot partition ONLY IF MOUNTED!
#####################################################################################
RW=($(mount | grep " /boot "))
if [[ ! -z "$RW" ]]; then
	[[ "${RW[5]}" == *ro,* ]] && mount -o remount,rw /boot
	cp uEnv.txt /boot/bananapi/bpi-r2/linux/
	[[ "${RW[5]}" == *ro,* ]] && mount -o remount,ro /boot
fi

#####################################################################################
# Copy or link files in the repo to their proper locations:
#####################################################################################
cd files
for dir in $(find ./ -maxdepth 1 -type d | grep -v "./root"); do 
	DIR=${dir/.\//};
	if [[ ! -z "${DIR}" ]]; then
		for file in $(find ${DIR}/* -type f | grep -v -e "^lib/systemd/system/"); do replace $file; done
	fi
done

#####################################################################################
# Link the service file changes into "/lib/systemd/system":
#####################################################################################
for file in $(find lib/systemd/system/* -type d); do replace $file; done

#####################################################################################
# Link bash config files into "/root", "/etc/skel", "/home/pi" and "/home/vpn":
#####################################################################################
for file in $(find root/.[a-z]* -type f); do
	replace $file
	replace $file /etc/skel/${file/root/}
	replace $file /home/pi/${file/root/}
	replace $file /home/vpn/${file/root/}
done
