#!/bin/bash
mount | grep " /var/lib/docker " >& /dev/null || mount LABEL=DOCKER /var/lib/docker
if ! test -d /var/lib/docker/data; then
	mkdir -p /var/lib/docker/data
	cp /dev/null /var/lib/docker/data/docker-compose.yaml
	chown pi:pi -R /var/lib/docker/data
fi
exit 0
