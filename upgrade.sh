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
	DEST=/${2:-"$1"}
	COPY=false
	SRC=$(echo ${PWD}/$1 | sed "s|/ro/|/|g")
	for cfile in ${COPY_ONLY[@]}; do if [[ "$1" =~ ^${cfile} ]]; then COPY=true; fi; done
	if [[ "$COPY" == "true" ]]; then
		echo -e -n "Copying ${BLUE}${SRC}${NC}... "
		if ! cp ${SRC} /${DEST}; then
			echo -e "${RED}FAIL!${NC}"
		else
			echo -e "${GREEN}Success!${NC}"
		fi
	else
		INFO=($(ls -l /${DEST}))
		if [[ ! "${INFO[-1]}" =~ ^$(dirname $0) ]]; then
			rm /${DEST}
			echo -e -n "Linking ${BLUE}${SRC}${NC}... "
			if ! ln -sf ${SRC} /${DEST}; then
				echo -e "${RED}FAIL!${NC}"
			else
				echo -e "${GREEN}Success!${NC}"
			fi
		fi
	fi
}

cd $(dirname $0)/files
cp -R boot/* boot/
for file in $(find etc/* -type f); do replace $file; done
for file in $(find lib/systemd/system/* -type d); do replace $file; done
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
