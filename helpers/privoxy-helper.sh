#!/bin/bash
#############################################################################
# This helper script takes care of any tasks that should occur before the
# privoxy service officially starts.  Tasks that occur here should not take
# very long to execute and should not rely on other services being up
# and running.
#############################################################################
# We need to be root in order to execute everything after this:
if [[ "${UID}" -ne 0 ]]; then
	sudo $0 $@
	exit $?
fi

#############################################################################
# PRE => Remove non-existant or zero-size files from configuration file:
#############################################################################
if [[ "$1" == "pre" ]]; then
	DIR=/etc/privoxy
	CONFIG=${DIR}/config
	grep -E "^(filter|actions)file " ${CONFIG} | awk '{print $2}' | while read FILE; do
		test -f ${DIR}/${FILE} && [[ "$(wc -c < ${DIR}/${FILE} 2> /dev/null)" -eq 0 ]] && rm ${DIR}/${FILE}
		test -f ${DIR}/${FILE} || sed -i "/${FILE}/d" ${CONFIG}
	done

#############################################################################
# POST => If no Adblock files exist, call the "privoxy-blocklist.sh" script 
#    ONLY after our internet connection is up:
#############################################################################
elif [[ "$1" == "post" ]]; then
	if ! grep -q "\.adblock\." /etc/privoxy/config; then
		echo "Waiting for Internet..."
		while ! ping -c 1 -W 1 1.1.1.1; do sleep 1; done
		/usr/local/bin/privoxy-blocklist.sh
	fi
fi

#############################################################################
# Exit with error code 0
#############################################################################
exit 0
