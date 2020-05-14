#!/bin/sh

: ${DBPATH:=/var/db/rrd}
: ${CF_CONF_PATH:=/cf/conf}

: ${RAM_Disk_Store:=${CF_CONF_PATH}/RAM_Disk_Store}

# Save the rrd databases to the RAM disk store.
if [ -d "${DBPATH}" ]; then
	echo -n "Salvando RRD sulla memoria RAM...";

	[ -f "${RAM_Disk_Store}/rrd.tgz" ] && /bin/rm -f "${RAM_Disk_Store}/rrd.tgz"

	if [ ! -d "${RAM_Disk_Store}" ]; then
		mkdir -p "${RAM_Disk_Store}"
	fi

	/usr/bin/tar -czf "${RAM_Disk_Store}/rrd.tgz" -C / "${DBPATH#/}/"

	echo "fatto.";
fi
