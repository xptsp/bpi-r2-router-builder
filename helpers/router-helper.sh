#!/bin/bash
if [[ "${UID}" -ne 0 ]]; then
	sudo $0 $@
	exit $?
fi

###########################################################################
# Supporting functions
###########################################################################
# Check to make sure that a read-only filesystem exists, and diagnose issue if it doesn't:
function check_ro()
{
	if ! test -d /ro; then
		if [[ "$(cat /etc/debian_chroot)" == "CHROOT" ]]; then
			echo "ERROR: Already in chroot environment!"
			exit 1
		elif ! test -e /boot/bananapi/bpi-r2/linux/uEnv.txt; then
			echo "ERROR: uEnv.txt file is missing."
			echo "Copy '/opt/bpi-r2-router-builder/uEnv.txt' to \"/boot/bananapi/bpi-r2/linux/uEnv.txt\" to enable."
			exit 1
		fi
		DEF=$(cat /boot/bananapi/bpi-r2/linux/uEnv.txt | grep bootmenu_default | cut -d= -f 2)
		if [[ "$DEF" != "2" ]]; then
			echo "ERROR: Overlay script has been disabled."
			echo "Change \"bootmenu_default\" to \"2\" in order to enable readonly overlay script."
		else
			echo "ERROR: Readonly filesystem not available!"
		fi
		exit 1
	fi
}

# Remount readonly lower filesystem as writable:
function remount_rw()
{
	if mount | grep -e "^$RO_DEV" | grep "rw" >& /dev/null; then
		echo "ERROR: Root filesystem already mounted read-write!"
		exit 1
	fi
	if ! mount -o remount,rw $RO_DEV /ro; then
		echo "ERROR: Unable to remount root filesystem!"
		exit 1
	fi
	if [[ ! "$1" == "notrap" ]]; then
		trap 'remount_ro' SIGINT
		trap 'remount_ro' EXIT
	fi
	mount --bind /dev /ro/dev
	mount --bind /run /ro/run
	mount --bind /proc /ro/proc
	mount --bind /sys /ro/sys
	mount --bind /tmp /ro/tmp
	return 0
}

# Remount writable lower filesystem as readonly:
function remount_ro()
{
	umount /ro/tmp >& /dev/null
	umount /ro/sys >& /dev/null
	umount /ro/proc >& /dev/null
	umount /ro/run >& /dev/null
	umount /ro/dev >& /dev/null
	mount -o remount,ro $RO_DEV /ro
}

# Function to ask whether to do a particular action:
# Src: https://stackoverflow.com/a/31939275
function askYesNo {
	QUESTION=$1
	DEFAULT=$2
	if [ "$DEFAULT" = true ]; then
		OPTIONS="[Y/n]"
		DEFAULT="y"
	else
		OPTIONS="[y/N]"
		DEFAULT="n"
	fi
	read -p "$QUESTION $OPTIONS " -n 1 -s -r INPUT
	INPUT=${INPUT:-${DEFAULT}}
	echo ${INPUT}
	ANSWER=false
	[[ "$INPUT" =~ ^[yY]$ ]] && ANSWER=true
}

function valid_ip()
{
    local  ip=$1
    local  stat=1

    if [[ $ip =~ ^[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}$ ]]; then
        OIFS=$IFS
        IFS='.'
        ip=($ip)
        IFS=$OIFS
        [[ ${ip[0]} -le 255 && ${ip[1]} -le 255 && ${ip[2]} -le 255 && ${ip[3]} -le 255 ]]
        stat=$?
    fi
    return $stat
}

