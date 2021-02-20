#!/bin/bash
DEV=/dev/sda2
[[ -f /etc/default/docker-helper ]] && source /etc/default/docker-helper
[[ -z "${DEV}" || ! -e ${DEV} ]] && exit 0
if mount | grep "${DEV} " >& /dev/null; then
	if ! mount | grep " /var/lib/docker "; then
		DIR=$(mount | grep ${DEV} | cut -d" " -f 3)
		[[ ! -d ${DIR}/docker ]] && cp -R /var/lib/docker ${DIR}/docker
		mount --bind ${DIR}/docker /var/lib/docker
	fi
fi
exit 0
