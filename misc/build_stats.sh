#!/bin/bash
if [[ "$UID" -ne 1000 ]]; then
	sudo -u pi -s $0 $@
	exit
fi
cd /var/lib/stats
virtualenv env
source env/bin/activate
tar xzvf /opt/stats/ssd1306_python3.tar.gz
python3 -m pip install --no-index --find-links=./whl psutil Adafruit-SSD1306 Adafruit-BBIO
rm -rf whl

