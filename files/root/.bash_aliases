alias docker-compose='docker compose'
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
		local root=$(blkid | grep "^${dev}p" | grep "LABEL=\"BPI-ROOT\"" | cut -d: -f 1)
		local boot=$(blkid | grep "^${dev}p" | grep "LABEL=\"BPI-BOOT\"" | cut -d: -f 1)
		if [[ -z "${boot}" || -z "${root}" ]]; then
			for part in ${dev}p*; do
				sudo mkdir -p ${part/dev/mnt}
				sudo mount ${part} ${part/dev/mnt}
			done
		else
			sudo mkdir -p ${dest}
			sudo mount ${root} ${dest}
			if [[ -d ${dest}/@ ]]; then
				sudo umount ${dev}p2
				sudo mount -o subvol=@ ${dev}p2 ${dest}
			fi 
			sudo mkdir -p ${dest}/boot
			sudo mount ${boot} ${dest}/boot
			test -d ${dest}/proc && sudo mount --bind /proc ${dest}/proc
			test -d ${dest}/sys && sudo mount --bind /sys ${dest}/sys
			test -d ${dest}/tmp && sudo mount --bind /tmp ${dest}/tmp
			test -d ${dest}/dev && sudo mount --bind /dev ${dest}/dev
			test -d ${dest}/var/lib/apt/lists && sudo mount -t tmpfs tmpfs ${dest}/var/lib/apt/lists
			test -d ${dest}/var/cache/apt && sudo mount -t tmpfs tmpfs ${dest}/var/cache/apt
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
