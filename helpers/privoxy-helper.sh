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

# Remove any non-existant files from the Privoxy configuration file:
FILE=/etc/privoxy/config
grep "^actionsfile " ${CONFIG} | cut -d" " -f 2 | while read file; do test -f $file || sed -i "/$file/d" ${CONFIG}; done
grep "^filterfile " ${CONFIG} | awk '{print $2}' | while read file; do test -f $file || sed -i "/$file/d" ${CONFIG}; done
