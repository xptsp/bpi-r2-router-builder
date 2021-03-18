#!/bin/bash
cd files
cp -R boot/* /boot/
for file in $(find etc/* -type f); do rm /$file; ln -sf $PWD/$file /$file; done
for file in $(find lib/systemd/system/* -type f); do rm /$file; ln -sf $PWD/$file /$file; done
for file in $(find root/.b* -type f); do
	rm /$file
	ln -sf $PWD/$file /$file
	rm /etc/skel/${file/root/}
	ln -sf $PWD/$file /etc/skel/${file/root/}
	rm /home/pi/${file/root/}
	ln -sf $PWD/$file /home/pi/${file/root/}
	rm /home/vpn/${file/root/}
	ln -sf $PWD/$file /home/vpn/${file/root/}
done
for file in $(find sbin/* -type f); do  rm /$file; ln -sf $PWD/$file /$file; done
mkdir -p /usr/local/bin/helpers
for file in $(find usr/* -type f); do  rm /$file; ln -sf $PWD/$file /$file; done
