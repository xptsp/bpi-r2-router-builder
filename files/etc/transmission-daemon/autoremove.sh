#!/bin/sh
test -e /etc/default/transmission-default && source /etc/default/transmission-default

# Get the IP and port number to access the transmission-daemon WebUI:
PORT=$(cat /etc/nginx/sites-available/transmission | grep listen | awk '{print $2}')
SERVER="${PORT} --auth ${TRANS_USER}:${TRANS_PASS}"

# Use transmission-remote to get torrent list from transmission-remote list, deleteing first / last line of output, 
# and removing leading spaces use cut to get first field from each line
TORRENTLIST=$(transmission-remote $SERVER --list | sed -e '1d;$d;s/^ *//' | cut --only-delimited --delimiter=" " --fields=1)
transmission-remote $SERVER --list

# Process each torrent in the list:
for TORRENTID in $TORRENTLIST; do
    echo Processing : $TORRENTID

    # Check if torrent download is completed
    DL_COMPLETED=$(transmission-remote $SERVER --torrent $TORRENTID --info | grep "Percent Done: 100%")

    # Check torrents current state is
    STATE_STOPPED=$(transmission-remote $SERVER --torrent $TORRENTID --info | grep "State: Seeding\|Stopped\|Finished\|Idle")
    echo $STATE_STOPPED

    # If the torrent is "Stopped", "Finished", or "Idle" after downloading 100%"
    if [ "$DL_COMPLETED" ] && [ "$STATE_STOPPED" ]; then
        echo "Torrent #$TORRENTID is completed"
        echo "Removing torrent from list"
        transmission-remote $SERVER --torrent $TORRENTID --remove
    else
        echo "Torrent #$TORRENTID is not completed. Ignoring."
    fi
done

