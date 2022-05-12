#!/bin/bash
#############################################################################
# This helper script takes care of any tasks that should occur before the 
# docker service officially starts.  Tasks that occur here should not
# take very long to execute and should not rely on other services being up
# and running.
#############################################################################

if ! mount | grep " /var/lib/docker " >& /dev/null; then
	DEV=$(blkid | grep "LABEL=\"DOCKER\"" | cut -d: -f 1)
	[[ ! -z "${DEV}" ]] && mount ${DEV} /var/lib/docker
fi
if ! test -d /var/lib/docker/data; then
	mkdir -p /var/lib/docker/data
	cp /dev/null /var/lib/docker/data/docker-compose.yaml
	chown pi:pi -R /var/lib/docker/data
fi
exit 0
