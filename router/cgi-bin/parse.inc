#!/bin/bash

# Our headers:
echo Content-type: text/html
echo ""

# Parse the query string into bash array "param":
declare -A param   
while IFS='=' read -r -d '&' key value; do
    param["$key"]="$value"
done <<<"${QUERY_STRING:+"${QUERY_STRING//;/&}&"}"

# If passed SID isn't valid, abort with error message:
[[ "$(curl localhost/validate?sid=${param['sid']}" != "VALID" ]] && echo "Invalid session ID!" && exit 1
