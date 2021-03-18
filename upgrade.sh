#!/bin/bash

COPY_ONLY=(
	etc/network/interfaces.d
	etc/network/dnsmasq.d
	etc/fstab
	etc/transmission-daemon/settings.json
	etc/default/
	etc/overlayRoot.conf
)

function replace()
{
	DEST=${2:-"1"}
	test -e /$DEST && rm /$DEST
	COPY=false
	for cfile in ${COPY_ONLY[@]}; do if [[ "$1" =~ ^${cfile} ]]; then COPY=true; fi; done
	if [[ "$COPY" == "true" ]]; then
		cp $PWD/$1 /$DEST
	else
		ln -sf $PWD/$1 /$DEST || cp $PWD/$1 /$DEST
	fi
}

cd $(dirname $0)/files
cp -R boot/* /boot/
cp -R opt/* /opt/
for file in $(find etc/* -type f); do replace $file; done
for file in $(find lib/systemd/system/* -type f); do replace $file; done
for file in $(find usr/* -type f); do replace $file; done
for file in $(find root/.b* -type f); do
	replace $file
	replace $file /etc/skel/${file/root/}
	replace $file /home/pi/${file/root/}
	replace $file /home/vpn/${file/root/}
done
for file in $(find sbin/* -type f); do replace $file; done
mkdir -p /usr/local/bin/helpers
for file in $(find usr/* -type f); do replace $file; done
