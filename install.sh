#!/bin/bash

# If we are not doing this as root, we need to change to root now!
if [[ "${UID}" -ne 0 ]]; then
	sudo $0 $@
	exit $?
fi

export DEBIAN_FRONTEND=noninteractive

# Create a user named "pi", being a member of the "docker", "sudo" and "users" group.
useradd -m -G sudo,users -s /bin/bash pi
echo -e "bananapi\nbananapi" | passwd -q pi

# Create a user name "vpn", being a member of the "pi" group:
useradd -m -G users -s /usr/sbin/nologin vpn
usermod -aG vpn pi

# "Fix" poweroff kernel panic:
mv /sbin/poweroff{,.bak}
mv /sbin/poweroff.bash poweroff

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

# Activate the iptables rules so that we have internet access during installation:
/etc/network/if-pre-up.d/iptables

# Create the hard drive mounting points:
mkdir -p /mnt/{sda1,sda2,sda3}

# Install any packages that need updating:
apt update
apt dist-upgrade -y

# Create our custom login message:
apt install -y toilet
rm /etc/motd
rm /etc/update-motd.d/10-uname
ln -s /var/run/motd /etc/motd
