#!/bin/bash
RED='\033[1;31m'
GREEN='\033[1;32m'
NC='\033[0m'

# Files to copy only:
COPY_ONLY=(
	etc/network/interface.d/
	etc/network/dnsmasq.d/
	etc/fstab
	etc/transmission-daemon/settings.json
	etc/default/
	etc/overlayRoot.conf
)

function replace()
{
	DEST=${2:-"$1"}
	rm $DEST >& /dev/null
	COPY=false
	SRC=$(echo ${PWD}/$1 | sed "s|/ro/|/|g")
	for cfile in ${COPY_ONLY[@]}; do if [[ "$1" =~ ^${cfile} ]]; then COPY=true; fi; done
	if [[ "$COPY" == "true" ]]; then
		! cp ${SRC} ${DEST} && echo -e -n "Copying ${GREEN}${SRC}${NC} to ${GREEN}${DEST}${NC}... ${RED}Fail!${NC}"
	else
		! ln -sf ${SRC} ${DEST} && echo -e "Linking ${GREEN}${SRC}${NC} to ${GREEN}${DEST}${NC}... ${RED}Fail!${NC}"
	fi
}

cd $(dirname $0)/files
cp -R boot/* boot/
for file in $(find etc/* -type f); do replace $file; done
for file in $(find lib/systemd/system/* -type d); do replace $file; done
if ! test -d opt/docker-data; then
	mkdir -p opt/docker-data
	cp -R opt/docker-data/* opt/docker-data/
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
replace /var/www/router