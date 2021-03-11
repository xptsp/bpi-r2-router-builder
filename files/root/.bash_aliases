upnpc()
{
	UPNP_URL=
	UPNP_IP=($(ifconfig br0 | grep " inet "))
	UPNP_PORT=$(cat /etc/miniupnpd/miniupnpd.conf | grep -e "^http_port=" | cut -d"=" -f 2)
	[[ ! -z "${UPNP_PORT}" && "${UPNP_PORT}" -gt 0 ]] && UPNP_URL="-u http://${UPNP_IP[1]}:${UPNP_PORT}/rootDesc.xml"
	/usr/bin/upnpc ${UPNP_URL} $@
}
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
