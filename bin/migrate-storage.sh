#!/bin/sh
# Moves mediapool and templates from public/var to var/storage.
# Idempotent: does nothing if the old directories do not exist.
# Usage: bin/migrate-storage.sh [project-root]   (default: /var/www)
set -eu

ROOT="${1:-/var/www}"
OLD="$ROOT/public/var"
NEW="$ROOT/var/storage"

for dir in mediapool templates; do
  src="$OLD/$dir"
  dst="$NEW/$dir"

  [ -d "$src" ] || continue
  mkdir -p "$NEW"

  if [ -e "$dst" ]; then
    echo "WARN: $dst already exists, skipping $dir" >&2
    continue
  fi

  # Same filesystem: mv is an atomic rename.
  if [ "$(stat -c %d "$OLD")" = "$(stat -c %d "$NEW")" ]; then
    mv "$src" "$dst"
  else
    # Different filesystems (e.g. two docker volumes): copy, verify, then delete.
    tmp="$dst.tmp"
    rm -rf "$tmp"
    cp -a "$src" "$tmp"

    [ "$(find "$src" -type f | wc -l)" = "$(find "$tmp" -type f | wc -l)" ] \
      || { echo "ERROR: file count differs ($dir)" >&2; exit 1; }
    [ "$(du -sb "$src" | cut -f1)" = "$(du -sb "$tmp" | cut -f1)" ] \
      || { echo "ERROR: size differs ($dir)" >&2; exit 1; }

    mv "$tmp" "$dst"
    rm -rf "$src"
  fi
  echo "migrated: $dir"
done
