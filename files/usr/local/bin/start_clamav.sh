#!/bin/bash
systemctl -q is-active clamav-daemon || sudo systemctl start clamav-daemon
