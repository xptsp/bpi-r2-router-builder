alias dpkg-deb='dpkg-deb -Zxz $@'
alias losl='losetup -l | sort -V'
function los
{
	if [[ -z "$1" || ! -f "$1" ]]; then
		echo "Syntax: los [filename]"
	else
		local dev=$(sudo losetup --show -f -P $1)
		local dest=${dev/dev/mnt}
		echo $dest
		if [[ ! "$(basename $1)" =~ ^(bpiwrt|bpi-r2) ]]; then
			for part in ${dev}p*; do
				sudo mkdir -p ${dest}
				sudo mount ${part} ${dest}
			done
		else
			sudo mkdir -p ${dest}
			sudo mount ${dev}p2 ${dest}
			[[ -d ${dest}/@/boot ]] && DIR=${dest}/@ || DIR=${dest}
			sudo mkdir -p ${DIR}/boot
			sudo mount ${dev}p1 ${DIR}/boot
			sudo mount --bind /proc ${DIR}/proc
			sudo mount --bind /sys ${DIR}/sys
			sudo mount --bind /tmp ${DIR}/tmp
			sudo mount --bind /dev ${DIR}/dev
			sudo mount -t tmpfs tmpfs ${DIR}/var/lib/apt/lists
			sudo mount -t tmpfs tmpfs ${DIR}/var/cache/apt
		fi
	fi
}
function losd
{
	local dev="${1/^mnt/dev}"
	local mnt=$(basename ${dev})
	if [[ -z "${dev}" ]]; then
		echo "Syntax: losd [loop device path]"
		return 0
	fi
	dev=$(losetup -l | grep "${mnt} " | cut -d" " -f 1)
	[[ -z "${dev}" ]] && dev=$(mount | grep "${mnt} " | head -1 | awk '{print $1}' | sed "s|p[0-9]$||")
	if [[ -z "${dev}" ]]; then
		echo "ERROR: No image mounted for \"${1}\".  Aborting..."
	else
		for part in $(mount | grep -e "${dev/dev/mnt}/" | awk '{print $3}' | tac); do sudo umount $part; done
		sudo umount ${dev/dev/mnt}
		sudo losetup -d ${dev}
		sudo rmdir ${dev/dev/mnt}
	fi
}
function bpiwrt
{
	local count=0
	local dev=""
	while true; do
		ifconfig | grep -B1 -e "192\.168\.2\.[0-9]*" | grep -o "^\w*" >& /dev/null && break
		clear
		echo -e "\033[1;32m============ Waiting $(printf "%3d" ${count}) seconds ============\033[0m"
		count=$(( count + 1))
		ls /sys/class/net | grep -v lo | while read iface; do ifconfig ${iface}; done
		echo -e "\033[1;32m=============================================\033[0m"
		sleep 1
	done
	ssh root@bpiwrt.local
}
