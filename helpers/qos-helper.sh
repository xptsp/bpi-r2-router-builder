#!/bin/bash

# Make sure that an interface is specified:
IFACE=$2
if [[ -z "${IFACE}" ]]; then echo "ERROR: No interface specified!"; exit 1; fi

# Process the command intended
case "$1" in
	start)
		tc qdisc add dev ${IFACE} root handle 1: prio priomap 2 2 2 2 2 2 2 2 1 1 1 1 1 1 1 0
		tc qdisc add dev ${IFACE} parent 1:1 handle 10: sfq limit 3000
		tc qdisc add dev ${IFACE} parent 1:2 handle 20: sfq
		tc qdisc add dev ${IFACE} parent 1:3 handle 30: sfq
		tc filter add dev ${IFACE} protocol ip parent 1: prio 1 u32 match ip tos 0x10 0xff flowid 1:2
		;;

	stop)
		tc qdisc del dev ${IFACE} root
		;;

	status)
		RULES=$(tc -name qdisc | grep wan | wc -l)
		[[ "${RULES}" -gt 1 ]] && echo "Active" || echo "Inactive"
		;;

	add|del)
		# Put the interfaces into environment variables:
		RANGE=${3}
		PRIO=${4:-"3"}

		# Validate parameters passed:
		if [[ -z "${PORT}" ]]; then echo "ERROR: No port(s) specified as 3rd parameter!"; exit 1; fi
		if [[ -z "${PRIO}" ]]; then echo "ERROR: No priority specified as 4th parameter!"; exit 1; fi
		if [[ "${PRIO}" -lt 0 || "${PRIO}" -gt 3 ]]; then echo "ERROR: Invalid priority specified as 4th parameter!  Placing in 3rd priority..."; PRIO=3; fi

		# Add requested TC rules:
		tc filter $1 dev ${IFACE} protocol ip parent 1: prio 1 u32 match ip dport ${PORT} 0xffff flowid 1:${PRIO} || exit $?
		tc filter $1 dev ${IFACE} protocol ip parent 1: prio 1 u32 match ip sport 4569 0xffff flowid 1:${PRIO} || exit $?
		;;

	restart)
		$0 stop
		$0 start
		;;

	*)
		echo "Syntax: $0 [start|stop|status|restart]"
		;;
esac
