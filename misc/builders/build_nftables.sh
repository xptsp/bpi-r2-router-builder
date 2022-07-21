#!/bin/bash

#################################################################################
# Install the packages we need to compile the software, then setup what we need:
#################################################################################
apt-get install -y --no-install-recommends git make gcc dh-autoreconf bison flex asciidoc pkg-config docbook-xsl xsltproc libxml2-utils
apt-get install -y --no-install-recommends checkinstall python3-distutils
BASE=/var/lib/docker/tmp
test -d ${BASE} && rm -rf ${BASE}
mkdir -p ${BASE}

#################################################################################
# Compile libmnl version 1.0.5:
#################################################################################
cd ${BASE}
git clone https://git.netfilter.org/libmnl
cd libmnl
git checkout 493aacf2ec9cc61a5b30d77cd55ec248f033bc74 -q
./autogen.sh
./configure
make

#################################################################################
# Build the libmnl0 package for armhf:
#################################################################################
checkinstall -y --pkgname=libmnl0 --pkgsource=libmnl --pkgversion=1.0.5 --pkgrelease=git20210807.493aacf --requires='"libc6 (>= 2.4)"'
cat << EOF > description-pak
minimalistic Netlink communication library
libmnl is a minimalistic user-space library oriented to Netlink developers.
There are a lot of common tasks in parsing, validating, constructing of
both the Netlink header and TLVs that are repetitive and easy to get wrong.
This library aims to provide simple helpers that allows you to re-use code
and to avoid re-inventing the wheel.
.
The main features of this library are:
.
Small: the shared library requires around 30KB for an x86-based computer.
.
Simple: this library avoids complexity and elaborated abstractions that
tend to hide Netlink details.
.
Easy to use: the library simplifies the work for Netlink-wise developers.
It provides functions to make socket handling, message building,
validating, parsing and sequence tracking, easier.
.
Easy to re-use: you can use the library to build your own abstraction
layer on top of this library.
.
Decoupling: the interdependency of the main bricks that compose the
library is reduced, i.e. the library provides many helpers, but the
programmer is not forced to use them.
.
This package contains the shared libraries needed to run programs that use
the minimalistic Netlink communication library.
EOF
checkinstall -y --pkgname=libmnl0 --pkgsource=libmnl --pkgversion=1.0.5 --pkgrelease=git20210807.493aacf --requires='"libc6 (>= 2.4)"'
mv libmnl0_1.0.5-git20210807.493aacf_armhf.deb ../

#################################################################################
# Compile libnftnl for armhf:
#################################################################################
cd ${BASE}
git clone git://git.netfilter.org/libnftnl
cd libnftnl
git checkout 0926cbe870187432fe7e1c227a44b59afbb4c6c5 -q
./autogen.sh
./configure --host=arm-linux-gnueabihf
make clean
make

#################################################################################
# Build the libnftnl11 package for armhf:
#################################################################################
checkinstall -y --pkgname=libnftnl11 --pkgsource=libnftnl --pkgversion=1.2.2 --pkgrelease=git20220615.84d12cf --requires='"libc6 (>= 2.4), libmnl0 (>= 1.0.5)"'
cat << EOF > description-pak
Netfilter nftables userspace API library
libnftnl is the low-level library for Netfilter 4th generation
framework nftables.
.
Is the user-space library for low-level interaction with
nftables Netlink's API over libmnl.
EOF
checkinstall -y --pkgname=libnftnl11 --pkgsource=libnftnl --pkgversion=1.2.2 --pkgrelease=git20220615.84d12cf --requires='"libc6 (>= 2.4), libmnl0 (>= 1.0.5)"'
mv libnftnl11_1.2.2-git20220615.84d12cf_armhf.deb ../

#################################################################################
# Compile nft for armhf:
#################################################################################
cd ${BASE}
git clone https://github.com/frank-w/nftables-bpi.git nftables
cd nftables/
git checkout 68dccd7f63dc3d9fa16acd1821b716af8dc24dce -q
mkdir -p install
./autogen.sh
./configure --host=arm-linux-gnueabihf  --prefix=$(pwd)/install --with-mini-gmp --without-cli
make
make install
