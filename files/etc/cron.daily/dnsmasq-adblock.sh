#!/bin/bash
wget -q https://github.com/notracking/hosts-blocklists/raw/master/domains.txt -O /etc/dnsmasq.d/30-domains.conf
wget -q https://github.com/notracking/hosts-blocklists/raw/master/hostnames.txt -O /etc/hosts.adblock
systemctl restart dnsmasq

