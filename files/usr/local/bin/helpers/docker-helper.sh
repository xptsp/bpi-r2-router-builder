#!/bin/bash
! mount | grep " /var/lib/docker " >& /dev/null && mount LABEL=DOCKER /var/lib/docker
! test -d /var/lib/docker/data && mkdir -p /var/lib/docker/data
! mount | grep " /opt/docker-data " >& /dev/null && mount --bind /var/lib/docker/data /opt/docker-data
exit 0
