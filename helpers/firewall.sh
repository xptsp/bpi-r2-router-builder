#!/usr/bin/php
#############################################################################
# This helper script takes care of firewall rule creation.
#############################################################################

<?php
#####################################################################################
echo "Creating iptables rules file which removes all non-docker rules...\n";
@shell_exec('(echo "*filter"; /sbin/iptables --list-rules | grep -v -i docker | grep "^-A" | sed "s|-A|-D|g"; echo "COMMIT") > /tmp/iptables.rules');

#####################################################################################
echo "Removing non-docker iptables rules...\n";
@shell_exec('/sbin/iptables-restore --noflush < /tmp/iptables.rules');

#####################################################################################
echo "Removing temporary iptables rules file...\n";
@shell_exec('rm /tmp/iptables.rules');

#####################################################################################
echo "Putting IPv4 rules into place...\n";
@shell_exec('/sbin/iptables-restore < /etc/network/iptables.rules');

#####################################################################################
echo "Putting IPv6 rules into place...\n";
@shell_exec('cat /etc/network/iptables.rules | grep -v "10.2" | grep -v "192.168" | /sbin/ip6tables-restore');

#####################################################################################
# NOTE: If the "CONFIG_NETFILTER_XT_MATCH_OWNER" isn't enabled during kernel compilation, this line will fail.
#   Moving it outside the "iptables.rules" file keeps the networking service from failing to initialize.
echo "Adding rule to prevent user \"vpn\" from sending anything...\n";
@shell_exec('iptables -A OUTPUT ! -o lo -m owner --uid-owner vpn -j DROP');

#####################################################################################
# Define default configuration, then read WebUI configuration into memory:
echo "Defining default WebUI configuration:\n";
$rules = array(
	'disable-ping' => true,
	'drop-ping' => true,
);

#####################################################################################
echo "Reading WebUI configuration...\n";
if (file_exists('/var/opt/router-builder/config.json'))
	$rules = json_decode(@file_get_contents('/var/opt/router-builder/config.json'), true);

#####################################################################################
# Block pings from the internet?
if (!empty($rules['disable-ping']))
{
	echo "Adding rule to block pings from the internet:\n";
	@shell_exec('/usr/sbin/iptables -I INPUT -i wan -p icmp --icmp-type echo-request -j ' . (!empty($rules['drop-ping']) ? 'DROP' : 'REJECT'));
}
