#!/bin/bash
[[ -e /etc/ssh/ssh_host_rsa_key ]] && exit 0
ssh-keygen -N "" -t rsa -f /etc/ssh/ssh_host_rsa_key
ssh-keygen -N "" -t ed25519 -f /etc/ssh/ssh_host_ed25519_key
ssh-keygen -N "" -t ecdsa -f /etc/ssh/ssh_host_ecdsa_key
