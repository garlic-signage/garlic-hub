#!/bin/bash
# Mutation tests with Infection, configuration in infection.json5, reports in var/infection/.
# Runs in ddev:  ddev exec bin/infection.sh            whole code base (takes hours)
#                ddev exec bin/infection.sh v1.2.0     only the lines changed since this git ref
# Each thread gets its own TEST_TOKEN, the tests use it for their temp directories.
set -e

threads=$(( $(nproc) * 3 / 4 ))
args=(--threads="$threads" --no-progress)

if [ -n "$1" ]; then
	args+=(--git-diff-lines --git-diff-base="$1")
fi

mkdir -p var/infection/tmp

# Xdebug is not loaded in ddev by default. An ini in the scan path also reaches the child processes,
# e.g. tests with RunInSeparateProcess; -d options would not. Only coverage mode, no debugger; JIT off, otherwise PHP warns in every process.
printf 'zend_extension=xdebug.so\nxdebug.mode=coverage\nopcache.jit=disable\n' > var/infection/tmp/xdebug.ini
export PHP_INI_SCAN_DIR="$(php -r 'echo PHP_CONFIG_FILE_SCAN_DIR;'):$PWD/var/infection/tmp"
export XDEBUG_MODE=coverage

vendor/bin/infection "${args[@]}"
