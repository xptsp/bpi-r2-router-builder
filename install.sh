#!/bin/bash

# If we are not doing this as root, we need to change to root now!
if [[ "${UID}" -ne 0 ]]; then
	sudo $0 $@
	exit $?
fi

##################################################################################
# Generic configuration
##################################################################################
# Secure the "authorized_keys" file so it can only be appended:
chattr +a /root/.ssh/authorized_keys

# Add additional configuration for split-tunnel VPN:
echo "200     vpn" >> /etc/iproute2/rt_tables
touch /etc/openvpn/.vpn_creds
chmod 600 /etc/openvpn/.vpn_creds

# Set some things before we start:
export DEBIAN_FRONTEND=noninteractive
update-alternatives --set iptables /usr/sbin/iptables-legacy

# Set a placeholder file for chrooting into read-only filesystem:
touch /etc/debian_chroot

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

# Refreshes the certificates:
update-ca-certificates -f

# Sets timezone to "America/Chicago":
rm /etc/localtime
ln -s /usr/share/zoneinfo/America/Chicago /etc/localtime

# Sets locale to "en_US.UTF-8":
sed -i "s|# en_US.UTF-8 UTF-8|en_US.UTF-8 UTF-8|g" /etc/locale.gen
locale-gen

# Place a list of english words in "/usr/share/dict/" for our password generator:
wget https://github.com/dobsondev/php-password-generator/raw/master/php-password-generator/adjectives.list -O /usr/share/dict/adjectives.list
wget https://github.com/dobsondev/php-password-generator/raw/master/php-password-generator/animals.list -O /usr/share/dict/animals.list

# Modify networking service configuration to exclude "eth0" adapter.  Service file
# changes take care of this without breaking the service when restarting it.
sed -i "s|#EXCLUDE_INTERFACES=.*|#EXCLUDE_INTERFACES=eth0|g" /etc/default/networking

##################################################################################
# Install any packages that need updating:
##################################################################################
apt update
apt dist-upgrade -y

##################################################################################
# Install a few packages so we can create our custom login message
##################################################################################
apt install -y toilet pmount eject
rm /etc/motd
rm /etc/update-motd.d/10-uname
ln -s /var/run/motd /etc/motd

##################################################################################
# Disable and stop hostapd service before we go further
##################################################################################
systemctl disable hostapd
systemctl stop hostapd

##################################################################################
# Install some new utilities
##################################################################################
apt install -y pciutils usbutils sudo iw wireless-tools net-tools wget curl lsb-release unzip debconf-utils tree rng-tools mosquitto-client
apt install -y vlan ipset traceroute nmap conntrack ndisc6 whois iperf3 tcpdump ethtool irqbalance screen parted wpasupplicant device-tree-compiler
echo 'HRNGDEVICE=/dev/urandom' >> /etc/default/rng-tools

