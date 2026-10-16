#!/bin/sh
# Moves mediapool, templates and playlists/items (rendered template items) from public/var to var/storage.
# Idempotent: does nothing if the old directories do not exist.
# Usage: bin/migrate-storage.sh [project-root]   (default: /var/www)
set -eu

ROOT="${1:-/var/www}"
OLD="$ROOT/public/var"
NEW="$ROOT/var/storage"

for dir in mediapool templates playlists/items; do
  src="$OLD/$dir"
  dst="$NEW/$dir"

  [ -d "$src" ] || continue
  # nested entries like playlists/items need their parent below var/storage
  mkdir -p "$(dirname "$dst")"

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

  # drop parents left empty by a nested move (public/var/playlists), never public/var itself
  parent="$(dirname "$src")"
  while [ "$parent" != "$OLD" ] && rmdir "$parent" 2>/dev/null; do
    parent="$(dirname "$parent")"
  done

  echo "migrated: $dir"
done
