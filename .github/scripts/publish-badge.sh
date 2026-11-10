#!/bin/bash
# Puts one badge into the branch "badges" of the README:  publish-badge.sh <file.svg>
# The branch holds the badges of several workflows (coverage, mutation). The other badges stay,
# the branch is replaced by one new commit, so it does not grow. Needs GITHUB_TOKEN with write access.
set -euo pipefail

file="$(realpath "$1")"
name="$(basename "$file")"
remote="https://x-access-token:${GITHUB_TOKEN}@github.com/${GITHUB_REPOSITORY}.git"
work="$(mktemp -d)"

cd "$work"
git init -q -b badges
git config user.name "github-actions[bot]"
git config user.email "41898282+github-actions[bot]@users.noreply.github.com"
git remote add origin "$remote"

# the badges which are there already; the very first run finds no branch
if git fetch -q --depth 1 origin badges 2>/dev/null; then
	git checkout -q FETCH_HEAD -- .
fi

cp "$file" "$name"
git add -A
git commit -q -m "$name of ${GITHUB_SHA::8}"
git push -q --force origin badges
