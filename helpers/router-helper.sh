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
TABLE=$(grep -m 1 "^table inet " /etc/nftables.conf | awk '{print $3}')

###########################################################################
# Supporting functions
###########################################################################
# Check to make sure that a read-only filesystem exists, and diagnose issue if it doesn't:
function check_ro()
{
	if test -d /ro; then
		mount | grep " /ro " | grep -q "ext4" || echo "ERROR: Read-only partition is a not a ext4 filesystem!  Aborting!" || exit 1
	else
		if [[ "$(cat /etc/debian_chroot)" == "CHROOT" ]]; then
			echo "ERROR: Already in chroot environment!"
			exit 1
		elif ! test -e /boot/bananapi/bpi-r2/linux/uEnv.txt; then
			echo "ERROR: uEnv.txt file is missing."
			echo "Copy '/opt/bpi-r2-router-builder/uEnv.txt' to \"/boot/bananapi/bpi-r2/linux/uEnv.txt\" to enable."
			exit 1
		fi
		if [[ "$(cat /boot/bananapi/bpi-r2/linux/uEnv.txt | grep bootmenu_default | cut -d= -f 2)" != "2" ]]; then
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
	RO=${1:-"/ro"}
	if [[ "${RO}" == "notrap" ]]; then
		RO=/ro
	elif [[ "${RO}" == "/ro" ]]; then
		if ! mount -o remount,rw $RO_DEV /ro; then
			echo "ERROR: Unable to remount root filesystem!"
			exit 1
		fi
		trap 'remount_ro' SIGINT
		trap 'remount_ro' EXIT
	fi
	mount --bind /dev ${RO}/dev
	mount --bind /run ${RO}/run
	mount --bind /proc ${RO}/proc
	mount --bind /sys ${RO}/sys
	mount --bind /tmp ${RO}/tmp
	mount --bind /var/lib/apt/lists ${RO}/var/lib/apt/lists
	[[ "${RO}" == "/ro" ]] && mount -t tmpfs tmpfs ${RO}/var/cache/apt
	return 0
}

# Remount writable lower filesystem as readonly:
function remount_ro()
{
	RO=${1:-"/ro"}
	umount -q ${RO}/tmp
	umount -q ${RO}/sys
	umount -q ${RO}/proc
	umount -q ${RO}/run
	umount -q ${RO}/dev
	umount -q ${RO}/var/lib/apt/lists
	umount -q ${RO}/var/cache/apt
	[[ "$RO" == "/ro" ]] && mount -o remount,ro $RO_DEV /ro
}

# Function to ask whether to do a particular action:
# Src: https://stackoverflow.com/a/31939275
function askYesNo {
	local QUESTION=$1
	local DEFAULT=$2
	if [ "$DEFAULT" = true ]; then
		OPTIONS="[Y/n]"
		DEFAULT="y"
	else
		OPTIONS="[y/N]"
		DEFAULT="n"
	fi
	read -p "$QUESTION $OPTIONS " -n 1 -s -r INPUT
	local INPUT=${INPUT:-${DEFAULT}}
	echo ${INPUT}
	ANSWER=false
	[[ "$INPUT" =~ ^[yY]$ ]] && ANSWER=true
}

