#!/bin/bash

# Load the router settings into memory:
test -f /etc/default/router-settings && test -f /etc/default/router-settings

# If we are stopping the service, then we need a reset:
[[ "$1" == "stop" ]] && RESET=Y

# If we are starting the service AND the device type isn't right, then we need a reset:
touch /var/run/wmtWifi
[[ "$1" == "start" && "$(cat /var/run/wmtWifi)" != "${onboard_wifi:="A"}" ]] && RESET=Y

# Do we need a reset?  If so, turn off the onboard interface and wait 5 seconds:
[[ "${RESET:="N"}" == "Y" ]] && echo 0 >/dev/wmtWifi && sleep 5

# Set the onboard interface correctly and wait 2 seconds:
echo ${onboard_wifi:="A"} | tee /var/run/wmtWifi > /dev/wmtWifi
sleep 2

# Exit with error code 0
exit 0