#!/bin/bash

#################################################################################
# Internal functions used in this script:
#################################################################################
git_version() {
	cd ${BASE}/$1 2> /dev/null || return
	echo $(git log | grep build: | grep -m 1 -o "[0-9]*\.[0-9]*\.[0-9]*")-${2:-"1"}~git$(git log | grep -m 1 -e "^commit " | awk '{print $2}' | cut -c1-7)
}
deb_version() {
	apt list $1 -a 2> /dev/null | grep -v local | grep $1 | awk '{print $2}'	
}

#################################################################################
# Install the packages we need to compile the software:
#################################################################################
apt-get install -y --no-install-recommends git make gcc dh-autoreconf bison flex asciidoc pkg-config docbook-xsl xsltproc libxml2-utils python3-distutils

#================================================================================
# Create the directories and gather the information we need:
BASE=/root/build
test -d ${BASE} && rm -rf ${BASE}
mkdir -p ${BASE}
  
#################################################################################
# Compile latest version of "libmnl" from the netfilter project:
#################################################################################
cd ${BASE}
git clone https://git.netfilter.org/libmnl
cd libmnl
mkdir -p {install,modded}
./autogen.sh
./configure --host=arm-linux-gnueabihf --prefix=$PWD/install
make
make install

#================================================================================
# Get version and build numbers for these packages:
LIBMNL_BUILD=1
OLD_LIBMNL_VER=$(deb_version libmnl0)
NEW_LIBMNL_VER=$(git_version libmnl ${LIBMNL_BUILD})

