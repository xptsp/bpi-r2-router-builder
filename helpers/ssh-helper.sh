#!/bin/bash
# Regenerate the missing SSH keys if missing:
if ! test -e /etc/ssh/ssh_host_rsa_key; then
	ssh-keygen -N "" -t rsa -f /etc/ssh/ssh_host_rsa_key
	ssh-keygen -N "" -t ed25519 -f /etc/ssh/ssh_host_ed25519_key
	ssh-keygen -N "" -t ecdsa -f /etc/ssh/ssh_host_ecdsa_key
fi

# Turn the blue light on on the side opposite the network ports:
echo 1 > /sys/class/leds/bpi-r2:pio:blue/brightness

exit 0
