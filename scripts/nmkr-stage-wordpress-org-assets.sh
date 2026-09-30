#!/usr/bin/env bash
set -euo pipefail

usage() {
  echo "Usage: $0 /path/to/wordpress-org-svn-checkout" >&2
  exit 64
}

[[ $# -eq 1 ]] || usage

command -v svn >/dev/null 2>&1 || {
  echo "error: svn is required" >&2
  exit 69
}

repo_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
source_dir="$repo_root/.wordpress-org"
svn_root="$1"
assets_dir="$svn_root/assets"

svn info "$svn_root" >/dev/null 2>&1 || {
  echo "error: target is not an SVN working copy: $svn_root" >&2
  exit 65
}

mkdir -p "$assets_dir"

assets=(
  "icon-128x128.png"
  "icon-256x256.png"
  "banner-772x250.png"
  "banner-1544x500.png"
)

png_signature="89 50 4e 47 0d 0a 1a 0a"

for asset in "${assets[@]}"; do
  src="$source_dir/$asset"
  dest="$assets_dir/$asset"

  [[ -f "$src" ]] || {
    echo "error: missing source asset: $src" >&2
    exit 66
  }

  actual_signature="$(od -An -tx1 -N8 "$src" | tr -s ' ' | sed 's/^ //')"
  [[ "$actual_signature" == "$png_signature" ]] || {
    echo "error: source asset is not a PNG: $src" >&2
    exit 67
  }

  cp "$src" "$dest"

  if ! svn info "$dest" >/dev/null 2>&1; then
    svn add "$dest"
  fi

  svn propset svn:mime-type image/png "$dest" >/dev/null

  actual_mime="$(svn propget svn:mime-type "$dest")"
  [[ "$actual_mime" == "image/png" ]] || {
    echo "error: unexpected svn:mime-type for $dest: $actual_mime" >&2
    exit 68
  }

  echo "staged: assets/$asset (svn:mime-type=image/png)"
done

echo
echo "Review before committing:"
svn status "$assets_dir"
