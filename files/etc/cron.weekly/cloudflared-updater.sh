#!/bin/bash
cd /tmp
wget https://bin.equinox.io/c/VdrWdbjqyF/cloudflared-stable-linux-arm.tgz
tar -xvzf cloudflared-stable-linux-arm.tgz
systemctl stop cloudflared@1
systemctl stop cloudflared@2
systemctl stop cloudflared@3
rm /usr/local/bin/cloudflared*
FILE=/usr/local/bin/cloudflared-v$(./cloudflared -v | cut -d" " -f 3)
cp ./cloudflared ${FILE}
chmod +x ${FILE}
ln -sf ${FILE} /usr/local/bin/cloudflared
systemctl start cloudflared@1
systemctl start cloudflared@2
systemctl start cloudflared@3
rm /tmp/cloudflared*