#================================================================================
# Modify existing deb package for "libmnl0" with our compiled files:
cd ${BASE}/libmnl/modded
DIR=libmnl0_${NEW_LIBMNL_VER}_armhf
apt download libmnl0=${OLD_LIBMNL_VER}
dpkg-deb -R libmnl0_${OLD_LIBMNL_VER}_armhf.deb ${DIR}
rm libmnl0_${OLD_LIBMNL_VER}_armhf.deb
cd ${BASE}/libmnl/modded/${DIR}
rm -rf usr/share/doc/libmnl0/*
cp ${BASE}/libmnl/{README,COPYING} usr/share/doc/libmnl0/
cp -a ${BASE}/libmnl/install/lib/libmnl.*.0 usr/lib/arm-linux-gnueabihf/
sed -i "s|^Version: .*|Version: ${NEW_LIBMNL_VER}|" DEBIAN/control
sed -i "s|^Installed-Size: .*|Installed-Size: $(du -s usr | awk '{print $2}')|" DEBIAN/control
find usr -type f -exec md5sum {} \; > DEBIAN/md5sums
rm DEBIAN/{shlibs,symbols,triggers}
cd ..
dpkg-deb --build --root-owner-group ${DIR}
apt install -y ./${DIR}.deb
mv ${DIR}.deb ${BASE}

#================================================================================
# Modify existing deb package for "libmnl-dev" with our compiled files:
cd ${BASE}/libmnl/modded
DIR=libmnl-dev_${NEW_LIBMNL_VER}_armhf
apt download libmnl-dev=${OLD_LIBMNL_VER}
dpkg-deb -R libmnl-dev_${OLD_LIBMNL_VER}_armhf.deb ${DIR}
rm libmnl-dev_${OLD_LIBMNL_VER}_armhf.deb
cd ${BASE}/libmnl/modded/${DIR}
rm -rf usr/share/doc/libmnl-dev/*
cp ${BASE}/libmnl/{README,COPYING} usr/share/doc/libmnl-dev/
cp ${BASE}/libmnl/install/include/libmnl/libmnl.h usr/include/libmnl/
rm usr/lib/arm-linux-gnueabihf//libmnl.so
cp -a ${BASE}/libmnl/install/lib/libmnl.so usr/lib/arm-linux-gnueabihf/
cp ${BASE}/libmnl/install/lib/pkgconfig/libmnl.pc usr/lib/arm-linux-gnueabihf/pkgconfig/
sed -i "s|^Version: .*|Version: ${NEW_LIBMNL_VER}|" DEBIAN/control
sed -i "s|^Installed-Size: .*|Installed-Size: $(du -s usr | awk '{print $1}')|" DEBIAN/control
sed -i "s|^Depends: .*|Depends: libmnl0 (= ${NEW_LIBMNL_VER})|" DEBIAN/control
find usr -type f -exec md5sum {} \; > DEBIAN/md5sums
cd ..
dpkg-deb --build --root-owner-group ${DIR}
apt install -y ./${DIR}.deb
mv ${DIR}.deb ${BASE}

#################################################################################
# Compile lastest version of libnftnl for armhf:
#################################################################################
cd ${BASE}
git clone git://git.netfilter.org/libnftnl
cd libnftnl
mkdir -p {install,modded}
./autogen.sh
./configure --host=arm-linux-gnueabihf --prefix=$PWD/install
make clean
make
make install

#================================================================================
# Get version and build numbers for these packages:
LIBNFTNL_BUILD=1
OLD_LIBNFTNL_VER=$(deb_version libnftnl11)
NEW_LIBNFTNL_VER=$(git_version libnftnl ${LIBNFTNL_BUILD})

#================================================================================
# Modify existing deb package for "libnftnl11" with our compiled files:
cd ${BASE}/libnftnl/modded
DIR=libnftnl11_${NEW_LIBNFTNL_VER}_armhf
apt download libnftnl11=${OLD_LIBNFTNL_VER}
dpkg-deb -R libnftnl11_${OLD_LIBNFTNL_VER}_armhf.deb ${DIR}
rm libnftnl11_${OLD_LIBNFTNL_VER}_armhf.deb
cd ${BASE}/libnftnl/modded/${DIR}
rm -rf usr/share/doc/libnftnl11/*
cp ${BASE}/libmnl/{README,COPYING} usr/share/doc/libnftnl11/
rm usr/lib/arm-linux-gnueabihf/*
cp -a ${BASE}/libnftnl/install/lib/*.11* usr/lib/arm-linux-gnueabihf/ 
rm DEBIAN/{shlibs,symbols,triggers}
sed -i "s|^Version: .*|Version: ${NEW_LIBNFTNL_VER}|" DEBIAN/control
sed -i "s|^Installed-Size: .*|Installed-Size: $(du -s usr | awk '{print $1}')|" DEBIAN/control
OLD_DEPENDS=($(grep Depends DEBIAN/control))
sed -i "s|${OLD_DEPENDS[-1]}|${NEW_LIBMNL_VER}\)|" DEBIAN/control
find usr -type f -exec md5sum {} \; > DEBIAN/md5sums
cd ..
dpkg-deb --build --root-owner-group ${DIR}
apt install -y ./${DIR}.deb
mv ${DIR}.deb ${BASE}

#================================================================================
# Modify existing deb package for "libnftnl-dev" with our compiled files:
DIR=libnftnl-dev_${NEW_LIBNFTNL_VER}_armhf
apt download libnftnl-dev=${OLD_LIBNFTNL_VER}
dpkg-deb -R libnftnl-dev_${OLD_LIBNFTNL_VER}_armhf.deb ${DIR}
rm libnftnl-dev_${OLD_LIBNFTNL_VER}_armhf.deb
cd ${BASE}/libnftnl/modded/${DIR}
rm usr/share/doc/libnftnl-dev/*
cp ${BASE}/libnftnl/install/include/libnftnl/* usr/include/libnftnl/
rm usr/lib/arm-linux-gnueabihf/{lib*,*.a}
cp -a ${BASE}/libnftnl/install/lib/libnftnl.so usr/lib/arm-linux-gnueabihf/
cp -a ${BASE}/libnftnl/install/lib/pkgconfig/* usr/lib/arm-linux-gnueabihf/pkgconfig/
sed -i "s|^Version: .*|Version: ${NEW_LIBNFTNL_VER}|" DEBIAN/control
sed -i "s|^Installed-Size: .*|Installed-Size: $(du -s usr | awk '{print $1}')|" DEBIAN/control
sed -i "s|$(grep -o "libnftnl11 (= [^)]*)" DEBIAN/control)|libnftnl11 (= ${NEW_LIBNFTNL_VER})|" DEBIAN/control
find usr -type f -exec md5sum {} \; > DEBIAN/md5sums
cd ..
dpkg-deb --build --root-owner-group ${DIR}
apt install -y ./${DIR}.deb
mv ${DIR}.deb ${BASE}

#################################################################################
# Compile latest version of nft for armhf:
#################################################################################
cd ${BASE}
git clone git://git.netfilter.org/nftables
cd ${BASE}/nftables/
mkdir -p {install,modded}
./autogen.sh
./configure --host=arm-linux-gnueabihf --prefix=$PWD/install --with-mini-gmp --without-cli
make
make install

#================================================================================
# Get version and build numbers for these packages:
NFTABLES_BUILD=1
OLD_NFTABLES_VER=$(deb_version nftables)
NEW_NFTABLES_VER=$(git_version nftables ${LIBNFTNL_BUILD})

#================================================================================
# Modify existing deb package for "libnftnl-dev" with our compiled files:
cd ${BASE}/nftables/modded
DIR=nftables_${NEW_NFTABLES_VER}_armhf
apt download nftables=${OLD_NFTABLES_VER}
dpkg-deb -R nftables_${OLD_NFTABLES_VER}_armhf.deb ${DIR}
rm nftables_${OLD_NFTABLES_VER}_armhf.deb
cd ${BASE}/nftables/modded/${DIR}
cp ${BASE}/nftables/install/sbin/nft usr/sbin/
rm usr/share/doc/nftables/{changelog,copyright}*
rm usr/share/doc/nftables/examples/*.nft
cp ${BASE}/nftables/install/share/nftables/*.nft usr/share/doc/nftables/examples/
cp ${BASE}/nftables/install/share/doc/nftables/examples/*.nft usr/share/doc/nftables/examples/
cp ${BASE}/nftables/install/share/man/man8/* usr/share/man/man8/
gzip -f usr/share/man/man8/nft.8
sed -i "s|^Version: .*|Version: ${NEW_NFTABLES_VER}|" DEBIAN/control
sed -i "s|^Installed-Size: .*|Installed-Size: $(( $(du -s . | awk '{print $1}') - $(du -s DEBIAN | awk '{print $1}') ))|" DEBIAN/control
LIBNFTABLES1_VER=$(grep -o "libnftables1 (= [^)]*)" DEBIAN/control)
sed -i "s|${LIBNFTABLES1_VER}|libnftables1 (= ${NEW_NFTABLES_VER})|" DEBIAN/control
find usr -type f -exec md5sum {} \; > DEBIAN/md5sums
cd ..
dpkg-deb --build --root-owner-group ${DIR}

#================================================================================
# Modify existing deb package for "libnftables1" with our compiled files:
cd ${BASE}/nftables/modded
DIR=libnftables1_${NEW_NFTABLES_VER}_armhf
apt download libnftables1=${OLD_NFTABLES_VER}
dpkg-deb -R libnftables1_${OLD_NFTABLES_VER}_armhf.deb ${DIR}
rm libnftables1_${OLD_NFTABLES_VER}_armhf.deb
cd ${BASE}/nftables/modded/${DIR}
rm usr/lib/arm-linux-gnueabihf/*
rm -rf usr/share/doc/libnftables1/*
cp ${BASE}/nftables/COPYING usr/share/doc/libnftables1/ 
cp -a ${BASE}/nftables/install/lib/libnftables.so.1* usr/lib/arm-linux-gnueabihf/
cp ${BASE}/nftables/install/share/man/man3/libnftables.3 usr/share/man/man3/
gzip -f usr/share/man/man3/libnftables.3
cp ${BASE}/nftables/install/share/man/man5/libnftables-json.5 usr/share/man/man5/
gzip -f usr/share/man/man5/libnftables-json.5
rm DEBIAN/{shlibs,triggers}
sed -i "s|^Version: .*|Version: ${NEW_NFTABLES_VER}|" DEBIAN/control
sed -i "s|^Installed-Size: .*|Installed-Size: $(du -s usr | awk '{print $1}')|" DEBIAN/control
sed -i "s|$(grep -o "libmnl0 (>= [^)]*)" DEBIAN/control)|libmnl0 (>= ${NEW_LIBMNL_VER})|" DEBIAN/control
sed -i "s|$(grep -o "libnftnl11 (>= [^)]*)" DEBIAN/control)|libnftnl11 (>= ${NEW_LIBNFTNL_VER})|" DEBIAN/control
find usr -type f -exec md5sum {} \; > DEBIAN/md5sums
cd ..
dpkg-deb --build --root-owner-group ${DIR}

#================================================================================
# Modify existing deb package for "libnftables-dev" with our compiled files:
cd ${BASE}/nftables/modded
DIR=libnftables-dev_${NEW_NFTABLES_VER}_armhf
apt download libnftables-dev=${OLD_NFTABLES_VER}
dpkg-deb -R libnftables-dev_${OLD_NFTABLES_VER}_armhf.deb ${DIR}
rm libnftables-dev_${OLD_NFTABLES_VER}_armhf.deb
cd ${BASE}/nftables/modded/${DIR}
rm -rf usr/share/doc/libnftables-dev/*
cp ${BASE}/nftables/COPYING usr/share/doc/libnftables-dev/
cp ${BASE}/nftables/install/include/nftables/* usr/include/nftables/
cp -a ${BASE}/nftables/install/lib/*.so usr/lib/arm-linux-gnueabihf/
cp ${BASE}/nftables/install/lib/pkgconfig/* usr/lib/arm-linux-gnueabihf/pkgconfig/
sed -i "s|^Version: .*|Version: ${NEW_NFTABLES_VER}|" DEBIAN/control
sed -i "s|^Installed-Size: .*|Installed-Size: $(du -s usr | awk '{print $1}')|" DEBIAN/control
sed -i "s|$(grep -o "libnftables1 (= [^)]*)" DEBIAN/control)|libnftables1 (= ${NEW_NFTABLES_VER})|" DEBIAN/control
find usr -type f -exec md5sum {} \; > DEBIAN/md5sums
cd ..
dpkg-deb --build --root-owner-group ${DIR}
apt install -y ./*.deb
mv *.deb ${BASE}
