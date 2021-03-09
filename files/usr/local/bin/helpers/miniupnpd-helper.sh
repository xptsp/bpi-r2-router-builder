#!/bin/bash
TAB1=MINIUPNPD
TAB2=MINIUPNPD-POSTROUTING
IPT=/usr/sbin/iptables-legacy
case "$1" in
	"start")
		if ${IPT} --list-rules -t nat | grep -e "^-N ${TAB1}$" >& /dev/null; then
			${IPT} -t nat -F ${TAB1}
			echo "Flushed ${TAB1} chain in NAT table"
		else
			${IPT} -t nat -N ${TAB1}
			echo "Added ${TAB1} chain in NAT table"
		fi
		if ${IPT} --list-rules -t nat | grep -e "^-N ${TAB2}$" >& /dev/null; then
			${IPT} -t nat -F ${TAB2}
			echo "Flushed ${TAB2} chain in NAT table"
		else
			${IPT} -t nat -N ${TAB2}
			echo "Added ${TAB2} chain in NAT table"
		fi
		if ! ${IPT} --list-rules -t nat | grep -e "^-A PREROUTING -i wan -j ${TAB1}" >& /dev/null; then
			${IPT} -t nat -A PREROUTING -i wan -j ${TAB1}
			echo "Added prerouting rule for ${TAB1} chain to NAT table"
		fi
		if ${IPT} --list-rules -t filter | grep -e "^-N ${TAB1}$" >& /dev/null; then
			${IPT} -t filter -F ${TAB1}
			echo "Flushed ${TAB1} chain in FILTER table"
		else
			${IPT} -t filter -N ${TAB1}
			echo "Added ${TAB1} chain in FILTER table"
		fi
		exit 0
		;;
		
	"stop")
		if ${IPT} --list-rules -t nat | grep -e "^-A PREROUTING -i wan -j ${TAB1}" >& /dev/null; then
			${IPT} -t nat -D PREROUTING -i wan -j MINIUPNPD
			echo "Removed prerouting rule for ${TAB1} chain from NAT table"
		fi
		if ${IPT} --list-rules -t nat | grep -e "^-N ${TAB1}$" >& /dev/null; then
			${IPT} -t nat -F ${TAB1}
			${IPT} -t nat -X ${TAB1}
			echo "Removed ${TAB1} chain in NAT table"
		fi
		if ${IPT} --list-rules -t nat | grep -e "^-N ${TAB2}$" >& /dev/null; then
			${IPT} -t nat -F ${TAB2}
			${IPT} -t nat -X ${TAB2}
			echo "Removed ${TAB2} chain in NAT table"
		fi
		if ${IPT} --list-rules -t filter | grep -e "^-N ${TAB1}$" >& /dev/null; then
			${IPT} -t filter -F ${TAB1}
			${IPT} -t filter -X ${TAB1}
			echo "Removed ${TAB1} chain in FILTER table"
		fi
		exit 0
		;;
	
	*)
		echo "Usage: $0 (start|stop)"
		;;
esac
