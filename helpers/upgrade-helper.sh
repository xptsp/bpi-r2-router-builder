#!/bin/bash
#############################################################################
# This helper script takes care of copying and linking any files that are
# in the bpiwrt-builder repo to their respective locations.
#
# TODO:
# * Old files that have disappeared from the repo need to be removed
#############################################################################
if [[ "$UID" -ne 0 ]]; then
	sudo $0 $@
	exit $?
fi

RED='\033[1;31m'
GREEN='\033[1;32m'
BLUE='\033[1;34m'
NC='\033[0m'
FORCE_COPY=false
SKIP_COPY=false

#####################################################################################
# Files to copy only:
#####################################################################################
COPY_ONLY=(
	/etc/hosts.adblock
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
	/home/pi/
	/home/vpn/
	/etc/skel/
	/etc/apt/sources.list
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
	if [[ "${COPY}" == "true" ]]; then
		if [[ "${SKIP_COPY}" == "false" ]]; then
			CUR=$([[ -e "${DEST}" ]] || echo 0 && date -r ${DEST} "+%s")
			NEW=$(date -r ${SRC} "+%s")
			if [[ ${NEW} -gt ${CUR} ]]; then
				echo -e -n "Copying ${BLUE}${DEST}${NC}... "
				[[ "${FORCE_COPY}" == "true" ]] && rm "${DEST}" >& /dev/null
				if ! test -f "${DEST}"; then
					if ! cp -u ${SRC} ${DEST}; then
						echo -e "${RED}FAIL!${NC}"
					else
						echo -e "${GREEN}Success!${NC}"
					fi
				else
					echo -e "${GREEN}Skipped${NC}"
				fi
			fi
		fi
	else
		echo "${DEST}" >> ${LNEW}
		cat ${LOLD} | grep -v "^${DEST}$" | tee ${LOLD} >& /dev/null
		INFO=$(ls -l ${DEST} 2> /dev/null | awk '{print $NF}')
		if [[ ! "${INFO}" == "${SRC}" ]]; then
			rm ${DEST} >& /dev/null
			echo -e -n "Linking ${BLUE}${DEST}${NC}... "
			if ! ln -sf ${SRC} ${DEST}; then
				echo -e "${RED}FAIL!${NC}"
			else
				echo -e "${GREEN}Success!${NC}"
			fi
		fi
	fi
}

#####################################################################################
# Process all command-line arguments:
#####################################################################################
for i in "$@"; do
	case $i in
		-f|--force-copy)
			FORCE_COPY=true
			;;
		
		-s|--skip-copy)
			SKIP_COPY=true
			;;
	esac
done

#####################################################################################
# Copy or link files in the repo to their proper locations:
#####################################################################################
if ! cd files; then
	echo "ERROR: Something went really wrong!  Aborting!!"
	exit
fi
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
	replace $file etc/skel/${file/root\//}
	replace $file home/pi/${file/root\//}
	replace $file home/vpn/${file/root\//}
done
chmod +x /home/{pi,vpn}/.bash* /etc/skel/{.bash*,.profile}

#####################################################################################
# Perform same operations in the read-only partition:
#####################################################################################
RW=($(mount | grep " /ro "))
if [[ ! -z "${RW[5]}" ]]; then
	[[ "${RW[5]}" == *ro,* ]] && mount -o remount,rw /ro
	chroot /ro /opt/bpi-r2-router-builder/upgrade.sh -f
	[[ "${RW[5]}" == *ro,* ]] && mount -o remount,ro /ro
fi

