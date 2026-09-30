#!/usr/bin/env bash
set -euo pipefail

repo_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
svn_root="${1:-}"

if [[ -z "$svn_root" ]]; then
  echo "Usage: bash scripts/nmkr-wporg-prepare-assets.sh /path/to/wporg-svn-checkout" >&2
  exit 64
fi

if [[ ! -d "$svn_root/.svn" ]]; then
  echo "Not an SVN working copy: $svn_root" >&2
  exit 65
fi

expected_url="https://plugins.svn.wordpress.org/rocsi-connector-for-nmkr"
actual_url="$(svn info --show-item url "$svn_root")"
if [[ "$actual_url" != "$expected_url" ]]; then
  echo "Unexpected SVN repository: $actual_url" >&2
  exit 66
fi

svn update "$svn_root"

if [[ -n "$(svn status "$svn_root/assets")" ]]; then
  echo "SVN assets directory is not clean; review it before staging assets." >&2
  svn status "$svn_root/assets" >&2
  exit 67
fi

assets=(
  banner-1544x500.png
  banner-772x250.png
  icon-128x128.png
  icon-256x256.png
)

for name in "${assets[@]}"; do
  source_file="$repo_root/.wordpress-org/$name"
  target_file="$svn_root/assets/$name"

  if [[ ! -f "$source_file" ]]; then
    echo "Missing Git source asset: $source_file" >&2
    exit 68
  fi

  cp "$source_file" "$target_file"
  svn add --force "$target_file" >/dev/null
  svn propset svn:mime-type image/png "$target_file" >/dev/null

  if [[ "$(svn propget svn:mime-type "$target_file")" != "image/png" ]]; then
    echo "Failed to set image/png MIME type on $target_file" >&2
    exit 69
  fi
done

echo "WordPress.org assets staged from Git source with image/png MIME properties."
svn status "$svn_root/assets"
svn diff "$svn_root/assets"
