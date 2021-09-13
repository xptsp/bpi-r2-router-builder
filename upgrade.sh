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
	rm -rf router
	rm -rf files
	git reset --hard
	git pull
	# Make user "pi" owner of the router UI
	chown pi:pi -R router
	if [[ $(ischroot; echo $?) -ne 1 ]]; then
		systemctl daemon-reload
		systemctl restart smbd
	fi
fi

#####################################################################################
# Copy files to the boot partition ONLY IF MOUNTED!
#####################################################################################
RW=($(mount | grep " /boot "))
if [[ ! -z "${RW[5]}" ]]; then
	[[ "${RW[5]}" == *ro,* ]] && mount -o remount,rw /boot
	cp uEnv.txt /boot/bananapi/bpi-r2/linux/
	[[ "${RW[5]}" == *ro,* ]] && mount -o remount,ro /boot
fi

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
chmod +x /home/{pi,vpn}/{.bash*,.profile} /etc/skel/{.bash*,.profile}

#####################################################################################
# Move new linked file list to log directory and remove unnecessary linked files:
#####################################################################################
mkdir -p $(dirname ${LORG})
mv ${LNEW} ${LORG}
for file in $(cat ${LOLD}); do 
	test -f ${file} && rm ${file}
done
rm ${LOLD}

#####################################################################################
# Perform same operations in the read-only partition:
#####################################################################################
RW=($(mount | grep " /ro "))
if [[ ! -z "${RW[5]}" ]]; then
	[[ "${RW[5]}" == *ro,* ]] && mount -o remount,rw /ro
	chroot /ro /opt/bpi-r2-router-builder/upgrade.sh
	[[ "${RW[5]}" == *ro,* ]] && mount -o remount,ro /ro
fi

