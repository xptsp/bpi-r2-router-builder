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
		elif ! test -e /boot/bananapi/bpi-r2/linux/uEnv.txt; then
			echo "ERROR: uEnv.txt file is missing."
			echo "Add 'bootopts=init=/sbin/overlayRoot.sh' to \"/boot/bananapi/bpi-r2/linux/uEnv.txt\" to enable."
		elif ! cat /boot/bananapi/bpi-r2/linux/uEnv.txt | grep "=/sbin/overlayRoot.sh" >& /dev/null; then
			echo "ERROR: Overlay script line missing."
			echo "Add 'bootopts=init=/sbin/overlayRoot.sh' to \"/boot/bananapi/bpi-r2/linux/uEnv.txt\" to enable."
		elif cat /boot/bananapi/bpi-r2/linux/uEnv.txt | grep "noOverlayRoot$" >& /dev/null; then
			echo "ERROR: Overlay script has been disabled."
			echo "Remove or comment out \"noOverlayRoot\" from \"/boot/bananapi/bpi-r2/linux/uEnv.txt\" to reenable."
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
	umount /ro/etc/debian_chroot >& /dev/null
	test -e /tmp/debian_chroot && chattr -i /tmp/debian_chroot && rm /tmp/debian_chroot
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
# Function dealing with setting overlay mode:
###########################################################################
function setOverlay()
{
	if [[ "$1" == "enable" ]]; then
		if ! cat /boot/bananapi/bpi-r2/linux/uEnv.txt | grep "init=/sbin/overlayRoot.sh noOverlayRoot" >& /dev/null; then
			echo "INFO: Overlay root filesystem script already enabled!"
		else
			mount -o remount,rw /boot
			sed -i "s|init=/sbin/overlayRoot.sh|init=/sbin/overlayRoot.sh noOverlayRoot|g" /boot/bananapi/bpi-r2/linux/uEnv.txt
			mount -o remount,ro /boot
			if ! cat /boot/bananapi/bpi-r2/linux/uEnv.txt | grep "init=/sbin/overlayRoot.sh noOverlayRoot" >& /dev/null; then
				echo "ERROR: Unable to enable Overlay Root script!"
				exit 1
			else
				echo "Overlay Root script enabled for next reboot!"
			fi
		fi
	elif [[ "$1" == "disable" ]]; then
		if cat /boot/bananapi/bpi-r2/linux/uEnv.txt | grep "init=/sbin/overlayRoot.sh noOverlayRoot" >& /dev/null; then
			echo "INFO: Overlay root filesystem script already disabled!"
		else
			mount -o remount,rw /boot
			sed -i "s|init=/sbin/overlayRoot.sh noOverlayRoot|init=/sbin/overlayRoot.sh|g" /boot/bananapi/bpi-r2/linux/uEnv.txt
			mount -o remount,ro /boot
			if cat /boot/bananapi/bpi-r2/linux/uEnv.txt | grep "init=/sbin/overlayRoot.sh noOverlayRoot" >& /dev/null; then
				echo "ERROR: Unable to disable Overlay Root script!"
				exit 1
			else
				echo "Overlay Root script disabled for next reboot!"
			fi
		fi
	else
		echo "SYNTAX: $(basename $0) overlay [enable|disable]"
	fi
}

###########################################################################
# Function dealing with setting reformat persistant storage flag:
###########################################################################
function setReformat()
{
	if [[ ! -z "$2" && ! "$2" =~ -(y|-yes) ]]; then
		echo "SYNTAX: $(basename $0) reformat [-y|--yes]"
		exit 1
	fi
	if [[ ! "$2" =~ -(y|-yes) ]]; then
		echo "WARNING: The router will reboot and persistent storage will be formatted.  This action cannot be undone!\n\n"
		askYesNo "Are you SURE you want to do this?" || exit 0
	fi
	remount_rw
	sed -i "s|^SECONDARY_REFORMAT=.*|SECONDARY_REFORMAT=yes|g" /ro/etc/overlayRoot.conf
	remount_ro
	reboot now
}

###########################################################################
# Function that sets the IP address for DNS server we're using:
###########################################################################
function setDNS()
{
	IP=(${@/\#/ })
	if ! valid_ip ${IP[0]}; then
		echo "ERROR: Invalid IP address specified in 2nd parameter!"
		echo "SYNTAX: $(basename $0) dns [ip address(#optional port)]"
		exit 1
	fi
	if [[ ! -z "${IP[1]}" ]]; then
		if [[ ! "${IP[1]}" =~ ^[0-9]+$ || ${IP[1]} -gt 65535 ]]; then
			echo "ERROR: Invalid port number specified in 2nd parameter!"
			echo "SYNTAX: $(basename $0) dns [ip address(#optional port)]"
			exit 1
		fi
	fi
	sed -i "/PIHOLE_DNS_/d" /etc/pihole/setupVars.conf
	echo "PIHOLE_DNS_1=${1}" >> /etc/pihole/setupVars.conf
	pihole restartdns
}

###########################################################################
# Function dealing with login credentials and username:
###########################################################################
function setLogin()
{
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
}

###########################################################################
# Main code
###########################################################################
RO_DEV=$(test -f /ro/etc/fstab && cat /ro/etc/fstab | grep " / " | sed "s|^#||g" | cut -d" " -f 1)
CMD=$1
shift
case $CMD in
	chroot)
		check_ro
		remount_rw
		echo "CHROOT" > /tmp/debian_chroot
		chattr +i /tmp/debian_chroot
		mount --bind /tmp/debian_chroot /ro/etc/debian_chroot
		shift
		chroot /ro $@
		remount_ro
		;;

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

	reformat)
		check_ro
		setReformat $@
		;;

	overlay)
		setOverlay $@
		;;

	dns)
		setDNS $@
		;;

	pihole)
		/usr/local/bin/pihole $@
		;;

	apt)
		/usr/bin/apt $@
		;;

	login)
		setLogin $@
		;;

	dhcp-info)
		bound=($(grep dhclient /var/log/syslog* | grep bound | sort | tail -1 | cut -d":" -f 2-))
		from=($(grep dhclient /var/log/syslog* | grep from | sort | tail -1 | cut -d":" -f 2-))
		[[ -z "${from[-1]}" ]] && exit
		[[ -z "${bound[-2]}" ]] && exit
		echo ${from[-1]} $(echo $(php -r "echo strtotime('${bound[0]} ${bound[1]} ${bound[2]}');")) ${bound[-2]}
		;;

	reboot)
		/sbin/reboot now
		;;

	webui)
		cd /opt/bpi-r2-router-builder
		if [[ "$1" == "current" ]]; then
			echo $(git log -1 --format="%at")
		elif [[ "$1" == "remote" ]]; then
			git remote update >& /dev/null
			echo $(git log -1 --format="%at" origin/master)
		elif [[ "$1" == "update" ]]; then
			./upgrade.sh
		fi
		;;

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
			if ! md5sum -c md5sum 2> /dev/null | grep FAILED >& /dev/null; then echo "ERROR: Checksum Failure"; exit; fi
		elif [[ "$1" == "restore" ]]; then
			if ! test -d /tmp/bpiwrt; then; echo "ERROR: Backup has not been unpacked!"; exit; fi
			cd /tmp/bpiwrt
			if ! md5sum -c md5sum 2> /dev/null | grep FAILED >& /dev/null; then echo "ERROR: Checksum Failure"; exit; fi
			while IFS= read -r line; do rm $line; done < etc/default/backup_file.list
		fi
		;;
		
	*)
		echo "Syntax: $(basename $0) [command] [options]"
		;;
esac