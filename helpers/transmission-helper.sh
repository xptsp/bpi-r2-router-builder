#!/bin/bash
#############################################################################
# This helper script takes care of any tasks that should occur before
# launching the transmission-daemon program.  Tasks that occur here should not
# take very long to execute and should not rely on other services being up
# and running.
#############################################################################

#############################################################################
# Our global variables:
#############################################################################
TABLE=$(grep -m 1 "^table inet " /etc/nftables.conf | awk '{print $3}')
SETTINGS=/etc/transmission-daemon/settings.json
test -f /etc/default/transmission-daemon && source /etc/default/transmission-daemon

#############################################################################
# Are we starting the service?
#############################################################################
if [[ "$1" == "start" ]]; then
	# Get IPv4 and IPv6 address from the VPN client interface to bind to.
	IFACE=$(sudo $0 init | grep "^VPN=" | cut -d= -f 2)
	unset BIND_IPv4 BIND_IPv6
	if [[ ! -z "${IFACE}" ]]; then
		BIND_IPv4=$(ip addr show ${IFACE} | grep -m 1 inet | awk '{print $2}' | cut -d/ -f 1)
		BIND_IPv6=$(ip addr show ${IFACE} | grep -m 1 inet6 | awk '{print $2}' | cut -d/ -f 1)
	fi
	sed -i "s|\"bind-address-ipv4\": \".*|\"bind-address-ipv4\": \"${BIND_IPv4:-"255.255.255.1"}\",|" ${SETTINGS}
	sed -i "s|\"bind-address-ipv6\": \".*|\"bind-address-ipv6\": \"${BIND_IPv6:-"fe80::"}\",|" ${SETTINGS}

	# Read transmission-daemon defaults and set credentials for WebUI:
	# << Defaults >> Username: pi    Password: bananapi
	sed -i "s|\"rpc-username\": \".*|\"rpc-username\": \"${TRANS_USER:-"pi"}\",|" ${SETTINGS}
	sed -i "s|\"rpc-password\": \".*|\"rpc-password\": \"${TRANS_PASS:-"bananapi"}\",|" ${SETTINGS}

	# Start the daemon:
	exec /usr/bin/transmission-daemon -f --log-error --port=${TRANS_PORT:-"9091"} --no-portmap \
		--bind-address-ipv4=${IPv4:-"255.255.255.1"} --bind-address-ipv6=${IPv6:-"fe80::"} \
		--rpc-bind-address=127.0.0.1

#############################################################################
# Are we stopping the service?
#############################################################################
elif [[ "$1" == "stop" ]]; then
	sudo $0 deinit

#############################################################################
# Do we need to change the WebUI and add a firewall rule?
#############################################################################
elif [[ "$1" == "init" ]]; then
	# Change the transmission-daemon WebUI to choice in Router WebUI:
	DIR=/usr/share/transmission
	WEB=${DIR}/web
	if ! test -L ${WEB}; then
		test -d ${DIR}/original && rm -rf ${DIR}/original
		mv ${WEB} ${DIR}/original
	fi
	TRANS_WEBUI=${TRANS_WEBUI:-"combustion-release"}
	! test -d ${DIR}/${TRANS_WEBUI} && TRANS_WEBUI=original
	! test -d ${DIR}/${TRANS_WEBUI} && exit 1
	CUR=$(ls -l ${WEB} | awk '{print $NF}')
	if [[ "${CUR}" != "${DIR}/${TRANS_WEBUI}" ]]; then
		unlink ${WEB}
		ln -sf ${DIR}/${TRANS_WEBUI} ${WEB}
	fi

	# Add a firewall rule allowing inbound traffic over the VPN client:
	IFACE=$(nft list set inet ${TABLE} DEV_VPN_CLIENT | grep "elements" | cut -d\" -f 2)
	if [[ ! -z "${IFACE}" ]]; then
		PEER=$(grep '"peer-port": [0-9]*' ${SETTINGS} | awk '{print $2}' | sed "s|,||")
		nft add rule inet ${TABLE} input_vpn_client tcp dport ${PEER:-"51543"} accept comment "transmission-daemon"
	fi
	echo "VPN=$IFACE"

#############################################################################
# Do we need to remove the firewall rule for VPN traffic?
#############################################################################
elif [[ "$1" == "deinit" ]]; then
	nft delete rule inet ${TABLE} input_vpn_client handle $(nft -a list chain inet ${TABLE} input_vpn_client | grep "transmission-daemon" | awk '{print $NF}')
fi

#############################################################################
# Return error code 0 to caller:
#############################################################################
exit 0
