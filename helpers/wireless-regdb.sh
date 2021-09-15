#!/bin/bash

#####################################################################################
# Pull the latest version of the Wifi Regulatory Database:
#####################################################################################
cd /opt/wireless-regdb
git pull

#####################################################################################
# Perform same operations in the read-only partition:
#####################################################################################
RW=($(mount | grep " /ro "))
if [[ ! -z "${RW[5]}" ]]; then
	[[ "${RW[5]}" == *ro,* ]] && mount -o remount,rw /ro
	chroot /ro /opt/bpi-r2-router-builder/helper/wireless-regdb.sh
	[[ "${RW[5]}" == *ro,* ]] && mount -o remount,ro /ro
fi

