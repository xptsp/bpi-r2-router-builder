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
		if [[ ! "$(basename $1)" =~ "bpiwrt_v" ]]; then
			for part in ${dev}p*; do
				sudo mkdir -p ${dest} && sudo mount ${part} ${dest} || return
			done
		else
			sudo mkdir -p ${dest}
			sudo mount ${dev}p2 ${dest} || return
			sudo mkdir ${dest}/boot 2> /dev/null
			sudo mount ${dev}p1 ${dest}/boot || return
		fi
	fi
}
losd()
{
	if [[ -z "$1" ]]; then
		echo "Syntax: losd [loop device number]"
		return 0
	fi
	dev=/dev/loop${1}
	if ! losetup -l | grep "${dev}" >& /dev/null; then
		dev=$(losetup -l | grep "$1" | cut -d" " -f 1)
	fi
	if ! losetup -l | grep "${dev}" >& /dev/null; then
		echo "ERROR: No image mounted for \"${1}\".  Aborting..."
	else
		for part in $(mount | grep ${dev}p | awk '{print $3}' | sort -r); do 
			sudo umount $part || return
			sudo rmdir $part
		done
		sudo losetup -d "$dev"
	fi
}
