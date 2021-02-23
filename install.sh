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
chown root:root -R /opt/bpi-r2_router/files
cp -aR /opt/bpi-r2_router/files/* /
cp /root/.bash* /etc/skel/

# Create the hard drive mounting points:
mkdir -p /mnt/{sda1,sda2,sda3}

# Install any packages that need updating:
apt update
apt dist-upgrade -y

# Install some new stuff:
apt install -y git pciutils usbutils sudo iw wireless-tools net-tools wget curl lsb-release avahi-daemon avahi-discover libnss-mdns miniupnpd miniupnpc
systemctl enable avahi-daemon
systemctl enable smbd
systemctl enable nmbd

# Modify the Samba configuration to make sharing USB sticks more automatic:
apt install -y samba pmount
sed -i "1s|^|include = /etc/samba/includes.conf\n\n|" /etc/samba/smb.conf
touch /etc/samba/includes.conf
systemctl restart smbd
echo -e "bananapi\nbananapi" | smbpasswd -a pi

# Create our custom login message:
apt install -y toilet
rm /etc/motd
rm /etc/update-motd.d/10-uname
ln -s /var/run/motd /etc/motd

# Update the software:
apt update
apt dist-upgrade -y

# Install repository for PHP 7.x packages:
apt-get install -y software-properties-common
add-apt-repository -y ppa:ondrej/php
mv /etc/apt/sources.list.d/ondrej-ubuntu-php-hirsute.list /etc/apt/sources.list.d/ondrej-ubuntu-php-bionic.list
sed -i "s|hirsute|bionic|g" /etc/apt/sources.list.d/ondrej-ubuntu-php-bionic.list
apt-key adv --keyserver keyserver.ubuntu.com --recv-keys 4F4EA0AAE5267A6C

# Install NGINX and required PHP 7.2 packages:
apt update
apt-get install -y nginx php7.2-fpm php7.2-cgi php7.2-xml php7.2-sqlite3 php7.2-intl apache2-utils php7.2-mysql php7.2-sqlite3 sqlite3 php7.2-zip openssl php7.2-curl
systemctl enable php7.2-fpm
systemctl start php7.2-fpm
mv /etc/nginx/sites-enabled/default /etc/nginx/sites-enabled/default.bak
mv /etc/nginx/sites-enabled/organizr /etc/nginx/sites-enabled/default
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

# Install docker and docker-compose:
curl -fsSL https://get.docker.com -o /tmp/get-docker.sh
bash /tmp/get-docker.sh
wget https://github.com/tsitle/dockercompose-binary_and_dockerimage-aarch64_armv7l_x86_x64/raw/master/binary/docker-compose-linux-armhf-1.27.4.tgz -O /tmp/docker.tgz
pushd /tmp
tar xvzf /tmp/docker.tgz
mv docker-compose-linux-armhf-1.27.4 /usr/local/bin/
ln -sf /usr/local/bin/docker-compose-linux-armhf-1.27.4 /usr/local/bin/docker-compose
popd

# Create a user named "pi", being a member of the "docker", "sudo" and "users" group.
useradd -m -G docker,sudo,users pi
echo -e "bananapi\nbananapi" | passwd -q pi
cp /root/.bash* ~pi/
chown pi:pi -R ~pi/.bash*
chsh pi -s /bin/bash

# Install TrueCrypt and HD-Idle:
wget https://github.com/stefansundin/truecrypt.deb/releases/download/7.1a-15/truecrypt-cli_7.1a-15_armhf.deb -O /tmp/truecrypt.deb
wget https://github.com/adelolmo/hd-idle/releases/download/v1.12/hd-idle_1.12_armhf.deb -O /tmp/hdidle.deb
apt install -y /tmp/*.deb
rm /tmp/*.deb
