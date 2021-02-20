cls()
{
	clear
}
los()
{
	img="$1"
	if [[ -z "$img" || ! -f "$img" ]]; then
		echo "Syntax: los [filename]"
	else
		dev="$(sudo losetup --show -f -P "$img")"
		echo "$dev"
		for part in "$dev"?*; do
			if [ "$part" = "${dev}p*" ]; then
				part="${dev}"
			fi
			dst="/mnt/$(basename "$part")"
			echo "$dst"
			sudo mkdir -p "$dst"
			sudo mount "$part" "$dst"
		done
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
		echo "ERROR: No image mounted on ${dev}.  Aborting..."
		return 0
	fi
	for part in "${dev}"?*; do
		[ "${part}" = "${dev}p*" ] && part="${dev}"
		dst="/mnt/$(basename "$part")"
		sudo umount "$dst" && sudo rmdir "$dst"
	done
	sudo losetup -d "$dev"
}
losl() 
{
	sudo losetup -l
}
