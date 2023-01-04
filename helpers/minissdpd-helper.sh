#!/bin/bash
#############################################################################
# This helper script replaces the init routines that minissdpd uses, which
# overrides the default interface list specified by "/etc/default/minissdpd".
#############################################################################
cd /etc/network/interfaces.d
OPT=
for IFACE in $(grep -L "masquerade" * | cut -d: -f 1); do
	grep -q "static$" ${IFACE} && OPT="${OPT} -i ${IFACE}"
done
exec /usr/sbin/minissdpd ${OPT} ${MiniSSDPd_OTHER_OPTIONS}
