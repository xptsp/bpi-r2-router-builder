#!/bin/bash
TAB1=MINIUPNPD
TAB2=MINIUPNPD-POSTROUTING
IPT=/usr/sbin/iptables-legacy
if ! ${IPT} --list-rules -t nat | grep -e "^-N ${TAB1}$" >& /dev/null; then
	${IPT} -t nat -N ${TAB1}
	echo "Added ${TAB1} chain in NAT table"
fi
if ! ${IPT} --list-rules -t nat | grep -e "^-N ${TAB2}$" >& /dev/null; then
	${IPT} -t nat -N ${TAB2}
	echo "Added ${TAB2} chain in NAT table"
fi
if ! ${IPT} --list-rules -t nat | grep -e "^-A PREROUTING -i wan -j MINIUPNPD" >& /dev/null; then
	${IPT} -t nat -A PREROUTING -i wan -j MINIUPNPD
	echo "Added ${TAB1} prerouting rule to NAT table"
fi
if ! ${IPT} --list-rules -t nat | grep -e "^-A MINIUPNPD-POSTROUTING" >& /dev/null; then
	${IPT} -t nat -A ${TAB2} -o wan -j MINIUPNPD-POSTROUTING
	echo "Added ${TAB2} postrouting rule to NAT table"
fi
if ! ${IPT} --list-rules -t filter | grep -e "^-N ${TAB1}$" >& /dev/null; then
	${IPT} -t filter -N ${TAB1}
	echo "Added ${TAB1} chain in FILTER table"
fi
