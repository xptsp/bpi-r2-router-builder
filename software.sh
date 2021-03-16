	#!/bin/bash

# If we are not doing this as root, we need to change to root now!
if [[ "${UID}" -ne 0 ]]; then
	sudo $0 $@
	exit $?
fi

# Set some things before we start:
export DEBIAN_FRONTEND=noninteractive
update-alternatives --set iptables /usr/sbin/iptables-legacy

# Create a user named "pi", being a member of the "sudo" and "users" group.
useradd -m -G sudo,users -s /bin/bash pi
echo -e "bananapi\nbananapi" | passwd -q pi

# Create a user name "vpn", being a member of the "users" and "pi" group:
useradd -m -G users -s /usr/sbin/nologin vpn
usermod -aG vpn pi

# "Fix" poweroff kernel panic:
mv /sbin/poweroff{,.bak}
mv /sbin/poweroff.bash poweroff

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

# Copy files to their destination directories:
chown root:root -R files
cp -aR files/* /
cp /root/.bash* /etc/skel/
systemctl daemon-reload
chown pi:pi -R /var/lib/docker/data/

# Activate the iptables rules so that we have internet access during installation:
/etc/network/if-pre-up.d/iptables

# Create the hard drive mounting points:
mkdir -p /mnt/{sda1,sda2,sda3}

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
apt install -y vlan ipset traceroute nmap conntrack ndisc6 whois mtr iperf3 tcpdump ethtool irqbalance tree eject rng-tools
echo 'HRNGDEVICE=/dev/urandom' >> /etc/default/rng-tools
systemctl enable avahi-daemon
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
systemctl enable smbd
systemctl enable nmbd
systemctl restart smbd
echo -e "bananapi\nbananapi" | smbpasswd -a pi

# Temporarily install ondrej's php repo in order to install NGINX and required PHP 7.2 packages:
apt-get install -y software-properties-common
add-apt-repository -y ppa:ondrej/php
sed -i "s|hirsute|bionic|g" /etc/apt/sources.list.d/ondrej-ubuntu-php-hirsute.list
apt-key adv --keyserver keyserver.ubuntu.com --recv-keys 4F4EA0AAE5267A6C
apt update
apt-get install -y nginx php7.2-fpm php7.2-cgi php7.2-xml php7.2-sqlite3 php7.2-intl apache2-utils php7.2-mysql php7.2-sqlite3 sqlite3 php7.2-zip openssl php7.2-curl
systemctl enable php7.2-fpm
systemctl start php7.2-fpm
mv /etc/nginx/sites-available/default /etc/nginx/sites-available/default.bak
mv /etc/nginx/sites-available/organizr /etc/nginx/sites-available/default
systemctl enable nginx
systemctl restart nginx
systemctl start php7.2-fpm
mkdir /etc/apt/sources.disabled.d
mv /etc/apt/sources.list.d/ondrej-ubuntu-php-hirsute.list /etc/apt/sources.disabled.d/ondrej-ubuntu-php-hirsute.list
apt update

# Install the custom router UI:
git clone https://github.com/xptsp/bpi-r2-router-webui /var/www/router
chown www-data:www-data -R /var/www/router

# Install and configure cloudflared:
pushd /tmp
wget https://bin.equinox.io/c/VdrWdbjqyF/cloudflared-stable-linux-arm.tgz
tar -xvzf cloudflared-stable-linux-arm.tgz
mv ./cloudflared /usr/local/bin
popd
chmod +x /usr/local/bin/cloudflared
useradd -s /usr/sbin/nologin -r -M cloudflared
sudo chown cloudflared:cloudflared /etc/default/cloudflared
sudo chown cloudflared:cloudflared /usr/local/bin/cloudflared
sudo systemctl enable cloudflared@1
sudo systemctl start cloudflared@1
sudo systemctl enable cloudflared@2
sudo systemctl start cloudflared@2
sudo systemctl enable cloudflared@3
sudo systemctl start cloudflared@3

# Install PiHole
curl -L https://install.pi-hole.net | bash /dev/stdin --unattended
systemctl stop dnsmasq
systemctl disable dnsmasq
systemctl mask dnsmasq
chown pihole:pihole -R /var/lib/misc
chown www-data:www-data -R /var/www/html
systemctl enable pihole-FTL
systemctl start pihole-FTL
pihole -a -p bananapi
ln -sf /etc/nginx/sites-available/pihole /etc/nginx/sites-enabled/pihole

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
ln -sf /var/lib/docker/data /opt/docker-data

# Install TrueCrypt and HD-Idle:
wget https://github.com/stefansundin/truecrypt.deb/releases/download/7.1a-15/truecrypt-cli_7.1a-15_armhf.deb -O /tmp/truecrypt.deb
wget https://github.com/adelolmo/hd-idle/releases/download/v1.12/hd-idle_1.12_armhf.deb -O /tmp/hdidle.deb
apt install -y /tmp/*.deb
rm /tmp/*.deb

# Pull ydns's bash-updater repo and modify to pull settings from elsewhere:
git clone https://github.com/ydns/bash-updater /opt/ydns-updater
sed -i "s|^YDNS_LASTIP_FILE|[[ -f /etc/default/ydns-updater ]] \&\& source /etc/default/ydns-updater\nYDNS_LASTIP_FILE|" /opt/ydns-updater/updater.sh
chown www-data:www-data /etc/default/ydns-updater

# Temporarily add Raspberry Pi repository so we can install packages required by marklister/overlayRoot repo:
echo "deb http://archive.raspberrypi.org/debian/ stretch main ui" > /etc/apt/sources.list.d/raspi.list
apt-key adv --keyserver keyserver.ubuntu.com --recv-keys 82B129927FA3303E
apt update
git clone https://github.com/marklister/overlayRoot /opt/overlayRoot/
pushd /opt/overlayRoot
sed -i "/cmdline.txt/d" install
./install
popd
mv /etc/apt/sources.list.d/raspi.list /etc/apt/sources.disabled.d/raspi.list
apt update

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

# Install Transmission-BT program:
mv /etc/transmission-daemon/settings.json /tmp/settings.json
apt-get install transmission-daemon -y
mv /tmp/settings.json /etc/transmission-daemon/settings.json
chown -R vpn:vpn /etc/transmission-daemon/
chown -R vpn:vpn /var/lib/transmission-daemon/
chmod -R 775 /etc/transmission-daemon/
chmod -R 775 /var/lib/transmission-daemon/
mkdir /home/vpn/{Incomplete,Download}
chown -R vpn:vpn /home/vpn/{Incomplete,Download}
chmod -R 775 /home/vpn/{Incomplete,Download}
usermod -aG vpn pi

# Set some default settings for miniupnpd package:
echo "miniupnpd miniupnpd/start_daemon boolean true" | debconf-set-selections
echo "miniupnpd miniupnpd/ip6script boolean false" | debconf-set-selections
echo "miniupnpd miniupnpd/listen string br0" | debconf-set-selections
echo "miniupnpd miniupnpd/iface string wan" | debconf-set-selections

# Install and configure miniupnp install:
apt install -y -q miniupnpd
sed -i "s|#secure_mode=|secure_mode=|g" /etc/miniupnpd/miniupnpd.conf
sed -i "s|#http_port=0|http_port=5000|g" /etc/miniupnpd/miniupnpd.conf
sed -i "s|#enable_upnp=no|enable_upnp=yes|g" /etc/miniupnpd/miniupnpd.conf
sed -i "s|#enable_natpmp=yes|enable_natpmp=yes|g" /etc/miniupnpd/miniupnpd.conf
rm /etc/init.d/miniupnpd
rm /etc/miniupnpd/*.sh
systemctl daemon-reload
systemctl enable miniupnpd
systemctl restart miniupnpd

# Set some default settings for minissdpd package:
echo "minissdpd minissdpd/listen string br0" | debconf-set-selections
echo "minissdpd minissdpd/ip6 boolean false" | debconf-set-selections
echo "minissdpd minissdpd/start_daemon boolean true" | debconf-set-selections

# Install minissdpd, igmpproxy and miniupnpc packages:
apt install -y minissdpd igmpproxy miniupnpc
systemctl enable minissdpd
systemctl start minissdpd
systemctl enable igmpproxy
systemctl start igmpproxy
