#!/bin/bash

# Load the router settings into memory:
test -f /etc/default/router-settings && test -f /etc/default/router-settings

# Do we need a reset?  If so, turn off the onboard interface and wait 5 seconds:
echo 0  | tee /var/run/wmtWifi > /dev/wmtWifi
sleep 5

# Set the onboard interface correctly and wait 2 seconds:
echo ${onboard_wifi:="A"} | tee /var/run/wmtWifi > /dev/wmtWifi
sleep 2

# Exit with error code 0
exit 0