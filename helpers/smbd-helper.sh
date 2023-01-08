#!/bin/bash
#############################################################################
# This helper script takes care of any tasks that should occur before the 
# samba (smbd) service officially starts.  Tasks that occur here should not
# take very long to execute and should not rely on other services being up
# and running.
#############################################################################

# Change which interfaces service "smbd" binds to:
FILE=/etc/samba/smb.conf
cd /etc/network/interfaces.d
IFACES=($(grep "address" $(grep -L "masquerade" *) | cut -d: -f 1))
IFACES="${IFACES[@]}"
sed -i "s|interfaces = .*$|interfaces = ${IFACES}|" ${FILE}

# Exit with error code 0:
exit 0
