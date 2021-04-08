#!/bin/bash
SPC="--------"

function runCMD()
{
	CMD="$@"
	echo "$SPC CMD: ${CMD} $SPC"
	${CMD}
	echo -e "$SPC\n"
}

function setInterface()
{
	echo "Network interface: ${1}"
	runCMD "ip link set ${1} down"

	# Change MAC address:
	MAC=$(ifconfig ${1} | grep ether | awk '{print $2}')
	MAC=${MAC:0:${#MAC}-1}0
	runCMD "ifconfig ${1} hw ether ${MAC}"
	sed -i "s|bssid=.*|bssid=${MAC}|g" /etc/hostapd/${1}.conf

	# Rename network interface:
	SUB=100
	NEW=mt_24g
	[[ "${1}" == "rename"* ]] && NEW=mt_50g && SUB=110
	runCMD "ip link set ${1} name ${NEW}"
	runCMD "ip link set ${NEW} up"
	runCMD "ip addr add 192.168.${SUB}.1/24 dev ${NEW}"
}

# Enable DBDC on any MT76xx wifi card that supports it:
for file in /sys/kernel/debug/ieee80211/*; do 
        test -e $file/mt76/dbdc && echo 1 > $file/mt76/dbdc
done

# Rename the interfaces of the MT7615 card:
sleep 1
PCI=$(lspci | grep MEDIATEK | grep 7615 | cut -d" " -f 1)
if [[ ! -z "${PCI}" ]]; then
	cd /sys/class/net
	IFACES=($(ls -l | grep "${PCI}" | awk '{print $9}' | grep -v "^mt_"))
	for IFACE in ${IFACES[@]}; do
		setInterface ${IFACE} >& /var/run/mt7615-helper.${IFACE}.log
	done
fi
exit 0
