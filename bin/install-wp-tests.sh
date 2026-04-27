#!/usr/bin/env bash
# Install the WordPress test suite for PHPUnit.
#
# Usage:
#   bin/install-wp-tests.sh <db-name> <db-user> <db-pass> [db-host] [wp-version] [skip-database-creation]
#
# Defaults: db-host=localhost, wp-version=latest, skip-database-creation=false.
# Honors WP_TESTS_DIR / WP_CORE_DIR if set; otherwise installs under /tmp.

set -euo pipefail

if [ $# -lt 3 ]; then
	echo "usage: $0 <db-name> <db-user> <db-pass> [db-host] [wp-version] [skip-database-creation]"
	exit 1
fi

DB_NAME=$1
DB_USER=$2
DB_PASS=$3
DB_HOST=${4-localhost}
WP_VERSION=${5-latest}
SKIP_DB_CREATE=${6-false}

WP_TESTS_DIR=${WP_TESTS_DIR-/tmp/wordpress-tests-lib}
WP_CORE_DIR=${WP_CORE_DIR-/tmp/wordpress/}

download() {
	if command -v curl >/dev/null 2>&1; then
		curl -fsSL "$1" -o "$2"
	elif command -v wget >/dev/null 2>&1; then
		wget -nv -O "$2" "$1"
	else
		echo "Error: neither curl nor wget is available." >&2
		exit 1
	fi
}

# WordPress doesn't tag x.y.0 patches separately — they live under tags/x.y.
# Normalize so both the explicit x.y.z branch and the `latest`-via-API branch
# agree on the SVN path.
release_tag_for() {
	local v="$1"
	case "$v" in
		*.0) echo "tags/${v%.0}" ;;
		*)   echo "tags/$v" ;;
	esac
}

# Resolve the SVN tag the test suite ships under for the requested version.
if [[ $WP_VERSION =~ ^[0-9]+\.[0-9]+\-(beta|RC)[0-9]+$ ]]; then
	WP_TESTS_TAG="branches/${WP_VERSION%-*}"
elif [[ $WP_VERSION =~ ^[0-9]+\.[0-9]+$ ]]; then
	WP_TESTS_TAG="branches/$WP_VERSION"
elif [[ $WP_VERSION =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
	WP_TESTS_TAG=$(release_tag_for "$WP_VERSION")
elif [[ $WP_VERSION == 'nightly' || $WP_VERSION == 'trunk' ]]; then
	WP_TESTS_TAG="trunk"
else
	download https://api.wordpress.org/core/version-check/1.7/ /tmp/wp-latest.json
	LATEST_VERSION=$(grep -o '"version":"[^"]*' /tmp/wp-latest.json | sed 's/"version":"//' | head -1)
	if [ -z "$LATEST_VERSION" ]; then
		echo "Could not resolve latest WordPress version." >&2
		exit 1
	fi
	WP_TESTS_TAG=$(release_tag_for "$LATEST_VERSION")
fi

install_wp() {
	if [ -d "$WP_CORE_DIR" ] && [ -f "$WP_CORE_DIR/wp-load.php" ]; then
		return
	fi
	mkdir -p "$WP_CORE_DIR"

	if [ "$WP_VERSION" == 'nightly' ] || [ "$WP_VERSION" == 'trunk' ]; then
		download https://wordpress.org/nightly-builds/wordpress-latest.zip /tmp/wordpress-nightly.zip
		unzip -q /tmp/wordpress-nightly.zip -d /tmp/wordpress-nightly/
		mv /tmp/wordpress-nightly/wordpress/* "$WP_CORE_DIR"
	else
		local archive
		if [ "$WP_VERSION" == 'latest' ]; then
			archive='latest'
		else
			archive="wordpress-$WP_VERSION"
		fi
		download "https://wordpress.org/${archive}.tar.gz" /tmp/wordpress.tar.gz
		tar --strip-components=1 -zxmf /tmp/wordpress.tar.gz -C "$WP_CORE_DIR"
	fi
}

install_test_suite() {
	local sed_inplace
	if [[ $(uname -s) == 'Darwin' ]]; then
		sed_inplace=( -i.bak )
	else
		sed_inplace=( -i )
	fi

	if [ ! -d "$WP_TESTS_DIR/includes" ]; then
		mkdir -p "$WP_TESTS_DIR"
		svn co --quiet --ignore-externals "https://develop.svn.wordpress.org/${WP_TESTS_TAG}/tests/phpunit/includes/" "$WP_TESTS_DIR/includes"
		svn co --quiet --ignore-externals "https://develop.svn.wordpress.org/${WP_TESTS_TAG}/tests/phpunit/data/" "$WP_TESTS_DIR/data"
	fi

	if [ ! -f "$WP_TESTS_DIR/wp-tests-config.php" ]; then
		download "https://develop.svn.wordpress.org/${WP_TESTS_TAG}/wp-tests-config-sample.php" "$WP_TESTS_DIR/wp-tests-config.php"
		local core_dir
		core_dir=$(echo "$WP_CORE_DIR" | sed "s:/\+$::")
		sed "${sed_inplace[@]}" "s:dirname( __FILE__ ) . '/src/':'$core_dir/':" "$WP_TESTS_DIR/wp-tests-config.php"
		sed "${sed_inplace[@]}" "s/youremptytestdbnamehere/$DB_NAME/" "$WP_TESTS_DIR/wp-tests-config.php"
		sed "${sed_inplace[@]}" "s/yourusernamehere/$DB_USER/" "$WP_TESTS_DIR/wp-tests-config.php"
		sed "${sed_inplace[@]}" "s/yourpasswordhere/$DB_PASS/" "$WP_TESTS_DIR/wp-tests-config.php"
		sed "${sed_inplace[@]}" "s|localhost|${DB_HOST}|" "$WP_TESTS_DIR/wp-tests-config.php"
	fi
}

install_db() {
	if [ "$SKIP_DB_CREATE" == 'true' ]; then
		return 0
	fi

	# Split host:port or host:/path/to/socket into an explicit array of
	# mysqladmin flags so each value stays in its own argv slot regardless
	# of word-splitting rules.
	local host_part="${DB_HOST%%:*}"
	local port_or_sock="${DB_HOST#*:}"
	local conn=( "--user=$DB_USER" "--password=$DB_PASS" )
	if [ "$port_or_sock" != "$DB_HOST" ]; then
		if [[ $port_or_sock =~ ^[0-9]+$ ]]; then
			conn+=( "--host=$host_part" "--port=$port_or_sock" --protocol=tcp )
		else
			conn+=( "--socket=$port_or_sock" )
		fi
	elif [ -n "$host_part" ]; then
		conn+=( "--host=$host_part" --protocol=tcp )
	fi

	# Drop and recreate to keep runs deterministic; safe because SKIP_DB_CREATE
	# guards production paths.
	mysqladmin -f drop "$DB_NAME" "${conn[@]}" 2>/dev/null || true
	mysqladmin create "$DB_NAME" "${conn[@]}"
}

install_wp
install_test_suite
install_db