function valid_ip()
{
	local ip=(${1/:/ })
	[[ "${2:-"N"}" == "N" && ! -z "${ip[1]}" ]] && return 1
	if [[ "${2:-"N"}" == "Y" ]]; then [[ ${ip[1]} -le 65535 ]] 2> /dev/null || return 1; fi
	ip=(${ip//./ })
	[[ ! -z "${ip[3]}" && ${ip[0]} -le 255 && ${ip[1]} -le 255 && ${ip[2]} -le 255 && ${ip[3]} -le 255 ]] 2> /dev/null && return 0
	return 1
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
		echo "RO" > /tmp/debian_chroot
		mount --bind /tmp/debian_chroot /ro/etc/debian_chroot
		chroot /ro $@
		umount -q /ro/etc/debian_chroot
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
			echo "Usage: $(basename $0) remount [ro|rw]"
			echo "Where:"
			echo "     ro  -  Readonly Access"
			echo "     rw  -  Read/Wrie Access"
		fi
		;;

	###########################################################################
	reformat)
		check_ro
		if [[ ! "$1" =~ -(h|y|-yes) ]]; then
			echo "Usage: $(basename $0) reformat [-y]"
			echo "Where:"
			echo "    -y   Do not prompt to confirm and reboot system."
			exit 0
		fi
		if [[ ! "$1" =~ -(y|-yes) ]]; then
			echo "WARNING: The router will reboot and persistent storage will be formatted.  This action cannot be undone!"
			askYesNo "Are you SURE you want to do this?" || exit 0
		fi
		remount_rw
		echo "SECONDARY_REFORMAT=yes" >> /ro/etc/overlayRoot.conf
		reboot now
		;;

	###########################################################################
	overlay)
		test -f /etc/default/router-settings && source /etc/default/router-settings
		DIR=${OVERLAY_ROOT:-"/var/lib/docker/persistent"}
		#####################################################################
		# ENABLE/DISABLE => Set overlay status to either ennabled or disabled:
		if [[ "$1" == "enable" || "$1" == "disable" ]]; then
			FILE=/boot/bananapi/bpi-r2/linux/uEnv.txt
			OLD=$(cat ${FILE} | grep bootmenu_default | cut -d= -f 2)
			DIFF=$(( $(cat /boot/bananapi/bpi-r2/linux/uEnv.txt | grep bootmenu_default | cut -d= -f 2) / 2 * 2 ))
			NEW=$([[ "$1" == "enable" ]] && echo "$(( 0 + $DIFF ))" || echo "$(( 1 + $DIFF ))")
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
			STAT=$(cat /boot/bananapi/bpi-r2/linux/uEnv.txt | grep -q "^bootmenu_default=[2|4]" && echo "enabled" || echo "disabled")
			IN_USE=$(mount | grep -q " /ro " || echo " not")
			echo "Overlay Root script is ${STAT} for next boot, currently${IN_USE} active."
		#####################################################################
		# Everything else:
		else
			[[ "$1" != "-h" ]] && echo "ERROR: Invalid option passed!"
			echo "Usage: $(basename $0) overlay [enable|disable|status]"
			echo "Where:"
			echo "    enable  - Enables overlay script upon next boot"
			echo "    disable - Disables overlay script upon next boot"
			echo "    status  - Displays current status and next boot status of overlay script"
		fi
		;;

	###########################################################################
	compile)
		test -f /etc/default/router-settings && source /etc/default/router-settings
		DIR=${OVERLAY_ROOT:-"/var/lib/docker/persistent"}
		if ! mount | grep " /ro " >& /dev/null; then
			echo "ERROR: Read-only root partition not available for use!"
			exit 1
		fi
		#####################################################################
		# MOUNT => Creates overlayfs for chroot environment (aka for compiling stuff)
		if [[ "$1" == "mount" ]]; then
			mkdir -p ${DIR}
			cd ${DIR}
			mkdir -p {upper,work,merged}
			test -d /ro && LOW=/ro || LOW=/
			for DIR in lower*; do LOW=${LOW}:./${DIR}; done
			mount -t overlay chroot_env -o lowerdir=${LOW},upperdir=./upper,workdir=./work ./merged
			echo "CE" > merged/etc/debian_chroot
		#####################################################################
		# ENTER/CHROOT => Enters the created chroot for compilation environment
		elif [[ "$1" == "enter" || "$1" == "chroot" ]]; then
			DIR=${DIR}/${2:-"merged"}
			mount | grep -q "${DIR}/merged" && $0 compile umount
			$0 compile mount || exit 1
			remount_rw ${DIR}
			shift
			chroot ${DIR} $@
			remount_ro ${DIR}
			$0 compile umount
		#####################################################################
		# UMOUNT => Creates overlayfs for compilation environment
		elif [[ "$1" == "umount" ]]; then
			mount | grep "${DIR}/merged" | awk '{print $3}' | tac | while read mount; do umount $mount; done
			umount -q ${DIR}/lower 2> /dev/null
		#####################################################################
		# ADD_LAYER => Moves upper directory to a new lower layer directory for overlay:
		elif [[ "$1" == "add_layer" ]]; then
			if ! cd ${DIR}; then echo "ERROR: No persistent folder created yet!  Aborting!"; exit 1; fi
			$0 compile umount 
			LAYER=$(find . -maxdepth 1 -type d -name lower* | sort | tail -1 | sed "s|\.\/||")
			LAYER=lower$(( ${LAYER/lower/} + 1 ))
			mv upper ${LAYER}
			echo "[DONE] New lower overlay directory is ready for use."
		#####################################################################
		# DEL_LAYER => Moves upper directory to a new lower layer directory for overlay:
		elif [[ "$1" == "del_layer" ]]; then
			if ! cd ${DIR}; then echo "ERROR: No persistent folder created yet!  Aborting!"; exit 1; fi
			if [[ ! "$2" =~ -(y|-yes) ]]; then
				echo "WARNING: The router will remove the last lower directory created.  In addition, the"
				echo "script will remove changes made to the overlay environment.  This cannot be undone!"
				askYesNo "Are you SURE you want to do this?" || exit 0
			fi
			$0 compile umount 
			LAYER=$(find . -maxdepth 1 -type d -name lower* | sort | tail -1 | sed "s|\.\/||")
			echo "[INFO] Destroying ${LAYER}, upper and work directories..."
			rm -rf ${DIR}/{upper,work,${LAYER}}
			echo "[DONE] Completed!"
		#####################################################################
		# RESET => Remove changes made to the overlayfs environment
		elif [[ "$1" == "reset" ]]; then
			if ! cd ${DIR}; then echo "ERROR: No persistent folder created yet!  Aborting!"; exit 1; fi
			if [[ ! "$2" =~ -(y|-yes) ]]; then
				echo "WARNING: The router will remove changes made to overlay environment and cannot be undone!"
				askYesNo "Are you SURE you want to do this?" || exit 0
			fi
			$0 compile umount
			echo "[INFO] Destroying upper and work directories..."
			rm -rf ${DIR}/{upper,work}
			echo "[DONE] Completed!"
		#####################################################################
		# DESTROY => Destroys entire overlayfs for compilation environment
		elif [[ "$1" == "destroy" ]]; then
			if ! cd ${DIR}; then echo "ERROR: No persistent folder created yet!  Aborting!"; exit 1; fi
			if [[ ! "$2" =~ -(y|-yes) ]]; then
				echo "WARNING: The router will delete the ENTIRE overlay environment built and cannot be undone!"
				askYesNo "Are you SURE you want to do this?" || exit 0
			fi
			echo "[INFO] Destroying entire overlay filesystem..."
			$0 compile umount
			rm -rf ${DIR}/{lower*,upper,work,merged}
			echo "[DONE] Completed!"
		#####################################################################
		# Everything else:
		else
			[[ "$1" != "-h" ]] && echo "ERROR: Invalid option passed!"
			echo "Usage: $(basename $0) compile [mount|chroot|enter|umount|merge|clear|destroy]"
			echo "Where:"
			echo "    mount     - Create separate overlayfs environment"
			echo "    enter     - Enter created separate overlayfs environment"
			echo "    chroot    - Enter created separate overlayfs environment"
			echo "    umount    - Remove created separate overlayfs environment"
			echo "    add_layer - Moves upper directory to a lower layer directory"
			echo "    del_layer - Deletes most current layer"
			echo "    reset     - Remove all changes made to the overlayfs environment"
			echo "    destroy   - Remove ENTIRE overlayfs environment"
		fi
		;;

	###########################################################################
	apt)
		export DEBIAN_FRONTEND=noninteractive
		if [[ "$1" == "hold" || "$1" == "unhold" ]]; then
			apt-mark $@
		elif [[ "$1" == "upgrade" || "$1" == "dist-upgrade" || "$1" == "full-upgrade" || "$1" == "install" ]]; then
			apt -o Dpkg::Options::='--force-confdef' --assume-yes -fuy $@ | stdbuf -oL tr '\r' '\n'
		else
			apt $@ | stdbuf -oL tr '\r' '\n'
		fi
		;;

	###########################################################################
	login)
		USER=$(cat /etc/passwd | grep ":1000:" | cut -d: -f1)
		#####################################################################
		# CHECK => Checks to make sure supplied username/password combo is valid:
		if [[ "$1" == "check" ]]; then
			[[ -z "${2}" ]] && echo "No match" && exit 1
			pwd=$(getent shadow ${2} | cut -d: -f2)
			[[ -z "${pwd}" ]] && echo "No match" && exit 1
			[[ "$(mkpasswd ${3} ${pwd})" == "${pwd}" ]] && echo "Match" || echo "No match"
		#####################################################################
		# PASSWD => Changes the password for user 1000:
		elif [[ "$1" == "passwd" ]]; then
			OK=$($0 login check ${USER} $2)
			[[ "${OK}" == "No match" ]] && echo "ERROR: Incorrect Password" && exit 1
			[[ -z "${3}" ]] && echo "ERROR: Password not specified" && exit 1
			(echo $3; echo $3) | passwd ${3:-"${USER}"} >& /dev/null && echo "OK" || echo "Password change failed"
		#####################################################################
		# USERNAME => Returns the username for user 1000:
		elif [[ "$1" == "username" ]]; then
			[[ -z "${2}" ]] && echo "Username not specified" && exit 1
			usermod -l $2 ${3:-"${USER}"} && echo "Success"
		#####################################################################
		# Everything else:
		else
			[[ "$1" != "-h" ]] && echo "ERROR: Invalid option passed!"
			echo "Usage: $(basename $0) login [check|passwd|username]"
			echo "Where:"
			echo "    check [username] [password]   - Verifies that supplied credentials are correct for user 1000"
			echo "    passwd [oldPass] [newPass]    - Changes password of user 1000 from [oldPass] to [newPass]"
			echo "    username [username]           - Changes username of user 1000 to [username]"
		fi
		;;

	###########################################################################
	status)
		#####################################################################
		# REBOOT/POWEROFF: Reboot or poweroff the machine
		if [[ "$1" == "reboot" || "$1" == "poweroff" ]]; then
			/sbin/$1 now
		#####################################################################
		# MACHINE: Return machine name from system log (inaccessable otherwise...)
		elif [[ "$1" == "machine" ]]; then
			grep -m 1 'Machine model' /var/log/syslog* | awk -F ":" '{print $NF}'
		#####################################################################
		# Everything else:
		else
			[[ "$1" != "-h" ]] && echo "ERROR: Invalid option passed!"
			echo "Usage: $(basename $0) status [reboot|poweroff|machine]"
			echo "Where:"
			echo "    reboot   - Reboots the machine"
			echo "    poweroff - Powers off the machine"
			echo "    machine  - Returns machine name found in system log"
		fi
		;;

	###########################################################################
	device)
		if [[ -z "$1" ]]; then echo "ERROR: No hostname specified!"; exit 1; fi
		if [[ -z "$2" ]]; then echo "ERROR: No timezone specified!"; exit 1; fi
		if [[ ! -f /usr/share/zoneinfo/$2 ]]; then echo "ERROR: Invalid timezone specified!"; exit 1; fi
		if [[ -z "$3" ]]; then echo "ERROR: No locale specified!"; exit 1; fi
		LOC=${3/utf8/UTF-8}
		if [[ -z "$(cat /etc/locale.gen | grep -i "^$LOC ")" ]]; then echo "ERROR: Invalid locale specified!"; exit 1; fi

		# Set the new hostname:
		OLD_HOST=$(hostname)
		ORIG="$(grep " ${OLD_HOST}$" /etc/hosts)"
		REPL="${ORIG//${OLD_HOST}/${1}}"
		sed -i "s|^${ORIG}$|${REPL}|g" /etc/hosts
		sed -i "s| ${OLD_HOST}$| ${1}|g" /etc/pihole/custom.list
		sed -i "s| ${OLD_HOST}.local$| ${1}|g" /etc/pihole/custom.list
		sed -i "s| ${OLD_HOST}$| ${1}|g" /etc/squid/squid.conf
		echo "$1" > /etc/hostname
		/bin/hostname $1

		# Set the new timezone:
		echo "$2" > /etc/timezone
		timedatectl set-timezone $2

		# Set the new OS locale:
		echo "LANG=${LOC}" > /etc/default/locale
		localectl set-locale LANG=${LOC}
		echo "OK"
		;;

	###########################################################################
	git)
		if ! cd /opt/${2:-"bpi-r2-router-builder"} >& /dev/null; then echo "ERROR: Invalid repository specified!"; exit; fi
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
			if [[ "$2" == "bpi-r2-router-builder" ]]; then
				$PWD/upgrade.sh
			else
				git reset --hard
				git pull
				test /ro && $0 chroot /usr/local/bin/router-helper git update $2
			fi
		#####################################################################
		# Everything else:
		else
			[[ "$1" != "-h" ]] && echo "ERROR: Invalid option passed!"
			echo "Usage: $(basename $0) git [current|remove|update]"
			echo "Where:"
			echo "    current [subdir]  - Returns current version number of [subdir] repository under /opt"
			echo "    remote [subdir]   - Returns remote version number of [subdir] repository under /opt"
			echo "    update [subdir]   - Pulls the current version of the [subdir] repository under /opt"
			echo ""
			echo "NOTE: Version numbers are expressed as \"vYYYY.MMDD.HHMM\"."
		fi
		;;

	###########################################################################
	backup)
		if ! mount | grep -q "^overlayfs-root"; then echo "ERROR: Overlay is disabled."; exit; fi
		#####################################################################
		# SQUASH => Create settings backup in the /tmp folder:
		if [[ "$1" == "squash" ]]; then
			$0 backup unlink

			# Create backup directory:
			BACKUP=/tmp/bpiwrt
			test -d ${BACKUP} && rm -rf ${BACKUP}
			mkdir -p ${BACKUP}/{boot,etc}
			cp /boot/persistent.conf ${BACKUP}/boot/ 2> /dev/null

			# Sort "/etc/sysupgrade.conf" and include in backup if different:
			cat /etc/sysupgrade.conf | sort > ${BACKUP}/etc/sysupgrade.conf
			cat /ro/etc/sysupgrade.conf | sort > /tmp/sysupgrade.conf
			cmp -s ${BACKUP}/etc/sysupgrade.conf /tmp/sysupgrade.conf >& /dev/null && rm ${BACKUP}/etc/sysupgrade.conf
			rm /tmp/sysupgrade.conf

			# Copy any modified files from overlay upper layer to backup directory:
			cat /etc/sysupgrade.conf | while read LINE; do
				[[ "$LINE" =~ ^/ ]] && ROOT=/ || ROOT=/rw/upper/
				if [[ ! -z "${LINE}" ]]; then
					for FILE in $(ls ${ROOT}/${LINE}); do
						DIR=${BACKUP}/$(dirname ${FILE} | sed "s|${ROOT}/||")
						mkdir -p ${DIR}
						cp -a ${FILE} ${DIR}/
					done 2> /dev/null
				fi
			done

			# Remove any Adblock privoxy rules that we added automagically, then remove the privoxy
			# configuration file if it is the same as the unmodified one:
			rm ${BACKUP}/etc/privoxy/*.adblock.{action,filter} >& /dev/null
			if [[ -f ${BACKUP}/etc/privoxy/config ]]; then
				sed -i "/^actionsfile .*\.adblock\./d" ${BACKUP}/etc/privoxy/config
				cmp -s ${BACKUP}/etc/privoxy/config /ro/etc/privoxy/config >& /dev/null && rm ${BACKUP}/etc/privoxy/config
			fi

			# Remove any empty directories from the newly copied directory tree:
			find ${BACKUP} -type d | sort -r | while read dir; do rmdir ${dir} 2> /dev/null; done

			# Create a list of services to start, stop, or reload:
			cd /rw/upper/etc
			DIR=${BACKUP}/var/opt
			FILE=${DIR}/init_services.sh
			mkdir -p ${DIR}
			echo '#!/bin/bash' > ${FILE}
			find systemd/system/ -type c -exec basename {} \; 2> /dev/null | sort | uniq | while read LINE; do echo "systemctl disable --now ${LINE}"; done >> ${FILE}
			find systemd/system/ -type l -exec basename {} \; 2> /dev/null | sort | uniq | while read LINE; do echo "systemctl enable --now ${LINE}"; done >> ${FILE}

			# Load any firewall settings that need to be updated:
			if [[ -f default/router-settings ]]; then
				echo "systemctl reload nftables" >> ${FILE}
			elif [[ -f persistent-nftables.conf ]]; then
				echo "nft -f etc/persistent-nftables.conf" >> ${FILE}
			fi

			# Save any differences in "/etc/passwd", "/etc/group" and "/etc/shadow":
			for CHK in shadow passwd group; do
				grep -Fxvf /ro/etc/${CHK} ${CHK} 2> /dev/null | while read LINE; do
					USER=$(echo $LINE | cut -d: -f 1)
					echo "sed -i \"s|^${USER}:.*|${LINE//$/\\\$}|\" /etc/${CHK}" >> ${FILE}
				done
			done

			# If any CA certificates exist, add them to the system so it's trusted by default:
			[[ -f ${BACKUP}/usr/local/share/ca-certificates/* ]] && echo "update-ca-certificates" >> ${FILE}

			# Make a squashfs archive of all files copied/created:
			cd ${BACKUP}
			mksquashfs ./ /tmp/bpiwrt.cfg -quiet -no-progress -b 1048576 -comp xz -Xdict-size 100%
			rm -rf ${BACKUP}
		#####################################################################
		# REMOVE => Remove uploaded configuration backup:
		elif [[ "$1" == "unlink" ]]; then
			umount -q /tmp/bpiwrt 2> /dev/null
			test -f /tmp/bpiwrt.cfg && rm /tmp/bpiwrt.cfg
		#####################################################################
		# PREP => Prep the uploaded configuration backup to be restored:
		elif [[ "$1" == "prep" ]]; then
			umount -q /tmp/bpiwrt 2> /dev/null
			rm -rf /tmp/bpiwrt
			mkdir -p /tmp/bpiwrt
			if ! test -f /tmp/bpiwrt.cfg; then echo "ERROR: Missing /tmp/bpiwrt.cfg file!"; exit 1; fi
			if ! mount -o loop -t squashfs /tmp/bpiwrt.cfg /tmp/bpiwrt >& /dev/null; then echo "ERROR: Corrupted or invalid settings backup!"; exit 1; fi
		#####################################################################
		# RESTORE => Copy the files from the uploaded configuration backup into place:
		elif [[ "$1" == "copy" ]]; then
			if ! mount | grep -q ' /tmp/bpiwrt '; then $0 backup prep || exit 1; fi
			cp -aR /tmp/bpiwrt/* /
			if [[ -f /var/opt/init_services.sh ]]; then
				source /var/opt/init_services.sh
				rm /var/opt/init_services.sh
			fi
		#####################################################################
		# Everything else:
		else
			[[ "$1" != "-h" ]] && echo "ERROR: Invalid option passed!"
			echo "Usage: $(basename $0) backup [squash|unlink|prep|copy]"
			echo "Where:"
			echo "    squash  - Creates a backup of critical settings in /tmp/bpiwrt.cfg "
			echo "    unlink  - Unmount and remove /tmp/bpiwrt.cfg"
			echo "    prep    - Prepares the uploaded backup found in /tmp/bpiwrt.cfg"
			echo "    copy    - Restores the files from the uploaded configuration backup"
		fi
		;;

	###########################################################################
	defaults)
		# CREATE => Create "persistent" default settings in the /boot partition:
		if [[ "$1" == "create" ]]; then
			$0 backup squash
			mount -o remount,rw /boot
			mv /tmp/bpiwrt.cfg /boot/bpiwrt.cfg || rm /boot/bpiwrt.cfg
			mount -o remount,ro /boot
		#####################################################################
		# UNPACK => Clear reformat flag and unpack custom default settings:
		elif [[ "$1" == "unpack" ]]; then
			test -d /ro && DIR=/ro
			source ${DIR}/etc/overlayRoot.conf
			if [[ "$SECONDARY_REFORMAT" == "yes" ]]; then
				# Remove the reformatting flag from "/etc/overlayRoot.conf":
				test -d /ro && mount -o remount,rw /ro
				sed -i "/SECONDARY_REFORMAT=/d" ${DIR}/etc/overlayRoot.conf
				if [[ -d /ro ]]; then
					# If user-defined defaults exist, copy them to file system:
					if [[ -f /boot/bpiwrt.cfg ]]; then
						mount /boot/bpiwrt.cfg /mnt
						cp -aRv /mnt/* /
						umount /mnt
					fi
					mount -o remount,ro /ro
				fi
			fi
			exit 0
		#####################################################################
		# REMOVE => Create "persistent" settings backup from the /boot partition:
		elif [[ "$1" == "remove" ]]; then
			if test -f /boot/bpiwrt.cfg; then
				mount -o remount,rw /boot
				rm /boot/bpiwrt.cfg
				mount -o remount,ro /boot
			else
				echo "ERROR: No override defaults have been stored in \"/boot\"!"
			fi
		#####################################################################
		# Everything else:
		else
			[[ "$1" != "-h" ]] && echo "ERROR: Invalid option passed!"
			echo "Usage: $(basename $0) defaults [create|unpack|remove]"
			echo "Where:"
			echo "    create  - Creates default override settings config in /boot/bpiwrt.cfg"
			echo "    unpack  - Unpack default override settings config from /boot/bpiwrt.cfg"
			echo "    remove  - Unmount and remove /boot/bpiwrt.cfg"
		fi
		;;


	###########################################################################
	iface)
		# MOVE => Move specified configuration file from "/tmp" to "/etc/network/interfaces.d/":
		if [[ "$1" == "move" ]]; then
			if ! test -f /tmp/${2}; then echo "ERROR: Missing Configuration File"; exit; fi
			mv /tmp/${2} /etc/network/interfaces.d/${2}
			chown root:root /etc/network/interfaces.d/${2}
		#####################################################################
		# DELETE => Delete specified configuration file from "/etc/network/interfaces.d/":
		elif [[ "$1" == "delete" ]]; then
			rm /etc/network/interfaces.d/${2} 2> /dev/null
		#####################################################################
		# IFUP => Restart the specified interface:
		elif [[ "$1" == "ifup" ]]; then
			ifup ${2}
		#####################################################################
		# IFDOWN => Restart the specified interface:
		elif [[ "$1" == "ifdown" ]]; then
			ifdown ${2}
		#####################################################################
		# SCAN => Restart the specified interface:
		elif [[ "$1" == "scan" ]]; then
			iw ${2} scan
		#####################################################################
		# SCAN-TEST => Restart the specified interface:
		elif [[ "$1" == "scan-test" ]]; then
			cat /opt/bpi-r2-router-builder/misc/old/wifi-test.txt
		#####################################################################
		# AP_MOVE => Move specified configuration file from "/tmp" to "/etc/network/interfaces.d/":
		elif [[ "$1" == "ap_move" ]]; then
			if ! test -f /tmp/${2}; then echo "ERROR: Missing Configuration File"; exit; fi
			mv /tmp/${2} /etc/hostapd/${2}.conf
			chown root:root /etc/hostapd/${2}.conf
		#####################################################################
		# AP_DEL => Move specified configuration file from "/tmp" to "/etc/network/interfaces.d/":
		elif [[ "$1" == "ap_del" ]]; then
			if ! test -f /tmp/${2}; then echo "ERROR: No Configuration File specified"; exit; fi
			rm /etc/hostapd/${2}.conf
		#####################################################################
		# Everything else:
		else
			[[ "$1" != "-h" ]] && echo "ERROR: Invalid option passed!"
			echo "Usage: $(basename $0) iface [move|delete|ifup|ifdown|scan|scan-test|hostapd]"
			echo "Where:"
			echo "    move [file]       - Moves specified file from /tmp/ to /etc/network/interfaces.d/"
			echo "    delete [iface]    - Deletes specified interface configuration file"
			echo "    ifup [iface]      - Brings specified interface up"
			echo "    ifdown [iface]    - Brings specified interface down"
			echo "    scan [iface]      - Perform a wifi scan on the specified interface"
			echo "    scan-test [iface] - Returns test scan data instead of performing a wifi scan."
			echo "    ap_move [iface]   - Moves specified file from /tmp/ to /etc/hostapd/"
			echo "    ap_del [iface]    - Deletes specified hostapd configuration file"
		fi
		;;

	###########################################################################
	dhcp)
		#####################################################################
		# INFO => Get DHCP information from the system logs:
		if [[ "$1" == "info" ]]; then
			IFACE=${2:-"wan"}
			request=($(journalctl --unit=networking | grep dhclient | grep DHCPREQUEST | grep ${IFACE} | tail -1))
			bound=($(journalctl --unit=networking | grep dhclient | grep bound | grep ${request[8]} | tail -1))
			from=$(route -n | grep -e '^0.0.0.0' | grep ${IFACE} | head -1 | awk '{print $2}')
			[[ -z "${bound[-2]}" ]] && exit
			echo ${from} ${bound[0]} ${bound[1]} ${bound[2]} ${bound[-2]}
		#####################################################################
		# SET => Create or modify the DHCP for a specific interface:
		elif [[ "$1" == "set" ]]; then
			FILE=/etc/dnsmasq.d/${2}.conf
			if ! valid_ip $3; then echo "ERROR: Invalid IP Address specified as 3rd param!"; exit; fi
			if ! valid_ip $4; then echo "ERROR: Invalid IP Address specified as 4th param!"; exit; fi
			if ! valid_ip $5; then echo "ERROR: Invalid IP Address specified as 5th param!"; exit; fi
			if [[ ! "$6" =~ [0-9]+[m|h|d|w|] && "$6" != "infinite" ]]; then echo "ERROR: Invalid time period specified as 6th param!"; exit; fi
			if ! test -f ${FILE}; then
				echo "interface=$2" > ${FILE}
				echo "dhcp-range=${2},${4},${5},${6}" >> ${FILE}
				echo "dhcp-option=${2},3,${3}" >> ${FILE}
			else
				# Fix any IP addresses on any dhcp host lines in the configuration file:
				OLD_IP=$(cat ${FILE} | grep dhcp-option | cut -d"," -f 3)
				OLD_SUB=$(echo ${OLD_IP} | cut -d"." -f 1-3).
				NEW_SUB=$(echo $3 | cut -d"." -f 1-3).
				sed -i "s|${OLD_SUB}|${NEW_SUB}|g" ${FILE}

				# Replace the DHCP range and option lines with our new configuration:
				sed -i "s|^dhcp-range=.*|dhcp-range=${2},${4},${5},${6}|g" ${FILE}
				sed -i "s|^dhcp-option=.*|dhcp-option=${2},3,${3}|g" ${FILE}
			fi
		#####################################################################
		# RM => Remove the specified host from the DHCP configuration file:
		elif [[ "$1" == "remove" ]]; then
			FILE=/etc/dnsmasq.d/${2}.conf
			if ! test -f ${FILE}; then echo "ERROR: No DNSMASQ configuration found for adapter!"; exit; fi
			if [[ ! "$3" =~ ^([a-fA-F0-9]{2}:){5}[a-fA-F0-9]{2}$ ]]; then echo "ERROR: Invalid MAC address specified as 3rd param!"; exit 1; fi
			if ! valid_ip $4; then echo "ERROR: Invalid IP address specified as 4th param!"; exit 1; fi
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
			test -f /etc/dnsmasq.d/${2}.conf && rm /etc/dnsmasq.d/${2}.conf
		#####################################################################
		# IFACES => List the interfaces we can  the DHCP configuration file:
		elif [[ "$1" == "ifaces" ]]; then
			for iface in /sys/class/net/*; do
				name=$(basename $iface)
				if test -f /etc/dnsmasq.d/${name}.conf; then
					cat /etc/dnsmasq.d/${name}.conf | grep dhcp-range | cut -d= -f 2 | cut -d, -f 1
				fi
			done
		#####################################################################
		# Everything else:
		else
			[[ "$1" != "-h" ]] && echo "ERROR: Invalid option passed!"
			echo "Usage: $(basename $0) dhcp [del|add|remove|set|info]"
			echo "Where:"
			echo "    info                                 - Returns DHCP information"
			echo "    set [iface] [ip] [ip] [ip] [expires] - Sets DHCP configuration for interface"
			echo "    remove [iface] [mac] [ip]            - Remove the specified host from interface\'s DHCP configuration"
			echo "    add [iface] [mac] [ip] [?]           - Adds specified host to interface\'s DHCP configuration"
			echo "    del [iface]                          - Deletes DHCP configuration for interface"
			echo "    ifaces                               - Lists interfaces that we can set DHCP reservations on"
		fi
		;;

	###########################################################################
	systemctl)
		systemctl $@
		;;

	###########################################################################
	mac)
		MAC=$1
		[[ "$MAC" == "saved" && -f /boot/persistent.conf ]] && source /boot/persistent.conf
		if [[ -z "$MAC" || "$MAC" == "saved" || "$MAC" == "current" ]]; then
			MAC=$(ifconfig wan | grep ether | awk '{print $2}')
			echo "INFO: Using MAC Address: $MAC"
		elif [[ "$MAC" == "random" ]]; then
			MAC=$(printf '%01X2:%02X:%02X:%02X:%02X:%02X\n' $[RANDOM%16] $[RANDOM%256] $[RANDOM%256] $[RANDOM%256] $[RANDOM%256] $[RANDOM%256])
			echo "INFO: Using MAC Address: $MAC"
		elif [[ ! "$MAC" =~ ^([a-fA-F0-9]{2}:){5}[a-fA-F0-9]{2}$ ]]; then
			[[ "$1" != "-h" ]] && echo "ERROR: Invalid option passed!"
			echo "Usage: $(basename $0) mac [option]"
			echo "Where \"option\" is:"
			echo "    saved         - Use the saved MAC address from /boot/persistent.conf"
			echo "    current       - Use the current MAC address"
			echo "    random        - Generate a random MAC address"
			echo "    [mac address] - Use specified MAC address"
			exit 0
		fi

		# Decompile DTB, then add new MAC address (if not already there), then recompile:
		FILE=/boot/bananapi/bpi-r2/linux/dtb/bpi-r2.dtb
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
		[[ ! -f ${FILE}.old ]] && cp ${FILE} ${FILE}.old
		[[ -f /boot/persistent.conf ]] && sed -i "/^MAC=/d" /boot/persistent.conf
		echo "MAC=${MAC}" >> /boot/persistent.conf
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
	pihole)
		if [[ "$1" == "restartdns" && ! -z "$2" ]]; then
			echo "  [✓] Waiting for interface $2 to become available...."
			while ! ifconfig $2 >& /dev/null; do sleep 1; done
		fi
		/usr/local/sbin/pihole $@
		;;

	###########################################################################
	dns)
		# Are we getting the contents of the "/etc/ddclient.conf" file?  If so, output it and exit.
		[[ "$1" == "ddclient" ]] && cat /etc/ddclient.conf && exit 0

		# Validate first IP address passed as parameter:
		unset DNS1 DNS2
		IP=(${1/"#"/" "})
		if ! valid_ip ${IP[0]}; then echo "ERROR: Invalid IP Address specified as 1st param!"; exit; fi
		if [[ ! -z "${IP[1]}" ]]; then if [[ "${IP[1]}" -lt 0 || "${IP[1]}" -gt 65535 ]]; then echo "ERROR: Invalid port number for 1st param!"; exit; fi; fi
		DNS1=${IP[0]}$([[ ! -z "${IP[1]}" ]] && echo "#${IP[1]}")

		IP=(${2/"#"/" "})
		if [[ ! -z "${IP[@]}" ]]; then
			if ! valid_ip ${IP[0]}; then echo "ERROR: Invalid IP Address specified as 2nd param!"; exit; fi
			if [[ ! -z "${IP[1]}" ]]; then if [[ "${IP[1]}" -lt 0 || "${IP[1]}" -gt 65535 ]]; then echo "ERROR: Invalid port number for 2nd param!"; exit; fi; fi
			DNS2=${IP[0]}$([[ ! -z "${IP[1]}" ]] && echo "#${IP[1]}")
		fi

		# Remove existing IP addresses and add the included ones:
		if [[ ! -z "${DNS1}" ]]; then
			sed -i "/^PIHOLE_DNS_/d" /etc/pihole/setupVars.conf
			echo "PIHOLE_DNS_1=${DNS1}" >> /etc/pihole/setupVars.conf
			[[ ! -z "${DNS2}" ]] && echo "PIHOLE_DNS_2=${DNS2}" >> /etc/pihole/setupVars.conf
			sed -i "/^server=/d" /etc/dnsmasq.d/01-pihole.conf
			echo "server=${DNS1}" >> /etc/dnsmasq.d/01-pihole.conf
			[[ ! -z "${DNS2}" ]] && echo "server=${DNS2}" >> /etc/dnsmasq.d/01-pihole.conf

			# Restart the PiHole FTL service if running:
			if [[ "$3" != "norestart" ]]; then if systemctl -q is-active pihole-FTL; then pihole restartdns; else true; fi; fi
			[[ $? -eq 0 ]] && echo "OK"
		else
			[[ "$1" != "-h" ]] && echo "ERROR: Invalid option passed!"
			echo "Syntax: $(basename $0) dns [DNS #1] [DNS #2]"
			echo ""
			echo "NOTE: Using a port with the IP Address is expressed as \"127.0.0.1#5335\"!"
		fi
		;;

	###########################################################################
	route)
		#####################################################################
		# MOVE => Move the specified routing file to "/etc/network/if-up.d/":
		if [[ "$1" == "move" ]]; then
			if ! test -f /tmp/$2; then echo "ERROR: Specified file does not exist!"; exit; fi
			chown root:root /tmp/$2
			chmod 755 /tmp/$2
			mv /tmp/$2 /etc/network/if-up.d/$2
			echo 'OK'
		#####################################################################
		# ADD/DEL => Call "ip route" with the specified parameters:
		elif [[ "$1" == "add" || "$1" == "del" ]]; then
			ip route $@
			echo 'OK'
		#####################################################################
		# Everything else:
		else
			[[ "$1" != "-h" ]] && echo "ERROR: Invalid option passed!"
			echo "SYNTAX: $(basename $0) route [move|add|del]"
			echo "Where:"
			echo "    move [filename]     - Moves file from temporary folder to /etc/network/if-up.d"
			echo "    add [parameters]    - Adds routing with specified parameters to the network routing table"
			echo "    del [parameters]    - Removes routing with specified parameters from network routing table"
		fi
		;;

	###########################################################################
	upgrade)
		/opt/bpi-r2-router-builder/upgrade.sh $@
		;;

	###########################################################################
	cleanup)
		CMD=/opt/bpi-r2-router-builder/misc/remove_files
		$CMD $@
		[[ -d /ro ]] && $0 chroot $CMD $@
		;;

	###########################################################################
	webui)
		for action in $@; do
			#####################################################################
			# ACTION: http-on => Turn on Router WebUI locally via HTTP:
			#####################################################################
			if [[ "${action}" == "http-on" ]]; then
				! test -f /etc/nginx/sites-enabled/default && ln -sf /etc/nginx/sites-available/router /etc/nginx/sites-enabled/default
			#####################################################################
			# ACTION: https-on => Turn on Router WebUI locally via HTTP and HTTPS:
			#####################################################################
			elif [[ "${action}" == "https-on" ]]; then
				! test -f /etc/nginx/sites-enabled/default && ln -sf /etc/nginx/sites-available/router /etc/nginx/sites-enabled/default
				! test -f /etc/nginx/sites-enabled/default-https && ln -sf /etc/nginx/sites-available/router-https /etc/nginx/sites-enabled/default-https
			#####################################################################
			# ACTION: http-off => Turn off Router WebUI locally via HTTP and HTTPS:
			#####################################################################
			elif [[ "${action}" == "http-off" ]]; then
				test -f /etc/nginx/sites-enabled/default && rm /etc/nginx/sites-enabled/default
				test -f /etc/nginx/sites-enabled/default-https && rm /etc/nginx/sites-enabled/default-https
			#####################################################################
			# ACTION: https-off => Turn off Router WebUI locally viaHTTPS:
			#####################################################################
			elif [[ "${action}" == "https-off" ]]; then
				test -f /etc/nginx/sites-enabled/default-https && rm /etc/nginx/sites-enabled/default-https
			#####################################################################
			# ACTION: pihole-http-on => Turn on Pi-Hole WebUI locally via HTTP:
			#####################################################################
			elif [[ "${action}" == "pihole-http-on" ]]; then
				! test -f /etc/nginx/sites-enabled/pihole && ln -sf /etc/nginx/sites-available/pihole /etc/nginx/sites-enabled/pihole
			#####################################################################
			# ACTION: pihole-http-off => Turn on Pi-Hole WebUI locally via HTTP:
			#####################################################################
			elif [[ "${action}" == "pihole-https-on" ]]; then
				! test -f /etc/nginx/sites-enabled/pihole && ln -sf /etc/nginx/sites-available/pihole /etc/nginx/sites-enabled/pihole
				! test -f /etc/nginx/sites-enabled/pihole-https && ln -sf /etc/nginx/sites-available/pihole-https /etc/nginx/sites-enabled/pihole-https
			#####################################################################
			# ACTION: pihole-http-off => Turn off PiHole WebUI locally via HTTP and HTTPS:
			#####################################################################
			elif [[ "${action}" == "pihole-http-off" ]]; then
				test -f /etc/nginx/sites-enabled/pihole && rm /etc/nginx/sites-enabled/pihole
				test -f /etc/nginx/sites-enabled/pihole-https && rm /etc/nginx/sites-enabled/pihole-https
			#####################################################################
			# ACTION: https-off => Turn off PiHole WebUI locally viaHTTPS:
			#####################################################################
			elif [[ "${action}" == "pihole-https-off" ]]; then
				test -f /etc/nginx/sites-enabled/pihole-https && rm /etc/nginx/sites-enabled/pihole-https
			#####################################################################
			# ACTION: restart => Restart Nginx daemon:
			#####################################################################
			elif [[ "${action}" == "restart" ]]; then
				systemctl restart nginx
			#####################################################################
			# Otherwise, display error:
			#####################################################################
			else
				[[ "$1" != "-h" ]] && echo "ERROR: Invalid option passed!"
				echo "SYNTAX: $(basename $0) webui [(options)]"
				echo "Where:"
				echo "    http-on          = Turn on Router WebUI locally via HTTP"
				echo "    https-on         = Turn on Router WebUI locally via HTTPS"
				echo "    http-off         = Turn off Router WebUI locally via HTTP"
				echo "    https-off        = Turn on Router WebUI locally via HTTPS"
				echo "    pihole-http-on   = Turn on PiHole Admin WebUI locally via HTTP"
				echo "    pihole-https-on  = Turn on PiHole Admin WebUI locally via HTTPS"
				echo "    pihole-http-off  = Turn off PiHole Admin WebUI locally via HTTP"
				echo "    pihole-https-off = Turn off PiHole Admin WebUI locally via HTTPS"
				echo "    restart          = Restart nginx service (web server)"
			fi
		done
		;;

	###########################################################################
	samba)
		#####################################################################
		# WEBUI-ON/WEBUI-OFF - Control state of WebUI router samba share:
		#####################################################################
		if [[ "${1}" =~ webui-(on|off) ]]; then
			/opt/bpi-r2-router-builder/helpers/usbmount-helper.sh ${1}
		#####################################################################
		# Otherwise, display error:
		#####################################################################
		else
			[[ "$1" != "-h" ]] && echo "ERROR: Invalid option passed!"
			echo "SYNTAX: $(basename $0) samba [webui-on|webui-off]"
			echo "Where:"
			echo "    webui-on  = Turn on samba share to WebUI directory"
			echo "    webui-off = Turn off samba share to WebUI directory"
		fi
		;;	  

	###########################################################################
	forward_port|forward_range|trigger_port)
		#####################################################################
		# ADD/DELETE - Add/delete the port from the list in question:
		#####################################################################
		if [[ "${1,,}" =~ (add|delete) ]]; then
			# Validate the incoming information:
			[[ "${1^^}" == "Y" ]] && ENABLED= || ENABLED='# '
			NFT="${2,,} element inet ${TABLE} ${CMD^^}"
			PROTO=${3^^}
			if [[ ! "${PROTO}" =~ (TCP|UDP|BOTH) ]]; then echo "ERROR: 2nd param must be \"tcp\", \"udp\" or \"both\"."; exit 1; fi
			EXT_PORT=${4}
			if [[ "${EXT_PORT}" -lt 65535 ]] 2> /dev/null; then echo "ERROR: 3rd param must be an integer!"; exit 1; fi
			if [[ "${CMD}" == "trigger_port" ]]; then
				INT_ADDR=
				shift 4
			else
				[[ "${CMD}" == "forward_port" ]] && INT_PORT=Y || INT_PORT=N
				valid_ip $5 ${INT_PORT} || echo "ERROR: 4th param must have a valid IP address and port$([[ "$INT_PORT" == "N" ]] && echo " range")!"; exit 1
				INT_ADDR=": ${5/:/ . }"
				shift 5
			fi
			[[ -z "$@" ]] && COMMENT= || COMMENT=" \# ${@}"

			# Remove the port mapping to the persistent rules file:
			FILE=/etc/persistent-nftables.conf
			if [[ "${PROTO}" =~ BOTH ]]; then
				sed -i "/ ${NFT/delete/add}_(TCP|UDP) { ${EXT_PORT} /d" ${FILE}
			else
				sed -i "/ ${NFT/delete/add}_${PROTO} { ${EXT_PORT} /d" ${FILE}
			fi

			# Add the port mapping to the persistent rules file:
			if [[ "${1}" == "add" ]]; then
				if [[ "${PROTO}" =~ BOTH ]]; then
					echo "${ENABLED}${NFT}_TCP { ${EXT_PORT} ${DATA} } ${COMMENT}" >> ${FILE}
					echo "${ENABLED}${NFT}_UDP { ${EXT_PORT} ${DATA} } ${COMMENT}" >> ${FILE}
				else
					echo "${ENABLED}${CMD}_${PROTO} { ${EXT_PORT} ${DATA} } ${COMMENT}" >> ${FILE}
				fi
			fi
			nft -f ${FILE} || exit $?
			echo "OK"

		#####################################################################
		# Otherwise, display help:
		#####################################################################
		else
			echo "SYNTAX: $(basename $0) ${CMD} [add|delete] [...]"
			[[ "$CMD" == "forward_port" ]] && MSG=" [ip]"
			[[ "$CMD" == "forward_range" ]] && MSG=" [ip:port]"
			[[ "$CMD" == "trigger_port" ]] && MSG=""
			echo "Add ports to the list:"
			echo "    ${CMD} add [enable] [proto] [ports] ${MSG}[comment]"
			echo "Delete ports from the list:"
			echo "    ${CMD} delete [proto] [ports]"
			exit 1
		fi
		;;

	###########################################################################
	move)
		# Determine what filename to move the temporary file to:
		FILE=
		[[ "$1" == "miniupnpd" ]] && FILE=/etc/miniupnpd/miniupnpd.conf
		[[ "$1" == "multicast-relay" ]] && FILE=/etc/default/multicast-relay
		[[ "$1" == "docker-compose" ]] && FILE=/etc/docker-compose.yaml
		[[ "$1" == "ddclient" ]] && FILE=/etc/ddclient.conf && chmod 600 ${FILE}
		[[ "$1" == "privoxy-blocklist" ]] && FILE=/etc/privoxy/blocklist.conf

		# Was a filename determined?  If not, abort with error:
		[[ -z "${FILE}" ]] && echo "ERROR: Invalid option passed!" && exit 1

		# Abort if temporary file not found.  Otherwise, copy to destination, delete original, then change owner to root:
		if ! test -f /tmp/router-settings; then echo "ERROR: File does not exist!"; exit; fi
		mv /tmp/router-settings ${FILE}
		chown root:root ${FILE}

		# Restart service if requested and already running:
		if [[ "$2" == "restart" ]]; then
			SERVICE=${1}
			if [[ "${SERVICE}" == "privoxy-blocklist" ]]; then
				echo "y" | /usr/local/bin/privoxy-blocklist.sh -r
				/usr/local/bin/privoxy-blocklist.sh
			elif [[ "$(systemctl is-active $1)" == "active" ]]; then
				systemctl restart $1
			fi
		fi
		echo "OK"
		;;

	###########################################################################
	# Code adapted from https://andrewwippler.com/2016/03/11/wifi-captive-portal/
	portal)
		#####################################################################
		# CHECK => Check to see if captive portal is required for IP address:
		#####################################################################
		if [[ "$1" == "check" ]]; then
			# Return error if IP address passed is invalid:
			IP=${2}
			if ! valid_ip ${IP}; then echo "ERROR: Invalid IP Address specified as 2rd param!"; exit 1; fi
			# Return error if IP address isn't in the ARP table:
			ARP=($(arp | grep ${IP}))
			if [[ -z "${ARP[@]}" ]]; then echo "ERROR: No MAC address found for specified IP address!"; exit 1; fi
			# Not required if the interface isn't part of the captive portal configuration:
			if ! nft get element inet ${TABLE} DEV_PORTAL { ${ARP[6]} } >& /dev/null; then echo "Y"; exit; fi
			# Not required if MAC address has already been approved:
			nft get element inet ${TABLE} PORTAL_PASS { ${ARP[3]} } >& /dev/null && echo "Y" || echo "N"
		#####################################################################
		# ACCEPT/REJECT => Accept or reject MAC address for IP address specified:
		#####################################################################
		elif [[ "$1" == "accept" || "$1" == "reject" ]]; then
			# Return error if IP address passed is invalid:
			IP=${2}
			if ! valid_ip ${IP}; then echo "ERROR: Invalid IP Address specified as 2rd param!"; exit 1; fi
			# Return error if IP address isn't in the ARP table:
			ADDR=($(arp -a ${IP} | grep -o '..:..:..:..:..:..'))
			if [[ -z "${ADDR[@]}" ]]; then echo "ERROR: No MAC address found for specified IP address!"; exit 1; fi
			# Remove any conntrack information about this IP address:
			conntrack -L | grep ${IP} | grep ESTAB | grep 'dport=80' | awk "{ system(\"conntrack -D --orig-src $1 --orig-dst \" substr(\$6,5) \" -p tcp --orig-port-src \" substr(\$7,7) \" --orig-port-dst 80\"); }"

			# If persistence is NOT required, set the timeout if required:
			TIMEOUT=
			if [[ "${captive_portal_persistent:-"Y"}" != "Y" ]]; then
				[[ ! -z "${captive_portal_${1}_timeout}" ]] && TIMEOUT=" timeout ${captive_portal_${1}_timeout}"
			fi

			# Add the MAC address(es) here:
			for MAC in ${ADDR[@]}; do
				# Add the MAC address(es) to either "PORTAL_ACCEPT" or "PORTAL_REJECT" set, along with timeout (if any):
				nft add element inet ${TABLE} PORTAL_${1^^} { ${MAC} ${TIMEOUT} }

				# If Captive Portal persistence is required, add it to "/etc/persistent-nftables.conf":
				if [[ -z "${TIMEOUT}" ]]; then
					FILE=/etc/persistent-nftables.conf
					sed -i "/PORTAL_${1^^} { ${MAC} }/d" ${FILE}
					echo "nft add element inet ${TABLE} PORTAL_${1^^} { ${MAC} }" >> ${FILE}
				fi
			done
			echo "OK"
		#####################################################################
		# Otherwise, display help:
		#####################################################################
		else
			[[ "$1" != "-h" ]] && echo "ERROR: Invalid option passed!"
			echo "SYNTAX: $(basename $0) portal [check|allow|reject] [ip]"
			echo "Where:"
			echo "    check [ip]   - Shows if captive portal is required for specified IP address"
			echo "    accept [ip]  - Accepts MAC address associated with specified IP address for access to network"
			echo "    reject [ip]  - Rejects MAC address associated with specified IP address for access to network"
		fi
		;;

	###########################################################################
	*)
		[[ "$1" != "-h" ]] && echo "ERROR: Invalid command passed!"
		echo "Syntax: $(basename $0) [command] [options]"
		echo "Where:"
		(echo "    chroot        - Enters chroot environment in system partition"
		 echo "    remount       - Remounts system partition as read-only or writable"
		 echo "    reformat      - Reformats persistent storage"
		 echo "    overlay       - Enables or Disables overlay script"
		 echo "    compile       - Compilation chroot environment actions"
		 echo "    apt           - Debian package installer"
		 echo "    login         - Login actions"
		 echo "    device        - Device Setings actions"
		 echo "    backup        - Settings Backup and Restore actions"
		 echo "    git           - Repository actions"
		 echo "    iface         - Network Interface Setup actions"
		 echo "    dhcp          - DHCP actions"
		 echo "    systemctl     - System Services Control actions"
		 echo "    mac           - Onboard Ethernet MAC address actions"
		 echo "    firewall      - Firewall actions"
		 echo "    dns           - Domain Name Server actions"
		 echo "    route         - Network Routing actions"
		 echo "    upgrade       - Pulls the lastest version of WebUI from GitHub"
		 echo "    cleanup       - Removes unnecessary files from system partition"
		 echo "    webui         - WebUI actions"
		 echo "    forward_port  - Single Port forwarding actions"
		 echo "    forward_range - Port Range forwarding actions"
		 echo "    trigger_port  - Port triggering actions"
		 echo "    move          - Move configuration files into position"
		 echo "    portal        - Captive Portal actions"
		 echo "    defaults      - User-Defined Persistent Settings Backup and Restore actions"
		 echo "    samba         - Samba actions"
		) | sort
		echo ""
		echo "NOTE: Use \"-h\" after the command to see what options are available for that command."
		;;
esac
