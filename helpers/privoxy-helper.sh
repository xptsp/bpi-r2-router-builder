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

# If no Adblock files exist, call the "privoxy-blocklist.sh" script:
grep -q "\.adblock\." /etc/privoxy/config || /usr/local/bin/privoxy-blocklist.sh -w &
