#!/bin/bash
#############################################################################
# This helper script replaces the init routines that miniupnpd uses.  This
# is done so that (1) miniupnpd works with my firewall, and (2) the service
# doesn't purge the firewall rules when stopped.
#############################################################################
TABLE=$(grep -m 1 "^table " /etc/nftables.conf | awk '{print $3}')
FWD_CHAIN=forward_miniupnpd
PRE_CHAIN=nat_prerouting_miniupnpd
PST_CHAIN=nat_postrouting_miniupnpd

##################################################################################
# START => Set up the service to start correctly with my firewall:
##################################################################################
if [[ "$1" == "start" ]]; then
	# Set table and chain names for compatibility with the firewall:
	FILE=/etc/miniupnpd/miniupnpd.conf
	if [[ "$(grep "^upnp_table_name=" $FILE | cut -d= -f 2)" != "${TABLE}" ]]; then
		sed -i "s|^upnp_table_name=.*|upnp_table_name=${TABLE}|" ${FILE}
	fi
	if [[ "$(grep "^upnp_nat_table_name=" $FILE | cut -d= -f 2)" != "${TABLE}" ]]; then
		sed -i "s|^upnp_nat_table_name=.*|upnp_nat_table_name=${TABLE}|" ${FILE}
	fi
	if [[ "$(grep "^upnp_forward_chain=" $FILE | cut -d= -f 2)" != "${FWD_CHAIN}" ]]; then
		sed -i "s|^upnp_forward_chain=.*|upnp_forward_chain=${FWD_CHAIN}|" ${FILE}
	fi
	if [[ "$(grep "^upnp_nat_chain=" $FILE | cut -d= -f 2)" != "${PRE_CHAIN}" ]]; then
		 sed -i "s|^upnp_nat_chain=.*|upnp_nat_chain=${PRE_CHAIN}|" ${FILE}
	fi
	if [[ "$(grep "^upnp_nat_postrouting_chain=" $FILE | cut -d= -f 2)" != "${PST_CHAIN}" ]]; then
		sed -i "s|^upnp_nat_postrouting_chain=.*|upnp_nat_postrouting_chain=${PST_CHAIN}|" ${FILE}
	fi

	# Add a route for 239.0.0.0 for each interface that miniupnpd listens on:
	for iface in $(cat ${FILE} | grep "^listening_ip" | cut -d= -f 2); do
		route add -net 224.0.0.0 netmask 240.0.0.0 $iface
	done

	# Create necessary chains if not already done so:
	nft list chain inet ${TABLE} ${FWD_CHAIN} >& /dev/null || nft add chain inet ${TABLE} ${FWD_CHAIN}
	nft list chain inet ${TABLE} ${PRE_CHAIN} >& /dev/null || nft add chain inet ${TABLE} ${PRE_CHAIN}
	nft list chain inet ${TABLE} ${PST_CHAIN} >& /dev/null || nft add chain inet ${TABLE} ${PST_CHAIN}

	# Create rules to jump to miniupnpd chains:
	nft list chain inet ${TABLE} forward | grep "jump ${FWD_CHAIN}" >& /dev/null || nft insert rule inet ${TABLE} forward jump ${FWD_CHAIN}
	nft list chain inet ${TABLE} nat_prerouting | grep "jump ${PRE_CHAIN}" >& /dev/null || nft insert rule inet ${TABLE} nat_prerouting jump ${PRE_CHAIN}
	nft list chain inet ${TABLE} nat_postrouting | grep "jump ${PST_CHAIN}" >& /dev/null || nft insert rule inet ${TABLE} nat_postrouting jump ${PST_CHAIN}

##################################################################################
# FLUSH => Called in place of "nft_removeall.sh" found in miniupnpd package:  
##################################################################################
elif [[ "$1" == "stop" ]]; then
	# Flush the miniupnpd tables:
	nft flush chain inet ${TABLE} ${FWD_CHAIN} >& /dev/null
	nft flush chain inet ${TABLE} ${PRE_CHAIN} >& /dev/null
	nft flush chain inet ${TABLE} ${PST_CHAIN} >& /dev/null
fi

##################################################################################
# Exit with error code 0:
##################################################################################
exit 0
