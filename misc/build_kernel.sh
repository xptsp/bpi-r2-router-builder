#!/bin/bash

###############################################################################################
# Our custom deb packing function:
###############################################################################################
function custom_deb {
	check_dep "deb"
	if [[ $? -ne 0 ]];then exit 1;fi
	get_version
	ver=${kernver}-${board}${gitbranch}
	uimagename=uImage_${kernver}${gitbranch}
	echo "deb package ${ver}"
	prepare_SD
	boardv=${board:4}
	targetdir=debian/bananapi-$boardv-image

	rm -rf $targetdir/boot/bananapi/$board/linux/*
	rm -rf $targetdir/lib/modules/*
	mkdir -p $targetdir/boot/bananapi/$board/linux/dtb/
	mkdir -p $targetdir/lib/modules/
	mkdir -p $targetdir/DEBIAN/

	if [[ -e ./uImage || -e ./uImage_nodt ]] && [[ -d ../SD/BPI-ROOT/lib/modules/${ver} ]]; then
		if [[ -e ./uImage ]];then
			cp ./uImage $targetdir/boot/bananapi/$board/linux/${uimagename}
		fi
		if [[ -e ./uImage_nodt && -e ./$board.dtb ]];then
			cp ./uImage_nodt $targetdir/boot/bananapi/$board/linux/${uimagename}_nodt
			cp ./$board.dtb $targetdir/boot/bananapi/$board/linux/dtb/$board-${kernver}${gitbranch}.dtb
		fi
		cp -r ../SD/BPI-ROOT/lib/modules/${ver} $targetdir/lib/modules/

		cat > $targetdir/DEBIAN/preinst << EOF
#!/bin/bash
clr_red=\$'\e[1;31m'
clr_green=\$'\e[1;32m'
clr_yellow=\$'\e[1;33m'
clr_blue=\$'\e[1;34m'
clr_reset=\$'\e[0m'
m=\$(mount | grep '/boot[^/]')
if [[ -z "\$m" ]];
then
	echo "\${clr_red}/boot needs to be mountpoint for /dev/mmcblk0p1\${clr_reset}";
	exit 1;
fi
kernelfile=/boot/bananapi/$board/linux/${uimagename}
if [[ -e "\${kernelfile}" ]];then
	echo "\${clr_red}\${kernelfile} already exists\${clr_reset}"
	echo "\${clr_red}please remove/rename it or uninstall previous installed kernel-package\${clr_reset}"
	exit 2;
fi
EOF
		chmod +x $targetdir/DEBIAN/preinst
		cat > $targetdir/DEBIAN/postinst << EOF
#!/bin/bash
clr_red=\$'\e[1;31m'
clr_green=\$'\e[1;32m'
clr_yellow=\$'\e[1;33m'
clr_blue=\$'\e[1;34m'
clr_reset=\$'\e[0m'
case "\$1" in
	configure)
	#install|upgrade)
		echo "kernel=${uimagename}">>/boot/bananapi/$board/linux/uEnv.txt

		#check for non-dsa-kernel (4.4.x)
		kernver=\$(uname -r)
		if [[ "\${kernver:0:3}" == "4.4" ]];
		then
			echo "\${clr_yellow}you are upgrading from kernel 4.4.\${clr_reset}";
			echo "\${clr_yellow}Please make sure your network-config (/etc/network/interfaces) matches dsa-driver\${clr_reset}";
			echo "\${clr_yellow}(bring cpu-ports ethx up, ip-configuration to wan/lanx)\${clr_reset}";
		fi
	;;
	*) echo "unhandled \$1 in postinst-script"
esac
EOF
		chmod +x $targetdir/DEBIAN/postinst

		cat > $targetdir/DEBIAN/postrm << EOF
#!/bin/bash
case "\$1" in
	abort-install)
		echo "installation aborted"
	;;
	remove|purge)
		if [[ -e /boot/bananapi/$board/linux/uEnv.txt ]];then
			cp /boot/bananapi/$board/linux/uEnv.txt /boot/bananapi/$board/linux/uEnv.txt.bak
			grep -v  ${uimagename} /boot/bananapi/$board/linux/uEnv.txt.bak > /boot/bananapi/$board/linux/uEnv.txt
		else
			cp /boot/uEnv.txt /boot/uEnv.txt.bak
			grep -v  ${uimagename} /boot/uEnv.txt.bak > /boot/uEnv.txt
		fi
	;;
esac
EOF
		chmod +x $targetdir/DEBIAN/postrm
		if [[ "$board" == "bpi-r64" ]];then
			debarch=arm64
		else
			debarch=armhf
		fi

		cat > $targetdir/DEBIAN/control << EOF
Package: bananapi-$boardv-image-${kernbranch}
Version: ${kernver}-1
Section: custom
Priority: optional
Architecture: $debarch
Multi-Arch: no
Essential: no
Maintainer: Frank Wunderlich
Description: ${BOARD^^} linux image ${ver}
EOF
		cd debian
		fakeroot dpkg-deb --build bananapi-$boardv-image ../debian
		cd ..
		ls -lh debian/*.deb
		debfile=debian/bananapi-$boardv-image-${kernbranch,,}_${kernver}-1_$debarch.deb
		dpkg -c $debfile

		dpkg -I $debfile
	else
		echo "First build kernel ${ver}"
		echo "eg: ./build"
	fi
}

###############################################################################################
# Update the kernel source, compile it, then pack as if we will be using the archive:
###############################################################################################
cd ~/R2/BPI-R2-4.14
BIN=/tmp/kernel_build.sh
cp build.sh ${BIN}
sed -i "s|			installchoice|			custom_deb|g" ${BIN}
source ${BIN}
