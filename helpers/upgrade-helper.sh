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
QUIET=false
PFL=/var/local/bpiwrt-builder.filelist
TFL=/tmp/bpiwrt-builder.filelist

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
		[[ "${DEST}" == "${MATCH}"* && "${DEST}" != "/etc/dnsmasq.d/"[0-9]* ]] && COPY=true
	done
	mkdir -p $(dirname ${DEST})
	if [[ "${COPY}" == "true" ]]; then
		if [[ "${SKIP_COPY}" == "false" ]]; then
			[[ "${FORCE_COPY}" == "true" ]] && rm "${DEST}" 2> /dev/null
			if ! test -f ${DEST}; then
				[[ "${QUIET}" == "false" ]] && echo -e -n "Copying ${BLUE}${DEST}${NC}... "
				if ! cp ${SRC} ${DEST}; then
					[[ "${QUIET}" == "false" ]] && echo -e "${RED}FAIL!${NC}"
				else
					[[ "${QUIET}" == "false" ]] && echo -e "${GREEN}Success!${NC}"
				fi
			fi
		fi
	else
		echo "${DEST}" >> ${LNEW}
		cat ${LOLD} | grep -v "^${DEST}$" | tee ${LOLD} >& /dev/null
		INFO=$(ls -l ${DEST} 2> /dev/null | awk '{print $NF}')
		if [[ ! "${INFO}" == "${SRC}" ]]; then
			rm ${DEST} >& /dev/null
			[[ "${QUIET}" == "false" ]] && echo -e -n "Linking ${BLUE}${DEST}${NC}... "
			if ! ln -sf ${SRC} ${DEST}; then
				[[ "${QUIET}" == "false" ]] && echo -e "${RED}FAIL!${NC}"
			else
				[[ "${QUIET}" == "false" ]] && echo -e "${GREEN}Success!${NC}"
			fi
		fi
	fi
	echo $DEST >> ${PFL}
	cat ${TFL} | grep -ve "^${DEST}$" | tee ${TFL} >& /dev/null
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
		
		-q|--quiet)
			QUIET=true
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
test -f ${PFL} || touch $PFL
mv ${PFL} ${TFL}
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
# Remove any files listed within the old file list:
#####################################################################################
for DEST in $(cat $TFL); do 
	[[ "${QUIET}" == "false" ]] && echo -e "Removing ${BLUE}${DEST}${NC}... "
	if [[ "$DEST" =~ /etc/systemd/system/ ]]; then
		systemctl disable --now $(basename $DEST)
	fi
	rm ${DEST}
done

#####################################################################################
# Perform same operations in the read-only partition:
#####################################################################################
RW=($(mount | grep " /ro " 2> /dev/null))
if [[ ! -z "${RW[5]}" ]]; then
	#####################################################################################
	# Reload the system daemons and enable any services deemed necessary by the script:
	#####################################################################################
	systemctl daemon-reload
	if ! systemctl is-enabled cloudflared@1 >& /dev/null; then
		systemctl enable --now cloudflared@1
		systemctl enable --now cloudflared@2
		systemctl enable --now cloudflared@3
	fi

	#####################################################################################
	# Copy the toolkit into the read-only partition and perform upgrade:
	#####################################################################################
	[[ "${RW[5]}" == *ro,* ]] && mount -o remount,rw /ro
	rm -rf /ro/opt/bpi-r2-router-builder
	cp -R /opt/bpi-r2-router-builder /ro/opt/bpi-r2-router-builder
	chroot /ro /opt/bpi-r2-router-builder/upgrade.sh -f
	[[ "${RW[5]}" == *ro,* ]] && mount -o remount,ro /ro
fi
