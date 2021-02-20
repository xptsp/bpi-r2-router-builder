#!/bin/bash

# If we are not doing this as root, we need to change to root now!
if [[ "${UID}" -ne 0 ]]; then
	sudo $0 $@
	exit $?
fi

# Enable packet forwarding on IPv4:
sed -i "s|#net.ipv4.ip_forward=1|net.ipv4.ip_forward=1|g" /etc/sysctl.conf

# Force automatic reboot after 1 second upon a kernel panic:
echo "kernel.panic = 1" >> /etc/sysctl.conf

# Blacklist the module responsible for poweroffs on R2:
echo "blacklist mtk_pmic_keys" > /etc/modprobe.d/blacklist.conf

# Refreshes the certificates:
update-ca-certificates -f

# Sets timezone to "America/Chicago":
timedatectl set-timezone America/Chicago

# Sets locale to "en_US.UTF-8":
sed -i "s|# en_US.UTF-8 UTF-8|en_US.UTF-8 UTF-8|g" /etc/locale.gen
locale-gen

# Copy files to their destination directories:
pushd /opt/bpi-r2_router
chown root:root -R files
cp -aR files/* /
popd

# Create the hard drive mounting points:
mkdir -p /etc/samba/smb.d/
mkdir -p /mnt/{sda1,sda2,sda3}

# Install repository for PHP 7.x packages:
apt update
apt-get install -y software-properties-common
add-apt-repository -y ppa:ondrej/php
mv /etc/apt/sources.list.d/ondrej-ubuntu-php-hirsute.list /etc/apt/sources.list.d/ondrej-ubuntu-php-bionic.list
sed -i "s|hirsute|bionic|g" /etc/apt/sources.list.d/ondrej-ubuntu-php-bionic.list
apt-key adv --keyserver keyserver.ubuntu.com --recv-keys 4F4EA0AAE5267A6C
apt-get update

# Update the software:
apt dist-upgrade -y

# Install some new stuff:
apt install -y git pciutils usbutils sudo iw wireless-tools net-tools wget curl lsb-release samba avahi-daemon avahi-discover libnss-mdns pmount toilet
systemctl enable avahi-daemon
systemctl enable smbd
systemctl enable nmbd

# Create our custom login:
rm /etc/motd
rm /etc/update-motd.d/10-uname
ln -s /var/run/motd /etc/motd

# Install NGINX and required PHP 7.2 packages:
apt-get install -y nginx php7.2-fpm php7.2-cgi php7.2-xml php7.2-sqlite3 php7.2-intl apache2-utils php7.2-mysql php7.2-sqlite3 sqlite3 php7.2-zip openssl php7.2-curl
systemctl enable php7.2-fpm
systemctl start php7.2-fpm
mv /etc/nginx/sites-enabled/default /etc/nginx/sites-enabled/default.bak
mv /etc/nginx/sites-enabled/organizr /etc/nginx/sites-enabled/default
ln -sf /etc/nginx/sites-available/pihole /etc/nginx/sites-enabled/pihole
systemctl enable nginx
systemctl restart nginx
systemctl start php7.2-fpm

# Download and configure the main interface for the router:
git clone https://github.com/causefx/Organizr /var/www/organizr
chown www-data:www-data -R /var/www/organizr
mkdir -p /var/lib/docker/data/organizr
chown www-data:www-data -R /var/lib/docker/data/organizr
pushd /var/www/organizr
git checkout v1-master
popd
mv /var/www/router.png /var/www/organizr/images/
mv /var/www/config.php /var/www/organizr/config/

# Install PiHole
curl -L https://install.pi-hole.net | bash /dev/stdin --unattended
systemctl stop dnsmasq
systemctl disable dnsmasq
systemctl mask dnsmasq
chown pihole:pihole -R /var/lib/misc
systemctl enable pihole-FTL
systemctl start pihole-FTL

# Download the bpi-r2-ssd1306-display repo:
apt install -y python3-pip libtiff5-dev libjpeg8-dev zlib1g-dev libfreetype6-dev liblcms2-dev libwebp-dev tcl8.6-dev tk8.6-dev python-tk
python3 -m pip install --upgrade pip wheel setuptools
python3 -m pip install Adafruit-SSD1306 Adafruit-BBIO Adafruit-GPIO Adafruit-PureIO Pillow psutil
git clone https://github.com/xptsp/bpi-r2-ssd1306-display /opt/stats
cp /opt/stats/stats.service /etc/systemd/system/stats.service
systemctl enable stats
systemctl start stats

# Install docker and docker-compose:
curl -fsSL https://get.docker.com -o /tmp/get-docker.sh
bash /tmp/get-docker.sh
wget https://github.com/tsitle/dockercompose-binary_and_dockerimage-aarch64_armv7l_x86_x64/raw/master/binary/docker-compose-linux-armhf-1.27.4.tgz -O /tmp/docker.tgz
pushd /tmp
tar xvzf /tmp/docker.tgz
mv docker-compose-linux-armhf-1.27.4 /usr/local/bin/
ln -sf /usr/local/bin/docker-compose-linux-armhf-1.27.4 /usr/local/bin/docker-compose
popd
