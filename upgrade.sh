#!/bin/bash

# Files to copy only:
COPY_ONLY=(
	etc/network/interface.d/
	etc/network/dnsmasq.d/
	etc/fstab
	etc/transmission-daemon/settings.json
	etc/default/
	etc/overlayRoot.conf
)

# Place of the root files to copy/link:
RO=

function replace()
{
	DEST=${2:-"1"}
	test -e ${RO}/$DEST && rm ${RO}/$DEST
	COPY=false
	for cfile in ${COPY_ONLY[@]}; do if [[ "$1" =~ ^${cfile} ]]; then COPY=true; fi; done
	if [[ "$COPY" == "true" ]]; then
		cp $PWD/$1 ${RO}/$DEST
	else
		ln -sf $PWD/$1 ${RO}/$DEST || cp $PWD/$1 ${RO}/$DEST
	fi
}

cd $(dirname $0)/files
cp -R boot/* ${RO}/boot/
for file in $(find etc/* -type f); do replace $file; done
for file in $(find lib/systemd/system/* -type f); do replace $file; done
if ! test -d ${RO}/opt/docker-data; then
	mkdir -p ${RO}/opt/docker-data
	cp -R opt/docker-data/* ${RO}/opt/docker-data/
fi
for file in $(find root/.b* -type f); do
	replace $file
	replace $file /etc/skel/${file/root/}
	replace $file /home/pi/${file/root/}
	replace $file /home/vpn/${file/root/}
done
for file in $(find sbin/* -type f); do replace $file; done
test -d /usr/local/bin/helpers || mkdir -p /usr/local/bin/helpers
for file in $(find usr/* -type f); do replace $file; done
