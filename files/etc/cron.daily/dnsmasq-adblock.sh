#!/bin/bash
wget -q https://github.com/notracking/hosts-blocklists/raw/master/domains.txt -O /etc/dnsmasq.d/domains.txt
wget -q https://github.com/notracking/hosts-blocklists/raw/master/hostnames.txt -O /etc/dnsmasq.d/hostnames.txt
systemctl restart dnsmasq

