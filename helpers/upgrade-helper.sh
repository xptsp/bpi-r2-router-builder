#!/bin/bash
#############################################################################
# This helper script takes care of copying and linking any files that are
# in the bpiwrt-builder repo to their respective locations.
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
	/etc/network/interfaces.d/*
	/etc/dnsmasq.d/*
	/etc/hostapd/*
	/etc/fstab
	/etc/tcmount.ini
	/etc/rc.local
	/etc/default/*
	/etc/sysupgrade.conf
	/etc/overlayRoot.conf
	/etc/pihole/*
	/etc/pivpn/*
	/etc/systemd/system/*.service
	/etc/persistent-nftables.conf
	/root/.bash_aliases
	/root/.bash_logout
	/root/.bashrc
	/root/.ssh/authorized_keys
	/home/pi/*
	/home/vpn/*
	/etc/skel/*
	/etc/apt/sources.list
	/etc/ddclient.conf
)

#####################################################################################
# Function that deals with copying and/or linking individual files:
#####################################################################################
function replace()
{
	DEST=/${2:-"$1"}
	COPY=${3:-"false"}
	SRC=$(echo ${PWD}/$1 | sed "s|/ro/|/|g")
	for MATCH in ${COPY_ONLY[@]}; do 
		[[ "${DEST}" =~ ${MATCH} && "${DEST}" != "/etc/dnsmasq.d/"[0-9]* ]] && COPY=true
	done
	rm $(dirname ${DEST}) 2> /dev/null
	mkdir -p $(dirname ${DEST})
	if [[ "${COPY}" == "true" ]]; then
		if [[ "${SKIP_COPY}" == "false" ]]; then
			if [[ "${FORCE_COPY}" == "true" ]]; then
				MD5_OLD=$(md5sum ${SRC} 2> /dev/null | cut -d" " -f 1)
				MD5_NEW=$(test -f ${SRC} && md5sum ${DEST} | cut -d" " -f 1)
				[[ "${MD5_OLD}" != "${MD5_NEW}" ]] && test -f ${DEST} && rm "${DEST}" 2> /dev/null
			fi
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
	if [[ "${COPY}" == "false" ]]; then
		echo $DEST >> ${PFL}
		cat ${TFL} | grep -ve "^${DEST}$" | tee ${TFL} >& /dev/null
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

		-q|--quiet)
			QUIET=true
			;;
	esac
done

#####################################################################################
# Copy or link files in the repo to their proper locations:
#####################################################################################
if ! cd /opt/bpi-r2-router-builder/files; then
	echo "ERROR: Something went really wrong!  Aborting!!"
	exit
fi
test -f ${PFL} || touch $PFL
mv ${PFL} ${TFL}
find . -type f | grep -v "^./root" | while read file; do replace ${file/.\//}; done

#####################################################################################
# Link bash config files into "/root", "/etc/skel", "/home/pi" and "/home/vpn":
#####################################################################################
find root -type f | while read file; do
	replace $file
	replace $file etc/skel/${file/root\//}
	replace $file home/pi/${file/root\//}
	replace $file home/vpn/${file/root\//}
done
chmod 600 /root/.gnupg/* /home/{pi,vpn}/.gnupg/*
chmod 700 ~root/.gnupg /home/{pi,vpn}/.gnupg
chmod +x /home/{pi,vpn}/.bash* /etc/skel/{.bash*,.profile}

#####################################################################################
# Remove any files listed within the old file list:
#####################################################################################
for DEST in $(cat $TFL); do 
	[[ "${QUIET}" == "false" ]] && echo -e "Removing ${BLUE}${DEST}${NC}... "
	[[ "$DEST" =~ /(lib|etc)/systemd/system/ ]] && systemctl disable --now $(basename $DEST)
	test -f ${DEST} && rm ${DEST}
done

#####################################################################################
# Perform same operations in the read-only partition:
#####################################################################################
RW=($(mount | grep " /ro "))
if [[ ! -z "${RW[5]}" ]]; then
	#####################################################################################
	# Enable any services deemed necessary by the script:
	#####################################################################################
	systemctl daemon-reload
	systemctl is-enabled multicast-relay >& /dev/null || systemctl enable ${NOW} multicast-relay
	systemctl is-enabled wifi >& /dev/null || systemctl enable ${NOW} wifi

	#####################################################################################
	# Unwrite-protect the readonly root partition and perform upgrade of RO partition:  
	#####################################################################################
	[[ "${RW[5]}" == *ro,* ]] && NOW="--now" && mount -o remount,rw /ro 2> /dev/null
	chroot /ro /opt/bpi-r2-router-builder/upgrade.sh -f

	#####################################################################################
	# Add any files to the list of files to backup: 
	#####################################################################################
	CHK=/etc/sysupgrade.conf
	TMP=/tmp/sysupgrade.conf
	cat ${CHK} | uniq | sort > ${TMP}
	cat /ro/${CHK} | uniq | sort > ${TMP}.orig
	grep -Fxvf ${TMP} ${TMP}.orig | while read line; do echo $line >> ${CHK}; done
	rm ${TMP} ${TMP}.orig

	#####################################################################################
	# Replace default files as necessary:
	#####################################################################################
	cp -u ../misc/config/ddclient.conf /ro/etc/ddclient.conf 
	cp -u ../misc/config/hd-idle /ro/etc/default/hd-idle
	cp -u ../misc/config/multicast-relay /ro/etc/default/multicast-relay
	cp -u ../misc/config/pihole.conf /ro/etc/pihole/setupVars.conf
	cp -u ../misc/config/pihole-custom.list /ro/etc/pihole/custom.list
	cp -u ../misc/config/privoxy-blocklist.conf /ro/etc/privoxy/blocklist.conf
	cp -u ../misc/config/privoxy-config.conf /ro/etc/privoxy/config
	cp -u ../misc/config/squid.conf /ro/etc/squid/squid.conf
	cp -u ../misc/config/transmission-daemon /ro/etc/default/transmission-daemon
	cp -u ../misc/config/transmission.json /ro/home/vpn/.config/transmission-daemon/settings.json 

	#####################################################################################
	# Write-protect the readonly root partition:  
	#####################################################################################
	[[ "${RW[5]}" == *ro,* ]] && mount -o remount,ro /ro 2> /dev/null
fi
