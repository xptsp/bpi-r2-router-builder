#!/bin/bash

# Override the function handling DNS addresses so that the updates 
# happen in the PiHole configuration, not the "/etc/resolv.conf" file:
make_resolv_conf() {
	grep "use_isp=Y" /etc/default/router-settings >& /dev/null && echo "${new_domain_name_servers}" | sed "s| |\n|g" | sed "s|^|nameserver |g" > /etc/resolv.conf
}
