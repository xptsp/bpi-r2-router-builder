#!/bin/bash
cd /tmp
wget https://bin.equinox.io/c/VdrWdbjqyF/cloudflared-stable-linux-arm.tgz
tar -xvzf cloudflared-stable-linux-arm.tgz
sudo systemctl stop cloudflared@1
sudo systemctl stop cloudflared@2
sudo systemctl stop cloudflared@3
sudo cp ./cloudflared /usr/local/bin
sudo chmod +x /usr/local/bin/cloudflared
sudo systemctl start cloudflared@1
sudo systemctl start cloudflared@2
sudo systemctl start cloudflared@3
