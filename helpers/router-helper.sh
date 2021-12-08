#!/bin/bash
#############################################################################
# This helper script runs any tasks that the web server user "www-data"
# needs to perform as "root" user.  Adding "www-data" user to /etc/sudoers.d/
# presents opportunities for malicious scripts to run, so this script is the
# "official" way to run any tasks requiring "root" access.
#############################################################################
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
		if [[ ! -z "$1" && ! "$1" =~ -(y|-yes) ]]; then
			echo "SYNTAX: $(basename $0) reformat [-y|--yes]"
			exit 1
		fi
		if [[ ! "$1" =~ -(y|-yes) ]]; then
			echo "WARNING: The router will reboot and persistent storage will be formatted.  This action cannot be undone!"
			askYesNo "Are you SURE you want to do this?" || exit 0
		fi
		remount_rw
		sed -i "s|^SECONDARY_REFORMAT=.*|SECONDARY_REFORMAT=yes|g" /ro/etc/overlayRoot.conf
		reboot now
		;;

	###########################################################################
	overlay)
		#####################################################################
		# ENABLE/DISABLE => Set overlay status to either ennabled or disabled:
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
		#####################################################################
		# STATUS => Returns status of overlay setting:
		elif [[ "$1" == "status" ]]; then
			STAT=$(cat /boot/bananapi/bpi-r2/linux/uEnv.txt | grep "^bootmenu_default=2" >& /dev/null && echo "enabled" || echo "disabled")
			IN_USE=$(mount | grep " /ro " >& /dev/null || echo " not")
			echo "Overlay Root script is ${STAT} for next boot, currently${IN_USE} active."
		fi
		;;

	###########################################################################
	apt)
		export DEBIAN_FRONTEND=noninteractive
		if [[ "$1" == "hold" || "$1" == "unhold" ]]; then
			apt-mark $@
		elif [[ "$1" == "upgrade" || "$1" == "dist-upgrade" || "$1" == "full-upgrade" || "$1" == "install" ]]; then
			apt -o Dpkg::Options::='--force-confdef' --assume-yes -fuy $@
		else
			apt $@
		fi
		;;

	###########################################################################
	login)
		#####################################################################
		# CHECK => Checks to make sure supplied username/password combo is valid:
		if [[ "$1" == "check" ]]; then
			[[ "${2}" != "$($0 login webui)" ]] && echo "No match" && exit 1
			[[ -z "${3}" ]] && echo "No match" && exit 1
			pwd=$(getent shadow ${2} | cut -d: -f2)
			salt=\$$(echo $pwd | cut -d$ -f2)\$$(echo $pwd | cut -d$ -f3)
			[ "$(python -c 'import crypt; print crypt.crypt("'"${3}"'", "'${salt}'")')" == "${pwd}" ] && echo "Match" || echo "No match"
		#####################################################################
		# WEBUI => Returns the username for user 1000:
		elif [[ "$1" == "webui" ]]; then
			echo $(cat /etc/passwd | grep ":1000:" | cut -d: -f1)
		#####################################################################
		# PASSWD => Changes the password for user 1000:
		elif [[ "$1" == "passwd" ]]; then
			[[ -z "${2}" ]] && echo "Password not specified" && exit 1
			(echo $2; echo $2) | passwd $(cat /etc/passwd | grep ":1000:" | cut -d: -f1)
		#####################################################################
		# USERNAME => Returns the username for user 1000:
		elif [[ "$1" == "username" ]]; then
			[[ -z "${2}" ]] && echo "Username not specified" && exit 1
			usermod -l $2 $(cat /etc/passwd | grep ":1000:" | cut -d: -f1) && echo "Success"
		#####################################################################
		# SAFETY-CHECK => Returns information about possible security concerns:
		elif [[ "$1" == "safety-check" ]]; then
			[[ "$($0 login check $($0 login webui) bananapi)" == "Match" ]] && echo "Default"
			[[ "$($0 login check root bananapi)" == "Match" ]] && echo "Root"
			mount | grep -e "[emergency|tmp]-root-rw on /rw " >& /dev/null && echo "Temp"
		fi
		;;

	###########################################################################
	reboot)
		/sbin/reboot now
		;;

	###########################################################################
	device)
		if [[ -z "$1" ]]; then echo "ERROR: No hostname specified!"; exit 1; fi
		if [[ -z "$2" ]]; then echo "ERROR: No timezone specified!"; exit 1; fi
		if [[ ! -f /usr/share/zoneinfo/$2 ]]; then echo "ERROR: Invalid timezone specified!"; exit 1; fi
		if [[ -z "$3" ]]; then echo "ERROR: No locale specified!"; exit 1; fi
		if [[ -z "$(cat /etc/locale.gen | grep "^$3 ")" ]]; then echo "ERROR: Invalid locale specified!"; exit 1; fi

		# Set the new hostname:
		OLD_HOST=$(hostname)
		ORIG="$(grep ${OLD_HOST} /etc/hosts)"
		REPL="${ORIG//${OLD_HOST}/${1}}"
		sed -i "s|^${ORIG}\$|${REPL}|g" /etc/hosts
		echo "$1" > /etc/hostname
		/bin/hostname $1

		# Set the new timezone:
		echo "$2" > /etc/timezone
		timedatectl set-timezone $2

		# Set the new OS locale:
		echo "LANG=$3" > /etc/default/locale
		localectl set-locale LANG=$3
		echo "OK"
		;;

	###########################################################################
	git)
		cd /opt/${2:-"bpi-r2-router-builder"}
		#####################################################################
		# CURRENT => Return current repo time as version number (vYYYY.MMDD.HHMM)
		if [[ "$1" == "current" ]]; then
			git log -1 --format="%at"
		#####################################################################
		# REMOTE => Return remote repo time as version number (vYYYY.MMDD.HHMM)
		elif [[ "$1" == "remote" ]]; then
			git remote update >& /dev/null
			git log -1 --format="%at" origin/master
		#####################################################################
		# UPDATE => Return current repo time as version number (vYYYY.MMDD.HHMM)
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
		if ! cd /rw/upper; then echo "ERROR: Overlay is disabled."; exit; fi
		#####################################################################
		# CREATE => Create settings backup:
		if [[ "$1" == "create" ]]; then
			find . | grep -v -E "/var|/opt|/home|/run|/tmp|/root|/rw|/ro" | grep -E ".conf|.json" > /tmp/backup_file.list
			ftb=($(cat /tmp/backup_file.list))
			cd /tmp
			md5sum ${ftb[@]} |sed "s|  /|  |g" > md5sum
			test -f /tmp/bpiwrt.cfg && rm /tmp/bpiwrt.cfg
			tar -cJf /tmp/bpiwrt.cfg md5sum ${ftb[@]} >& /dev/null
		#####################################################################
		# REMOVE => Remove uploaded configuration backup:
		elif [[ "$1" == "remove" ]]; then
			rm /tmp/bpiwrt.cfg
		#####################################################################
		# UNPACK => Unpack the uploaded configuration backup:
		elif [[ "$1" == "unpack" ]]; then
			rm -rf /tmp/bpiwrt
			mkdir -p /tmp/bpiwrt
			cd /tmp/bpiwrt
			if ! tar -xJf /tmp/bpiwrt.cfg; then echo "ERROR: Invalid settings file!"; exit; fi
			if md5sum -c md5sum 2> /dev/null | grep FAILED >& /dev/null; then echo "ERROR: Checksum Failure"; exit; fi
		#####################################################################
		# RESTORE => Actually move the files from the uploaded configuration backup into place:
		elif [[ "$1" == "restore" ]]; then
			if ! test -d /tmp/bpiwrt; then echo "ERROR: Backup has not been unpacked!"; exit; fi
			cd /tmp/bpiwrt
			if md5sum -c md5sum 2> /dev/null | grep FAILED >& /dev/null; then echo "ERROR: Checksum Failure"; exit; fi
			while IFS= read -r line; do mv ${line:1} $(dirname $line)/; done < etc/default/backup_file.list
		fi
		;;

	###########################################################################
	iface)
		# MOVE => Move specified configuration file from "/tmp" to "/etc/network/interfaces.d/":
		if [[ "$1" == "move" ]]; then
			if ! test -f /tmp/${2}; then echo "ERROR: Missing Configuration File"; exit; fi
			mv /tmp/${2} /etc/network/interfaces.d/${2}
			chroot root:root /etc/network/interfaces.d/${2}
		#####################################################################
		# DELETE => Delete specified configuration file from "/etc/network/interfaces.d/":
		elif [[ "$1" == "delete" ]]; then
			rm /etc/network/interfaces.d/${1}.conf 2> /dev/null
		fi
		;;

	###########################################################################
	dhcp)
		#####################################################################
		# INFO => Get DHCP information from the system logs:
		if [[ "$1" == "info" ]]; then
			bound=($(cat /var/log/syslog* | grep dhclient | grep bound | sort | tail -1))
			from=($(cat /var/log/syslog* | grep dhclient | grep from | sort | tail -1))
			[[ -z "${from[-1]}" ]] && exit
			[[ -z "${bound[-2]}" ]] && exit
			echo ${from[-1]} ${bound[0]} ${bound[1]} ${bound[2]} ${bound[-2]}
		#####################################################################
		# SET => Create or modify the DHCP for a specific interface:
		elif [[ "$1" == "set" ]]; then
			FILE=/etc/dnsmasq.d/${2}.conf
			if [[ ! "$3" =~ ^([a-fA-F0-9]{2}:){5}[a-fA-F0-9]{2}$ ]]; then echo "ERROR: Invalid MAC address specified as 2nd param!"; exit 1; fi
			if ! valid_ip $4; then echo "ERROR: Invalid IP Address specified as 3rd param!"; exit; fi
			if ! valid_ip $5; then echo "ERROR: Invalid IP Address specified as 4th param!"; exit; fi
			if [[ ! "$5" =~ [0-9]+[m|h|d|w|] ]]; then echo "ERROR: Invalid time period specified as 5th param!"; exit; fi
			if ! test -f ${FILE}; then
				echo "interface=$2" > ${FILE}
				echo "dhcp-range=${2},${4},${5},${6}" >> ${FILE}
				echo "dhcp-option=${2},3,${3}" >> ${FILE}
			else
				# Replace any hostnames with the old IP address with the correct IP address:
				OLD_IP=$(cat ${FILE} | grep dhcp-option | cut -d"," -f 3)
				sed -i "s|${OLD_SUB}|${NEW_SUB}|g" /etc/hosts

				# Fix any IP addresses on any dhcp host lines in the configuration file:
				OLD_SUB=$(echo ${OLD_IP} | cut -d"." -f 1-3).
				NEW_SUB=$(echo $3 | cut -d"." -f 1-3).
				sed -i "s|${OLD_IP}|${3}|g" ${FILE}

				# Replace the DHCP range and option lines with our new configuration:
				sed -i "s|^dhcp-range=*.|dhcp-range=${2},${4},${5},${6}|g" ${FILE}
				sed -i "s|^dhcp-option=*.|dhcp-option=${2},3,${3}|g" >> ${FILE}
			fi
		#####################################################################
		# RM => Remove the specified host from the DHCP configuration file:
		elif [[ "$1" == "remove" ]]; then
			FILE=/etc/dnsmasq.d/${2}.conf
			if ! test -f ${FILE}; then echo "ERROR: No DNSMASQ configuration found for adapter!"; exit; fi
			if [[ ! "$3" =~ ^([a-fA-F0-9]{2}:){5}[a-fA-F0-9]{2}$ ]]; then echo "ERROR: Invalid MAC address specified as 2nd param!"; exit 1; fi
			if ! valid_ip $4; then echo "ERROR: Invalid IP address specified as 3rd param!"; exit 1; fi
			sed -i "/^dhcp-host=.*,${3},/d" ${FILE}
			sed -i "/^dhcp-host=.*,${4},/d" ${FILE}
			echo "OK"
		#####################################################################
		# ADD => Add the specified host to the DHCP configuration file:
		elif [[ "$1" == "add" ]]; then
			$0 dhcp remove $2 $3 $4 || exit
			echo "dhcp-host=$2,$3,$4,$5" >> /etc/dnsmasq.d/${2}.conf
		#####################################################################
		# DEL => Delete the DHCP configuration file:
		elif [[ "$1" == "del" ]]; then
			rm /etc/dnsmasq.d/${2}.conf
		fi
		;;

	###########################################################################
	systemctl)
		systemctl $@
		;;

	###########################################################################
	mac)
		MAC=$1
		[[ "$MAC" == "saved" && -f /boot/eth0.conf ]] && source /boot/eth0.conf
		if [[ -z "$MAC" || "$MAC" == "saved" || "$MAC" == "current" ]]; then
			MAC=$(ifconfig wan | grep ether | awk '{print $2}')
			echo "INFO: Using MAC Address: $MAC"
		elif [[ "$MAC" == "random" ]]; then
			MAC=$(printf '%01X2:%02X:%02X:%02X:%02X:%02X\n' $[RANDOM%16] $[RANDOM%256] $[RANDOM%256] $[RANDOM%256] $[RANDOM%256] $[RANDOM%256])
			echo "INFO: Using MAC Address: $MAC"
		elif [[ ! "$MAC" =~ ^([a-fA-F0-9]{2}:){5}[a-fA-F0-9]{2}$ ]]; then
			echo "ERROR: Invalid MAC address specified!"
			exit 1
		fi

		# Decompile DTB, then add new MAC address (if not already there), then recompile:
		FILE=/boot/bananapi/bpi-r2/linux/dtb/bpi-r2.dtb
		[[ ! -f ${FILE}.old ]] && cp ${FILE} ${FILE}.old
		trap "rm /tmp/dts" EXIT
		dtc -q -O dts ${FILE} > /tmp/dts
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
		echo "MAC=${MAC}" > /boot/eth0.conf
		dtc -q -O dtb /tmp/dts > ${FILE}
		[[ ! -z "$RO" ]] && mount -o remount,ro /boot

		# We need to change the MAC address on our ethernet interfaces:
		[[ "$1" == "current" ]] && echo "OK" && exit
		WIFI=($(iw dev | grep Interface | awk '{print $NF}'))
		for IFACE in $(netstat -i | awk '{print $1}'); do
			if [[ ! "${WIFI[@]}" =~ "${IFACE}" && -f /etc/network/interfaces.d/${IFACE} ]]; then
				ifconfig ${IFACE} down
				ifconfig ${IFACE} hw ether $MAC
				ifconfig ${IFACE} up
			fi
		done
		echo "OK"
		;;

	###########################################################################
	firewall)
		/opt/bpi-r2-router-builder/helpers/firewall.sh $@
		echo "OK"
		;;

	###########################################################################
	dns)
		# If we are being requested to set the DNS servers from the ISP, do so then exit
		unset DNS1 DNS2
		if [[ "$1" == "config" ]]; then
			test -f /etc/default/firewall && source /etc/default/firewall
			if [[ "${use_unbound:-"N"}" == "Y" ]]; then
				DNS1=127.0.0.1#5335
			elif [[ "${use_cloudflared:="N"}" == "Y" ]]; then
				DNS1=127.0.0.1#5051
			elif [[ "${use_isp:="N"}" == "Y" ]]; then 
				IP=($(cat /etc/resolv.conf | grep "nameserver" | head -2 | awk '{print $2}'))
				DNS1=${IP[0]}
				DNS2=${IP[1]}
			else
				echo "ERROR: Invalid action specified!" && exit
			fi
		else
			# Validate first IP address passed as parameter:
			IP=(${1/"#"/" "})
			if ! valid_ip ${IP[0]}; then echo "ERROR: Invalid IP Address specified as 1st param!"; exit; fi
			if [[ ! -z "${IP[1]}" ]]; then if [[ "${IP[1]}" -lt 0 || "${IP[1]}" -gt 65535 ]]; then echo "ERROR: Invalid port number for 1st param!"; exit; fi; fi
			DNS1=${DNS1[0]}$([[ "${DNS1[1]}" != "" ]] && echo "#${DNS1[1]}")

			IP=(${2/"#"/" "})
			if [[ ! -z "${IP[@]}" ]]; then
				if ! valid_ip ${IP[0]}; then echo "ERROR: Invalid IP Address specified as 2nd param!"; exit; fi
				if [[ "${IP[1]}" -lt 0 || "${IP[1]}" -gt 65535 ]]; then echo "ERROR: Invalid port number for 2nd param!"; exit; fi; 
				DNS2=${DNS2[0]}$([[ "${DNS2[1]}" != "" ]] && echo "#${DNS2[1]}")
			fi
		fi

		# Remove existing IP addresses and add the included ones:
		sed -i "/^PIHOLE_DNS_/d" /etc/pihole/setupVars.conf
		echo "PIHOLE_DNS_1=${DNS1}" >> /etc/pihole/setupVars.conf
		[[ ! -z "${DNS2}" ]] && echo "PIHOLE_DNS_2=${DNS2}" >> /etc/pihole/setupVars.conf
		sed -i "/^server=/d" /etc/dnsmasq.d/01-pihole.conf
		echo "server=${DNS1}" >> /etc/dnsmasq.d/01-pihole.conf
		[[ ! -z "${DNS2}" ]] && echo "server=${DNS2}" >> /etc/dnsmasq.d/01-pihole.conf

		# Restart the PiHole FTL service if running:
		if [[ "$3" != "norestart" ]]; then if systemctl is-active pihole-FTL >& /dev/null; then pihole restartdns; else true; fi; fi
		[[ $? -eq 0 ]] && echo "OK"
		;;

	###########################################################################
	route)
		#####################################################################
		# MOVE => Move the specified routing file to "/etc/network/if-up.d/":
		if [[ "$1" == "move" ]]; then
			if ! test -f /tmp/$2; then echo "ERROR: Specified file does not exist!"; exit; fi
			chown root:root /tmp/$2
			chmod 755 /tmp/$2
			mv /tmp/$1 /etc/network/if-up.d/$2
			echo 'OK'
		#####################################################################
		# ADD/DEL => Call "ip route" with the specified parameters:
		elif [[ "$1" == "add" || "$1" == "del" ]]; then
			ip route $@
			echo 'OK'
		fi
		;;

	###########################################################################
	upgrade)
		/opt/bpi-r2-router-builder/upgrade.sh $@
		;;

	###########################################################################
	remove_files)
		CMD=/opt/bpi-r2-router-builder/misc/remove_files
		$CMD $@
		[[ -d /ro ]] && $0 chroot $CMD $@
		;;

	###########################################################################
	webui)
		for action in $@; do
			if [[ "${action}" == "http-on" ]]; then
				! test -f /etc/nginx/sites-enabled/default && ln -sf /etc/nginx/sites-available/router /etc/nginx/sites-enabled/default
			elif [[ "${action}" == "http-off" ]]; then
				test -f /etc/nginx/sites-enabled/default && rm /etc/nginx/sites-enabled/default
			elif [[ "${action}" == "https-on" ]]; then
				if ! test -f /etc/ssl/certs/localhost.crt; then
					(echo; echo; echo; echo; echo; echo; echo) | sudo openssl req -x509 -nodes -days 3650 -newkey rsa:2048 -keyout /etc/ssl/private/localhost.key -out /etc/ssl/certs/localhost.crt
				fi
				! test -f /etc/nginx/sites-enabled/default-https && ln -sf /etc/nginx/sites-available/router-https /etc/nginx/sites-enabled/default-https
			elif [[ "${action}" == "https-off" ]]; then
				test -f /etc/nginx/sites-enabled/default-https && rm /etc/nginx/sites-enabled/default-https
			fi
		done
		systemctl restart nginx
		;;

	###########################################################################
	*)
		echo "Syntax: $(basename $0) [command] [options]"
		;;
esac
