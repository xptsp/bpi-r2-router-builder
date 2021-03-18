#!/bin/bash

# If we are not doing this as root, we need to change to root now!
if [[ "${UID}" -ne 0 ]]; then
	sudo $0 $@
	exit $?
fi

# Copy files to their destination directories:
chown root:root -R files
cp -aR files/* /
cp /root/.bash* /etc/skel/
cp /root/.bash* /home/{pi,vpn}/
systemctl daemon-reload

# Secure the "authorized_keys" file so it can only be appended:
chattr +a /root/.ssh/authorized_keys

# Set some things before we start:
export DEBIAN_FRONTEND=noninteractive
update-alternatives --set iptables /usr/sbin/iptables-legacy

# Create a user named "pi", being a member of the "sudo" and "users" group.
useradd -m -G sudo,users -s /bin/bash pi
echo -e "bananapi\nbananapi" | passwd -q pi

# Create a user name "vpn", being a member of the "users" and "pi" group:
useradd -m -G users -s /usr/sbin/nologin vpn
usermod -aG vpn pi

# Create symlink to use "clear" as "cls":
ln -sf /usr/bin/clear /usr/local/bin/cls

# Set hostname:
echo "bpiwrt" > /etc/hostname

# Set IP address of both hostname and pi.hole
echo "192.168.2.1     bpiwrt" >> /etc/hosts
echo "192.168.2.1     pi.hole" >> /etc/hosts

# Remove known duplicate files:
[[ -e /etc/apt/trusted.gpg~ ]] && rm /etc/apt/trusted.gpg~
[[ -e /etc/network/interfaces~ ]] && rm /etc/network/interfaces~

# Enable packet forwarding on IPv4:
sed -i "s|#net.ipv4.ip_forward=1|net.ipv4.ip_forward=1|g" /etc/sysctl.conf

# Force automatic reboot after 1 second upon a kernel panic:
sed -i "/kernel.panic/d" /etc/sysctl.conf
echo "kernel.panic = 1" >> /etc/sysctl.conf

# Activate these changes:
sysctl -p

# Blacklist the module responsible for poweroffs on R2:
echo "blacklist mtk_pmic_keys" > /etc/modprobe.d/blacklist.conf

# Load the i2c-dev module at boot:
echo "i2c-dev" > /etc/modprobe.d/i2c.conf

# Refreshes the certificates:
update-ca-certificates -f

# Sets timezone to "America/Chicago":
rm /etc/localtime
ln -s /usr/share/zoneinfo/America/Chicago /etc/localtime

# Sets locale to "en_US.UTF-8":
sed -i "s|# en_US.UTF-8 UTF-8|en_US.UTF-8 UTF-8|g" /etc/locale.gen
locale-gen

# Activate the iptables rules so that we have internet access during installation:
/etc/network/if-pre-up.d/iptables

# Install any packages that need updating:
apt update
apt dist-upgrade -y

# Install a few packages, then create our custom login message:
apt install -y toilet pmount eject
rm /etc/motd
rm /etc/update-motd.d/10-uname
ln -s /var/run/motd /etc/motd

# Disable and stop hostapd service before we go further:
systemctl disable hostapd
systemctl stop hostapd

# Install some new stuff:
apt install -y git pciutils usbutils sudo iw wireless-tools net-tools wget curl lsb-release avahi-daemon avahi-discover libnss-mdns unzip vnstat debconf-utils
apt install -y vlan ipset traceroute nmap conntrack ndisc6 whois mtr iperf3 tcpdump ethtool irqbalance tree eject rng-tools parted initramfs-tools
systemctl enable avahi-daemon
systemctl start avahi-daemon

# Correct issue regarding random numbers:
echo 'HRNGDEVICE=/dev/urandom' >> /etc/default/rng-tools

# Stop vnstat service and purge logs:
systemctl stop vnstat
systemctl disable vnstat
rm /var/lib/vnstat/*

# Set Country-Code (regulary domain)
iw reg set ISO_3166-1_alpha-2
iw reg set US

# Modify the Samba configuration to make sharing USB sticks more automatic:
echo "samba-common samba-common/dhcp boolean true" | debconf-set-selections
apt install -y samba
sed -i "1s|^|include = /etc/samba/includes.conf\n\n|" /etc/samba/smb.conf
touch /etc/samba/includes.conf
sed -i "s|/var/run|/run|g" /lib/systemd/system/?mbd.service
systemctl daemon-reload
systemctl enable smbd
systemctl enable nmbd
systemctl restart smbd
echo -e "bananapi\nbananapi" | smbpasswd -a pi

# Install NGINX and PHP 7.3:
apt install -y nginx php7.3-fpm php7.3-cgi php7.3-xml php7.3-sqlite3 php7.3-intl apache2-utils php7.3-mysql php7.3-sqlite3 sqlite3 php7.3-zip openssl php7.3-curl
systemctl enable php7.3-fpm
systemctl start php7.3-fpm
mv /etc/nginx/sites-available/default /etc/nginx/sites-available/default.bak
mv /etc/nginx/sites-available/router /etc/nginx/sites-available/default
ln -sf /etc/nginx/sites-available/pihole /etc/nginx/sites-enabled/pihole
systemctl enable nginx
systemctl restart nginx

# Install TrueCrypt and HD-Idle:
wget https://github.com/stefansundin/truecrypt.deb/releases/download/7.1a-15/truecrypt-cli_7.1a-15_armhf.deb -O /tmp/truecrypt.deb
wget https://github.com/adelolmo/hd-idle/releases/download/v1.12/hd-idle_1.12_armhf.deb -O /tmp/hdidle.deb
apt install -y /tmp/*.deb
rm /tmp/*.deb
systemctl enable hd-idle
systemctl start hd-idle

# Pull ydns's bash-updater repo and modify to pull settings from elsewhere:
git clone https://github.com/ydns/bash-updater /opt/ydns-updater
sed -i "s|^YDNS_LASTIP_FILE|[[ -f /etc/default/ydns-updater ]] \&\& source /etc/default/ydns-updater\nYDNS_LASTIP_FILE|" /opt/ydns-updater/updater.sh
chown www-data:www-data /etc/default/ydns-updater

# Install OpenVPN and create user VPN:
apt install -y openvpn
cat << EOF > /etc/sysctl.d/9999-vpn.conf
net.ipv4.conf.all.rp_filter = 2
net.ipv4.conf.default.rp_filter = 2
net.ipv4.conf.wan.rp_filter = 2
EOF
echo "200     vpn" >> /etc/iproute2/rt_tables
touch /etc/openvpn/.vpn_creds
chmod 600 /etc/openvpn/.vpn_creds

# Set some default settings for miniupnpd package:
echo "miniupnpd miniupnpd/start_daemon boolean true" | debconf-set-selections
echo "miniupnpd miniupnpd/ip6script boolean false" | debconf-set-selections
echo "miniupnpd miniupnpd/listen string br0" | debconf-set-selections
echo "miniupnpd miniupnpd/iface string wan" | debconf-set-selections

# Install and configure miniupnp install:
apt install -y -qq miniupnpd miniupnpc
sed -i "s|#secure_mode=|secure_mode=|g" /etc/miniupnpd/miniupnpd.conf
sed -i "s|#http_port=0|http_port=5000|g" /etc/miniupnpd/miniupnpd.conf
sed -i "s|#enable_upnp=no|enable_upnp=yes|g" /etc/miniupnpd/miniupnpd.conf
sed -i "s|#enable_natpmp=yes|enable_natpmp=yes|g" /etc/miniupnpd/miniupnpd.conf
rm /etc/init.d/miniupnpd
rm /etc/miniupnpd/*.sh
systemctl daemon-reload
systemctl enable miniupnpd
systemctl restart miniupnpd

# Install PiHole:
curl -L https://install.pi-hole.net | bash /dev/stdin --unattended
systemctl stop dnsmasq
systemctl disable dnsmasq
systemctl mask dnsmasq
chown pihole:pihole /var/lib/misc
chown pihole:pihole -R /var/lib/misc/*
chown www-data:www-data -R /var/www/html
chown www-data:www-data -R /var/www/html/*
systemctl enable pihole-FTL
systemctl restart pihole-FTL
pihole -a -p bananapi
git clone https://github.com/xptsp/bpi-r2-router-webui /var/www/router

# Install Transmission-BT program:
mv /etc/transmission-daemon/settings.json /tmp/settings.json
apt install -y transmission-daemon
mv /tmp/settings.json /etc/transmission-daemon/settings.json
chown -R vpn:vpn /etc/transmission-daemon/
chown -R vpn:vpn /var/lib/transmission-daemon/
chmod -R 775 /etc/transmission-daemon/
chmod -R 775 /var/lib/transmission-daemon/
mkdir /home/vpn/{Incomplete,Download}
chown -R vpn:vpn /home/vpn/{Incomplete,Download}
chmod -R 775 /home/vpn/{Incomplete,Download}

# Install docker:
curl -L https://get.docker.com | bash
usermod -aG docker pi

# Download docker-compose into the /usr/local/bin directory:
wget https://github.com/tsitle/dockercompose-binary_and_dockerimage-aarch64_armv7l_x86_x64/raw/master/binary/docker-compose-linux-armhf-1.27.4.tgz -O /tmp/docker.tgz
pushd /tmp
tar xvzf /tmp/docker.tgz
mv docker-compose-linux-armhf-1.27.4 /usr/local/bin/
ln -sf /usr/local/bin/docker-compose-linux-armhf-1.27.4 /usr/local/bin/docker-compose
popd
systemctl enable docker-compose

# Install and configure cloudflared:
pushd /tmp
wget https://bin.equinox.io/c/VdrWdbjqyF/cloudflared-stable-linux-arm.tgz
tar -xvzf cloudflared-stable-linux-arm.tgz
mv ./cloudflared /usr/local/bin
popd
useradd -s /usr/sbin/nologin -r -M cloudflared
chown cloudflared:cloudflared /etc/default/cloudflared
chown cloudflared:cloudflared /usr/local/bin/cloudflared
systemctl enable cloudflared@1
systemctl start cloudflared@1
systemctl enable cloudflared@2
systemctl start cloudflared@2
systemctl enable cloudflared@3
systemctl start cloudflared@3

# Set some default settings for minissdpd package:
echo "minissdpd minissdpd/listen string br0" | debconf-set-selections
echo "minissdpd minissdpd/ip6 boolean false" | debconf-set-selections
echo "minissdpd minissdpd/start_daemon boolean true" | debconf-set-selections

# Install minissdpd package:
apt install -y minissdpd
systemctl enable minissdpd
systemctl start minissdpd

# Install igmpproxy package:
mv /etc/igmpproxy.conf /tmp/igmpproxy.conf
apt install -y igmpproxy
mv /tmp/igmpproxy.conf /etc/igmpproxy.conf 
systemctl enable igmpproxy
systemctl start igmpproxy
