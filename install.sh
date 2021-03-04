#!/bin/bash

# If we are not doing this as root, we need to change to root now!
if [[ "${UID}" -ne 0 ]]; then
	sudo $0 $@
	exit $?
fi

# Create a user named "pi", being a member of the "docker", "sudo" and "users" group.
useradd -m -G docker,sudo,users -s /bin/bash pi
echo -e "bananapi\nbananapi" | passwd -q pi

# Create a user name "vpn", being a member of the "pi" group:
useradd -m -G users -s /usr/sbin/nologin vpn
usermod -aG vpn pi

# Create symlink to use "clear" as "cls":
ln -sf /usr/bin/clear /usr/local/bin/cls

# Set hostname:
echo "bpi-r2" > /etc/hostname

# Set IP address of both hostname and pi.hole
echo "192.168.2.1     bpi-r2" >> /etc/hosts
echo "192.168.2.1     pi.hole" >> /etc/hosts

# Remove known duplicate files:
[[ -e /etc/apt/trusted.gpg~ ]] && rm /etc/apt/trusted.gpg~
[[ -e /etc/network/interfaces~ ]] && rm /etc/network/interfaces~

# Enable packet forwarding on IPv4:
sed -i "s|#net.ipv4.ip_forward=1|net.ipv4.ip_forward=1|g" /etc/sysctl.conf

# Force automatic reboot after 1 second upon a kernel panic:
echo "kernel.panic = 1" >> /etc/sysctl.conf

# Activate these changes:
sysctl -p

# Activate the iptables rules so that we have internet access during installation:
/etc/network/if-pre-up.d/iptables

# Blacklist the module responsible for poweroffs on R2:
echo "blacklist mtk_pmic_keys" > /etc/modprobe.d/blacklist.conf

# Load the i2c-dev module at boot:
echo "i2c-dev" > /etc/modprobe.d/i2c.conf

# Refreshes the certificates:
update-ca-certificates -f

# Sets timezone to "America/Chicago":
timedatectl set-timezone America/Chicago

# Sets locale to "en_US.UTF-8":
sed -i "s|# en_US.UTF-8 UTF-8|en_US.UTF-8 UTF-8|g" /etc/locale.gen
locale-gen

# Copy files to their destination directories:
chown root:root -R files
cp -aR files/* /
cp /root/.bash* /etc/skel/
systemctl daemon-reload
chown pi:pi -R /var/lib/docker/data/

# Create the hard drive mounting points:
mkdir -p /mnt/{sda1,sda2,sda3}

# Install any packages that need updating:
apt update
apt dist-upgrade -y

# Install some new stuff:
apt install -y git pciutils usbutils sudo iw wireless-tools net-tools wget curl lsb-release avahi-daemon avahi-discover libnss-mdns unzip vnstat
systemctl enable avahi-daemon
systemctl enable smbd
systemctl enable nmbd
systemctl disable vnstat
rm /var/lib/vnstat/*

# Modify the Samba configuration to make sharing USB sticks more automatic:
apt install -y samba pmount
sed -i "1s|^|include = /etc/samba/includes.conf\n\n|" /etc/samba/smb.conf
touch /etc/samba/includes.conf
systemctl enable smbd
systemctl enable nmbd
systemctl restart smbd
echo -e "bananapi\nbananapi" | smbpasswd -a pi

# Create our custom login message:
apt install -y toilet
rm /etc/motd
rm /etc/update-motd.d/10-uname
ln -s /var/run/motd /etc/motd

# Install repository for PHP 7.x packages:
apt-get install -y software-properties-common
add-apt-repository -y ppa:ondrej/php
sed -i "s|hirsute|bionic|g" /etc/apt/sources.list.d/ondrej-ubuntu-php-hirsute.list
apt-key adv --keyserver keyserver.ubuntu.com --recv-keys 4F4EA0AAE5267A6C

# Install NGINX and required PHP 7.2 packages:
apt update
apt-get install -y nginx php7.2-fpm php7.2-cgi php7.2-xml php7.2-sqlite3 php7.2-intl apache2-utils php7.2-mysql php7.2-sqlite3 sqlite3 php7.2-zip openssl php7.2-curl
systemctl enable php7.2-fpm
systemctl start php7.2-fpm
mv /etc/nginx/sites-available/default /etc/nginx/sites-available/default.bak
mv /etc/nginx/sites-available/organizr /etc/nginx/sites-available/default
systemctl enable nginx
systemctl restart nginx
systemctl start php7.2-fpm

# Download and configure Organizr for the router:
git clone https://github.com/causefx/Organizr /var/www/organizr
pushd /var/www/organizr
git checkout v1-master
popd
chown www-data:www-data -R /var/www/organizr
mv /var/www/config.php /var/www/organizr/config/
mkdir -p /var/lib/docker/data/organizr
chown www-data:www-data -R /var/lib/docker/data/organizr

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
sudo systemctl enable cloudflared
sudo systemctl start cloudflared

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
rm /etc/apt/sources.list.d/raspi.list
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
apt-get install transmission-daemon -y
chown -R vpn:vpn /etc/transmission-daemon/
chown -R vpn:vpn /var/lib/transmission-daemon/
chmod -R 775 /etc/transmission-daemon/
chmod -R 775 /var/lib/transmission-daemon/
mkdir /home/vpn/{Incomplete,Download}
chown -R vpn:vpn /home/vpn/{Incomplete,Download}
chmod -R 775 /home/vpn/{Incomplete,Download}
usermod -aG vpn pi

# Install some other router tools:
apt install -y vlan ipset traceroute nmap conntrack ndisc6 whois mtr iperf3 tcpdump ethtool irqbalance igmpproxy

# Install miniupnpd, then cleanup messy install:
pushd /tmp/
apt download miniupnpd
cp /etc/miniupnpd/miniupnpd.conf /tmp/
apt install -y ./miniupnpd*.deb
rm /etc/default/miniupnpd
rm /etc/init.d/miniupnpd
rm /etc/miniupnpd/*
cp /tmp/miniupnpd.conf /etc/miniupnpd/miniupnpd.conf
dpkg -x $(ls miniupnpd*.deb) /tmp/miniupnpd
rm miniupnpd*.deb
cp /tmp/miniupnpd/usr/sbin/miniupnpd /usr/sbin
popd
systemctl enable miniupnpd
systemctl start miniupnpd
