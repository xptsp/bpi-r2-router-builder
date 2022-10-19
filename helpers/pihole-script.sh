#!/bin/bash

# Figure out what the IP addresses should be:
FILE=/etc/dnsmasq.d/03-bpiwrt.conf
IP=($(ip addr show br0 | grep "inet " | awk '{print $2}' | cut -d/ -f 1))
IP1=${IP[0]}
IP2=${IP[1]:-"${IP[0]}"}

# If file exists and existing IP addresses are correct, then exit with code 0:  
if test -f ${FILE}; then
	OLD1=$(grep "/bpiwrt/" ${FILE} | cut -d/ -f 3) 
	OLD2=$(grep "/wpad/" ${FILE} | cut -d/ -f 3)
	OLD3=$(grep "/pi.hole/" ${FILE} | cut -d/ -f 3)
	[[ "${OLD1}" == "${IP1}" && "${OLD2}" == "${IP1}" && "${OLD3}" == "${IP2}" ]] && exit 0
fi

# Update the "03-server.conf" file: 
cat << EOF > ${FILE}
dhcp-option=252,"http://${IP1}/wpad.dat"
address=/bpiwrt.local/${IP1}
address=/bpiwrt/${IP1}
address=/wpad.local/${IP1}
address=/wpad/${IP1}
address=/pi.hole/${IP2}
address=/pihole.local/${IP2}
EOF