###########################################################################
# Main code
###########################################################################
RO_DEV=$(test -f /ro/etc/fstab && cat /ro/etc/fstab | grep " / " | sed "s|^#||g" | cut -d" " -f 1)
CMD=$1
shift
case $CMD in
	###########################################################################
	chroot)
		check_ro
		remount_rw
		echo "CHROOT" > /tmp/debian_chroot
		mount --bind /tmp/debian_chroot /ro/etc/debian_chroot
		chroot /ro $@
		umount /ro/etc/debian_chroot >& /dev/null
		rm /tmp/debian_chroot
		remount_ro || echo "Setting RO Failed"
		;;

	###########################################################################
	remount)
		check_ro
		if [[ "$1" == "rw" ]]; then
			remount_rw notrap
		elif [[ "$1" == "ro" ]]; then
			remount_ro
		else
			echo "SYNTAX: $(basename $0) remount [ro|rw]"
		fi
		;;

	###########################################################################
	reformat)
		check_ro
		if [[ ! -z "$2" && ! "$2" =~ -(y|-yes) ]]; then
			echo "SYNTAX: $(basename $0) reformat [-y|--yes]"
			exit 1
		fi
		if [[ ! "$2" =~ -(y|-yes) ]]; then
			echo "WARNING: The router will reboot and persistent storage will be formatted.  This action cannot be undone!"
			askYesNo "Are you SURE you want to do this?" || exit 0
		fi
		remount_rw
		sed -i "s|^SECONDARY_REFORMAT=.*|SECONDARY_REFORMAT=yes|g" /ro/etc/overlayRoot.conf
		reboot now
		;;

	###########################################################################
	overlay)
		if [[ "$1" == "enable" || "$1" == "disable" ]]; then
			FILE=/boot/bananapi/bpi-r2/linux/uEnv.txt
			OLD=$(cat ${FILE} | grep bootmenu_default | cut -d= -f 2)
			NEW=$([[ "$1" == "enable" ]] && echo "2" || echo "3")
			TXT=$([[ "$NEW" == "2" ]] && echo "enabled" || echo "disabled")
			[[ "$OLD" == "$NEW" ]] && echo "INFO: Overlay script already ${TXT}!" && exit
			RO=$(mount | grep "/boot" | grep "(ro,")
			[[ ! -z "$RO" ]] && mount -o remount,rw /boot
			sed -i "s|bootmenu_default=.*|bootmenu_default=${NEW}|g" ${FILE}
			[[ ! -z "$RO" ]] && mount -o remount,ro /boot
			echo "Overlay Root script ${TXT} for next reboot!"
		elif [[ "$1" == "status" ]]; then
			STAT=$(cat /boot/bananapi/bpi-r2/linux/uEnv.txt | grep "bootmenu_default=2" >& /dev/null && echo "enabled" || echo "disabled")
			echo "Overlay Root script is ${STAT}."
		else
			echo "SYNTAX: $(basename $0) overlay [enable|disable|status]"
		fi
		;;

	###########################################################################
	apt)
		/usr/bin/apt $@
		;;

	###########################################################################
	login)
		if [[ "$1" == "check" ]]; then
			[[ -z "${2}" || -z "${3}" ]] && echo "No match" && exit 1
			pwd=$(getent shadow ${2} | cut -d: -f2)
			salt=\$$(echo $pwd | cut -d$ -f2)\$$(echo $pwd | cut -d$ -f3)
			[ "$(python -c 'import crypt; print crypt.crypt("'"${3}"'", "'${salt}'")')" == "${pwd}" ] && echo "Match" || echo "No match"
		elif [[ "$1" == "webui" ]]; then
			echo $(cat /etc/passwd | grep ":1000:" | cut -d: -f1)
		elif [[ "$1" == "passwd" ]]; then
			[[ -z "${2}" ]] && echo "Password not specified" && exit 1
			(echo $2; echo $2) | passwd $(cat /etc/passwd | grep ":1000:" | cut -d: -f1)
		elif [[ "$1" == "username" ]]; then
			[[ -z "${2}" ]] && echo "Username not specified" && exit 1
			usermod -l $2 $(cat /etc/passwd | grep ":1000:" | cut -d: -f1) && echo "Success"
		fi
		;;

	###########################################################################
	security-check)
		[[ "$($0 login check $($0 login webui) bananapi)" == "Match" ]] && echo "Default"
		[[ "$($0 login check root bananapi)" == "Match" ]] && echo "Root"
		mount | grep -e "[emergency|tmp]-root-rw on /rw " >& /dev/null && echo "Temp"
		;;

	###########################################################################
	dhcp-info)
		bound=($(cat /var/log/syslog* | grep dhclient | grep bound | sort | tail -1))
		from=($(cat /var/log/syslog* | grep dhclient | grep from | sort | tail -1))
		[[ -z "${from[-1]}" ]] && exit
		[[ -z "${bound[-2]}" ]] && exit
		echo ${from[-1]} ${bound[0]} ${bound[1]} ${bound[2]} ${bound[-2]}
		;;

	###########################################################################
	reboot)
		/sbin/reboot now
		;;

	###########################################################################
	hostname)
		OLD_HOST=$(hostname)
		ORIG="$(grep ${OLD_HOST} /etc/hosts)"
		REPL="${ORIG//${OLD_HOST}/${1}}"
		sed -i "s|^${ORIG}\$|${REPL}|g" /etc/hosts
		echo "$1" > /etc/hostname
		/bin/hostname $1
		echo "OK"
		;;

	###########################################################################
	git)
		cd /opt/${2:-"bpi-r2-router-builder"}
		if [[ "$1" == "current" ]]; then
			git log -1 --format="%at"
		elif [[ "$1" == "remote" ]]; then
			git remote update >& /dev/null
			git log -1 --format="%at" origin/master
		elif [[ "$1" == "update" ]]; then
			if [[ "$2" == "wireless-regdb" ]]; then
				/opt/bpi-r2-router-builder/misc/wireless-regdb.sh
			else
				$PWD/upgrade.sh
			fi
		fi
		;;

	###########################################################################
	backup)
		if [[ "$1" == "create" ]]; then
			ftb=($(cat /etc/default/backup_file.list))
			cd /tmp
			md5sum ${ftb[@]} |sed "s|  /|  |g" > md5sum
			test -f /tmp/bpiwrt.cfg && rm /tmp/bpiwrt.cfg
			tar -cJf /tmp/bpiwrt.cfg md5sum ${ftb[@]} >& /dev/null
		elif [[ "$1" == "remove" ]]; then
			rm /tmp/bpiwrt.cfg
		elif [[ "$1" == "unpack" ]]; then
			rm -rf /tmp/bpiwrt
			mkdir -p /tmp/bpiwrt
			cd /tmp/bpiwrt
			if ! tar -xJf /tmp/bpiwrt.cfg; then echo "ERROR: Invalid settings file!"; exit; fi
			if md5sum -c md5sum 2> /dev/null | grep FAILED >& /dev/null; then echo "ERROR: Checksum Failure"; exit; fi
		elif [[ "$1" == "restore" ]]; then
			if ! test -d /tmp/bpiwrt; then echo "ERROR: Backup has not been unpacked!"; exit; fi
			cd /tmp/bpiwrt
			if md5sum -c md5sum 2> /dev/null | grep FAILED >& /dev/null; then echo "ERROR: Checksum Failure"; exit; fi
			while IFS= read -r line; do mv ${line:1} $(dirname $line)/; done < etc/default/backup_file.list
		fi
		;;

	###########################################################################
	net_config)
		if ! ifconfig ${1} >& /dev/null; then echo "ERROR: Invalid adapter specified"; exit; fi
		if ! test -f /tmp/${1}; then echo "ERROR: Missing Configuration File"; exit; fi
		mv /tmp/${1} /etc/network/interfaces.d/${1}
		;;

	###########################################################################
	rem_config)
		rm /etc/network/interfaces.d/${1}
		;;

	###########################################################################
	systemctl)
		systemctl $@
		;;

	###########################################################################
	mac)
		CUR=($(ifconfig wan | grep ether))
		MAC=$1
		[[ "$MAC" == "saved" ]] && MAC=$(cat /boot/eth0.conf 2> /dev/null)
		if [[ -z "$MAC" || "$MAC" == "current" ]]; then
			MAC=${CUR[1]}
			echo "INFO: Using MAC Address: $MAC"
		elif [[ "$MAC" == "random" ]]; then
			MAC=$(printf '%01X2:%02X:%02X:%02X:%02X:%02X\n' $[RANDOM%16] $[RANDOM%256] $[RANDOM%256] $[RANDOM%256] $[RANDOM%256] $[RANDOM%256])
			echo "INFO: Using MAC Address: $MAC"
		elif [[ ! "$MAC" =~ ^([a-fA-F0-9]{2}:){5}[a-fA-F0-9]{2}$ ]]; then
			echo "ERROR: Invalid MAC address specified!"
			exit 1
		fi
		dtc -q -O dts /boot/bananapi/bpi-r2/linux/dtb/bpi-r2.dtb > /tmp/dts
		MAC=${MAC,,}
		LINE="$(grep "mac-address \= \[" /tmp/dts)"
		OLD=$(echo ${LINE// /:} | cut -d: -f 4-9)
		if [[ "${OLD,,}" == "${MAC}" ]]; then
			echo "INFO: Same MAC address.  Exiting!"
			exit
		fi		
		if [[ ! -z "${OLD}" ]]; then
			sed -i "s|mac-address = \[.*\]|mac-address = [ ${MAC//:/ } ]|g" /tmp/dts
		else
			sed -i "s|mediatek,eth-mac\"|mediatek,eth-mac\";\n\t\t\t\tmac-address = [ ${MAC//:/ } ]|g" /tmp/dts
		fi
		RO=$(mount | grep "/boot" | grep "(ro,")
		[[ ! -z "$RO" ]] && mount -o remount,rw /boot
		echo $MAC > /boot/eth0.conf
		dtc -q -O dtb /tmp/dts > /boot/bananapi/bpi-r2/linux/dtb/bpi-r2.dtb
		[[ ! -z "$RO" ]] && mount -o remount,ro /boot
		echo "REBOOT"
		;;

	###########################################################################
	*)
		echo "Syntax: $(basename $0) [command] [options]"
		;;
esac
