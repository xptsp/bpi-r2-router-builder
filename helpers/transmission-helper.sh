#!/bin/bash
#############################################################################
# This helper script takes care of any tasks that should occur before the
# transmission service officially starts.  Tasks that occur here should not
# take very long to execute and should not rely on other services being up
# and running.
#############################################################################
if [[ "${UID}" -ne 0 ]]; then
	sudo $0 $@
	exit $?
fi

# Forward all traffic on the peer port to the transmission daemon:
if [[ "$1" == "start" ]]; then
	iptables -I PREROUTING -t nat -i ${TRANS_IFACE:-"wan"} -p tcp --dport ${TRANS_PEERPORT:-"51543"} -j DNAT --to 127.0.0.1:${TRANS_PEERPORT:-"51543"}
	iptables -I FORWARD -p tcp -d 127.0.0.1 --dport ${TRANS_PEERPORT:-"51543"} -j ACCEPT
elif [[ "$1" == "stop" ]]; then
	iptables -D PREROUTING -t nat -i ${TRANS_IFACE:-"wan"} -p tcp --dport ${TRANS_PEERPORT:-"51543"} -j DNAT --to 127.0.0.1:${TRANS_PEERPORT:-"51543"}
	iptables -D FORWARD -p tcp -d 127.0.0.1 --dport ${TRANS_PEERPORT:-"51543"} -j ACCEPT
fi
exit 0