##################################################################################
# Install GIT and avahi utilities
##################################################################################
apt install -y git avahi-daemon libnss-mdns vnstat 
systemctl enable avahi-daemon
systemctl start avahi-daemon
# NOTE: Disable and remove data for "vnstat":
systemctl stop vnstat
systemctl disable vnstat
rm /var/lib/vnstat/*

##################################################################################
# Install Samba
##################################################################################
echo "samba-common samba-common/dhcp boolean true" | debconf-set-selections
apt install -y samba
echo -e "bananapi\nbananapi" | smbpasswd -a pi
# NOTE: Modify the Samba configuration to make sharing USB sticks more automatic
sed -i "1s|^|include = /etc/samba/includes.conf\n\n|" /etc/samba/smb.conf
touch /etc/samba/includes.conf
sed -i "s|/var/run|/run|g" /lib/systemd/system/?mbd.service
systemctl daemon-reload
systemctl enable smbd
systemctl restart smbd
systemctl enable nmbd
systemctl restart nmbd

##################################################################################
# Install NGINX and PHP 7.3
##################################################################################
apt install -y nginx php7.3-fpm php7.3-cgi php7.3-xml php7.3-sqlite3 php7.3-intl apache2-utils php7.3-mysql php7.3-sqlite3 sqlite3 php7.3-zip openssl php7.3-curl
sed -i "s|display_errors = .*|display_errors = On|g" /etc/php/7.3/fpm/php.ini
systemctl enable php7.3-fpm
systemctl start php7.3-fpm
rm /etc/nginx/sites-enabled/default
ln -sf /etc/nginx/sites-available/router /etc/nginx/sites-enabled/default
ln -sf /etc/nginx/sites-available/pihole /etc/nginx/sites-enabled/pihole
systemctl enable nginx
systemctl restart nginx
usermod -aG systemd-journal www-data

##################################################################################
# Install TrueCrypt and HD-Idle
##################################################################################
wget https://github.com/stefansundin/truecrypt.deb/releases/download/7.1a-15/truecrypt-cli_7.1a-15_armhf.deb -O /tmp/truecrypt.deb
wget https://github.com/adelolmo/hd-idle/releases/download/v1.12/hd-idle_1.12_armhf.deb -O /tmp/hdidle.deb
mv /etc/default/hd-idle /tmp/
apt install -y /tmp/*.deb
mv /tmp/hd-idle /etc/default/hd-idle
rm /tmp/*.deb
systemctl daemon-reload
systemctl enable hd-idle
systemctl restart hd-idle

##################################################################################
# Pull ydns's bash-updater repo and modify to pull settings from elsewhere
##################################################################################
git clone https://github.com/ydns/bash-updater /opt/ydns-updater
sed -i "s|^YDNS_LASTIP_FILE|[[ -f /etc/default/ydns-updater ]] \&\& source /etc/default/ydns-updater\nYDNS_LASTIP_FILE|" /opt/ydns-updater/updater.sh
chown www-data:www-data /etc/default/ydns-updater

##################################################################################
# Install and configure miniupnp install
##################################################################################
# NOTE: Install the miniupnp install quietly
echo "miniupnpd miniupnpd/start_daemon boolean true" | debconf-set-selection7s
echo "miniupnpd miniupnpd/ip6script boolean false" | debconf-set-selections
echo "miniupnpd miniupnpd/listen string br0" | debconf-set-selections
echo "miniupnpd miniupnpd/iface string wan" | debconf-set-selections
apt install -y miniupnpd miniupnpc
# NOTE: Configure the service:
sed -i "s|#secure_mode=|secure_mode=|g" /etc/miniupnpd/miniupnpd.conf
sed -i "s|#http_port=0|http_port=5000|g" /etc/miniupnpd/miniupnpd.conf
sed -i "s|#enable_upnp=no|enable_upnp=yes|g" /etc/miniupnpd/miniupnpd.conf
sed -i "s|#enable_natpmp=yes|enable_natpmp=yes|g" /etc/miniupnpd/miniupnpd.conf
rm /etc/init.d/miniupnpd
rm /etc/miniupnpd/*.sh
systemctl daemon-reload
systemctl enable miniupnpd
systemctl restart miniupnpd

##################################################################################
# Install minissdpd package
##################################################################################
# NOTE: Set some default settings for minissdpd package:
echo "minissdpd minissdpd/listen string br0" | debconf-set-selections
echo "minissdpd minissdpd/ip6 boolean false" | debconf-set-selections
echo "minissdpd minissdpd/start_daemon boolean true" | debconf-set-selections
# NOTE: Install minissdpd package:
apt install -y minissdpd
systemctl enable minissdpd
systemctl start minissdpd

##################################################################################
# Install Transmission-BT program
##################################################################################
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
systemctl restart transmission-daemon

##################################################################################
# Install docker and add bin directory on docker partition to system path
##################################################################################
curl -L https://get.docker.com | bash
usermod -aG docker pi
sed -i "s|PATH=\"|PATH=\"/var/lib/docker/bin:|g" /etc/profile

##################################################################################
# Download docker-compose into the /usr/local/bin directory
##################################################################################
wget https://github.com/tsitle/dockercompose-binary_and_dockerimage-aarch64_armv7l_x86_x64/raw/master/binary/docker-compose-linux-armhf-1.27.4.tgz -O /tmp/docker.tgz
pushd /tmp
tar xvzf /tmp/docker.tgz
mv docker-compose-linux-armhf-1.27.4 /usr/local/bin/
ln -sf /usr/local/bin/docker-compose-linux-armhf-1.27.4 /usr/local/bin/docker-compose
popd

##################################################################################
# Install cloudflared
##################################################################################
pushd /tmp
wget https://bin.equinox.io/c/VdrWdbjqyF/cloudflared-stable-linux-arm.tgz
tar -xvzf cloudflared-stable-linux-arm.tgz
mv ./cloudflared /usr/local/bin
popd
useradd -s /usr/sbin/nologin -r -M cloudflared
chown cloudflared:cloudflared /usr/local/bin/cloudflared
systemctl enable cloudflared@1
systemctl start cloudflared@1
systemctl enable cloudflared@2
systemctl start cloudflared@2
systemctl enable cloudflared@3
systemctl start cloudflared@3

##################################################################################
# Install the wireless regulatory table
##################################################################################
apt install -y wireless-regdb crda
git clone https://kernel.googlesource.com/pub/scm/linux/kernel/git/sforshee/wireless-regdb /opt/wireless-regdb
ln -sf /opt/wireless-regdb/regulatory.db /lib/firmware/
ln -sf /opt/wireless-regdb/regulatory.db.p7s /lib/firmware/

##################################################################################
# Install PiHole
##################################################################################
curl -L https://install.pi-hole.net | bash /dev/stdin --unattended
# NOTE: Mask "dhcpcd" package so we don't conflict with it!
systemctl stop dhcpcd
systemctl disable dhcpcd
systemctl mask dhcpcd
# NOTE: Mask "dnsmasq" package so we don't conflict with it!
systemctl stop dnsmasq
systemctl disable dnsmasq
systemctl mask dnsmasq
# NOTE: Configure some things correctly
chown pihole:pihole /var/lib/misc
chown pihole:pihole -R /var/lib/misc/*
chown www-data:www-data -R /var/www/html
chown www-data:www-data -R /var/www/html/*
rm /var/www/html/index.nginx-debian.html
# NOTE: Set default password as "bananapi"
pihole -a -p bananapi
# NOTE: Set default DNS to cloudflare port 5051
sed -i "/PIHOLE_DNS_.*/d" /etc/pihole/setupVars.conf
echo "PIHOLE_DNS_1=127.0.0.1#5051" >> /etc/pihole/setupVars.conf
pihole restartdns

##################################################################################
# Install I2C libraries and Python script to run OLED display
##################################################################################
# NOTE: Installing support packages and libraries
apt install -y --no-install-recommends i2c-tools python3-pip python3-pil python-psutil
python3 -m pip install --upgrade pip wheel setuptools
pushd /tmp
wget https://github.com/frank-w/bpi-r2-ssd1306-display/raw/master/ssd1306_python3.tar.gz
tar xzvf ssd1306_python3.tar.gz
python3 -m pip install --no-index --find-links=/tmp/whl psutil Adafruit-SSD1306 Adafruit-BBIO
popd
# NOTE: Install and enable Python3 stats script
git clone https://github.com/xptsp/bpi-r2-ssd1306-display /opt/stats
ln -sf /opt/stats/stats.service /etc/systemd/system/stats.service
systemctl enable stats
systemctl start stats
