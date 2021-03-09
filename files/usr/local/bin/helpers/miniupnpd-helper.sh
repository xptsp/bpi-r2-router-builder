#!/bin/bash
TAB1=MINIUPNPD
TAB2=MINIUPNPD-POSTROUTING
IPT=/usr/sbin/iptables-legacy
DEBUG=N
case "$1" in
	"start")
		#####################################################################################################
		# IPTable rules and chains for the MANGLE table
		#####################################################################################################
		[[ "${DEBUG}" == "Y" ]] && echo "A1"
		${IPT} -t mangle -N ${TAB1} >& /dev/null && echo "Added ${TAB1} chain to MANGLE table"
		[[ "${DEBUG}" == "Y" ]] && echo "A2"
		${IPT} -t mangle -F ${TAB1} >& /dev/null
		[[ "${DEBUG}" == "Y" ]] && echo "A3"
		if ! ${IPT} -t mangle -C PREROUTING -i wan -j ${TAB1} >& /dev/null; then
			[[ "${DEBUG}" == "Y" ]] && echo "A4"
			${IPT} -t mangle -A PREROUTING -i wan -j ${TAB1}
			echo "Added prerouting rule for ${TAB1} chain to MANGLE table"
		fi

		#####################################################################################################
		# IPTable rules and chains for the NAT table
		#####################################################################################################
		[[ "${DEBUG}" == "Y" ]] && echo "B1"
		${IPT} -t nat -N ${TAB1} >& /dev/null && echo "Added ${TAB1} chain to NAT table"
		[[ "${DEBUG}" == "Y" ]] && echo "B2"
		${IPT} -t nat -F ${TAB1} >& /dev/null
		[[ "${DEBUG}" == "Y" ]] && echo "B3"
		if ! ${IPT} -t nat -C PREROUTING -i wan -j ${TAB1} >& /dev/null; then
			[[ "${DEBUG}" == "Y" ]] && echo "B4"
			${IPT} -t nat -A PREROUTING -i wan -j ${TAB1}
			echo "Added prerouting rule for ${TAB1} chain to NAT table"
		fi

		[[ "${DEBUG}" == "Y" ]] && echo "C1"
		${IPT} -t nat -N ${TAB2} >& /dev/null && echo "Added ${TAB2} chain to NAT table"
		[[ "${DEBUG}" == "Y" ]] && echo "C2"
		${IPT} -t nat -F ${TAB2} >& /dev/null
		[[ "${DEBUG}" == "Y" ]] && echo "C3"
		if ! ${IPT} -t nat -C POSTROUTING -o wan -j ${TAB2} >& /dev/null; then
			[[ "${DEBUG}" == "Y" ]] && echo "C4"
			${IPT} -t nat -A POSTROUTING -o wan -j ${TAB2}
			echo "Added postrouting rule for ${TAB2} chain to NAT table"
		fi

		#####################################################################################################
		# IPTable rules and chains for the NAT table
		#####################################################################################################
		[[ "${DEBUG}" == "Y" ]] && echo "D1"
		${IPT} -t filter -N ${TAB1} >& /dev/null && echo "Added ${TAB1} chain to FILTER table"
		[[ "${DEBUG}" == "Y" ]] && echo "D2"
		${IPT} -t filter -F ${TAB1} >& /dev/null
		[[ "${DEBUG}" == "Y" ]] && echo "D3"
		if ! ${IPT} -t filter -C FORWARD -i wan -j ${TAB1} >& /dev/null; then
			[[ "${DEBUG}" == "Y" ]] && echo "D4"
			${IPT} -t filter -A FORWARD -i wan -j ${TAB1}
			echo "Added fowarding rule for ${TAB2} chain to FILTER table"
		fi
		exit 0
		;;

	"stop")
		#####################################################################################################
		# IPTable rules and chains for the MANGLE table
		#####################################################################################################
		[[ "${DEBUG}" == "Y" ]] && echo "E1"
		if ${IPT} -t mangle -C PREROUTING -i wan -j ${TAB1} >& /dev/null; then
			[[ "${DEBUG}" == "Y" ]] && echo "E2"
			${IPT} -t mangle -D PREROUTING -i wan -j ${TAB1}
			echo "Removed prerouting rule for ${TAB1} chain from MANGLE table"
		fi
		[[ "${DEBUG}" == "Y" ]] && echo "E3"
		${IPT} -t mangle -F ${TAB1} >& /dev/null
		[[ "${DEBUG}" == "Y" ]] && echo "E4"
		${IPT} -t mangle -X ${TAB1} >& /dev/null && echo "Removed ${TAB1} chain from MANGLE table"

		#####################################################################################################
		# IPTable rules and chains for the NAT table
		#####################################################################################################
		[[ "${DEBUG}" == "Y" ]] && echo "F1"
		if ${IPT} -t nat -C PREROUTING -i wan -j ${TAB1} >& /dev/null; then
			[[ "${DEBUG}" == "Y" ]] && echo "F2"
			${IPT} -t nat -D PREROUTING -i wan -j ${TAB1}
			echo "Removed prerouting rule for ${TAB1} chain from NAT table"
		fi
		[[ "${DEBUG}" == "Y" ]] && echo "F3"
		${IPT} -t nat -F ${TAB1} >& /dev/null
		[[ "${DEBUG}" == "Y" ]] && echo "F4"
		${IPT} -t nat -X ${TAB1} >& /dev/null && echo "Removed ${TAB1} chain from NAT table"

		[[ "${DEBUG}" == "Y" ]] && echo "G1"
		if ${IPT} -t nat -C POSTROUTING -o wan -j ${TAB2} >& /dev/null; then
			[[ "${DEBUG}" == "Y" ]] && echo "G2"
			${IPT} -t nat -D POSTROUTING -o wan -j ${TAB2}
			echo "Removed postrouting rule for ${TAB2} chain from NAT table"
		fi
		[[ "${DEBUG}" == "Y" ]] && echo "G3"
		${IPT} -t nat -F ${TAB2} >& /dev/null
		[[ "${DEBUG}" == "Y" ]] && echo "G4"
		${IPT} -t nat -X ${TAB2} >& /dev/null && echo "Removed ${TAB2} chain from NAT table"

		#####################################################################################################
		# IPTable rules and chains for the NAT table
		#####################################################################################################
		[[ "${DEBUG}" == "Y" ]] && echo "H1"
		if ${IPT} -t filter -C FORWARD -i wan -j ${TAB1} >& /dev/null; then
			[[ "${DEBUG}" == "Y" ]] && echo "H2"
			${IPT} -t filter -D FORWARD -i wan -j ${TAB1}
			echo "Removed fowarding rule for ${TAB2} chain from FILTER table"
		fi
		[[ "${DEBUG}" == "Y" ]] && echo "H3"
		${IPT} -t filter -F ${TAB1} >& /dev/null
		[[ "${DEBUG}" == "Y" ]] && echo "H4"
		${IPT} -t filter -X ${TAB1} >& /dev/null && echo "Removed ${TAB1} chain from FILTER table"
		exit 0
		;;


	*)
		echo "Usage: $0 (start|stop)"
		;;
esac
