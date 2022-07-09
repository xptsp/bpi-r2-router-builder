#!/bin/bash
systemctl stop cloudflared@1
systemctl stop cloudflared@2
systemctl stop cloudflared@3
wget https://github.com/cloudflare/cloudflared/releases/latest/download/cloudflared-linux-arm -O /usr/local/bin/cloudflared
chmod +x /usr/local/bin/cloudflared
systemctl start cloudflared@1
systemctl start cloudflared@2
systemctl start cloudflared@3
