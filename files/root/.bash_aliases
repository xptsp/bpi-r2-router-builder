alias losl='losetup -l'
los()
{
	img="$1"
	if [[ -z "$img" || ! -f "$img" ]]; then
		echo "Syntax: los [filename]"
	else
		dev="$(sudo losetup --show -f -P "$img")"
		dest=${dev/dev/mnt}
		echo $dest
		if [[ ! "$(basename $1)" =~ "bpiwrt_" ]]; then
			for part in ${dev}p*; do
				sudo mkdir -p ${dest} && sudo mount ${part} ${dest}
			done
		else
			sudo mkdir -p ${dest}
			sudo mount ${dev}p2 ${dest}
			sudo mkdir ${dest}/boot 2> /dev/null
			sudo mount ${dev}p1 ${dest}/boot
		fi
	fi
}
losd()
{
	if [[ -z "$1" ]]; then
		echo "Syntax: losd [loop device number]"
		return 0
	fi
	[[ "$1" =~ "{$1/mnt/dev}" ]] && dev=${1/mnt/dev}
	if [[ -f /dev/loop${1} ]]; then
		dev=/dev/loop${1}
	else
		[[ ! -z "$1" ]] && dev=$(losetup -l | grep "${1}" | cut -d" " -f 1)
		if [[ -z "${dev}" ]]; then
			dev=$(mount | grep "${1} " | head -1 | awk '{print $1}')
			dev=${dev/p2/}
		fi
	fi
	if [[ -z "${dev}" ]]; then
		echo "ERROR: No image mounted for \"${1}\".  Aborting..."
	elif ! losetup -l | grep "${dev}" >& /dev/null; then
		echo "ERROR: No image mounted for \"${1}\".  Aborting..."
	else
		for part in $(mount | grep ${dev}p | awk '{print $3}' | sort -r); do 
			sudo umount $part
			[[ ! "${part}" =~ /boot$ ]] && sudo rmdir $part 2> /dev/null
		done
		sudo losetup -d "$dev"
	fi
}
