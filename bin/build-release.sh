#!/usr/bin/env bash
set -euo pipefail

root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
slug="hold-this-product"
main_file="$root/HoldThisProduct.php"
dist_dir="$root/dist"

cd "$root"

if ! git diff --quiet || ! git diff --cached --quiet; then
	echo "Release builds require a clean working tree." >&2
	exit 1
fi

header_version="$(sed -n 's/^ \* Version:[[:space:]]*\([^[:space:]]*\).*/\1/p' "$main_file" | head -n 1)"
constant_version="$(sed -n "s/^define( 'HTP_VERSION', '\([^']*\)' );/\1/p" "$main_file" | head -n 1)"
if [[ -z "$header_version" || "$header_version" != "$constant_version" ]]; then
	echo "Plugin header and HTP_VERSION must contain the same version." >&2
	exit 1
fi

source_date_epoch="${SOURCE_DATE_EPOCH:-$(git show -s --format=%ct HEAD)}"
archive_name="$slug-$header_version.zip"
temporary="$(mktemp -d)"
trap 'rm -rf "$temporary"' EXIT

git archive --format=tar --prefix="$slug/" HEAD | tar -xf - -C "$temporary"

while IFS= read -r excluded || [[ -n "$excluded" ]]; do
	[[ -z "$excluded" || "$excluded" == \#* ]] && continue
	excluded="${excluded#/}"
	rm -rf "$temporary/$slug/$excluded"
done < "$root/.distignore"

find "$temporary/$slug" -exec touch -h -d "@$source_date_epoch" {} +
mkdir -p "$dist_dir"
rm -f "$dist_dir/$archive_name" "$dist_dir/$archive_name.sha256"

(
	cd "$temporary"
	find "$slug" -print | LC_ALL=C sort | zip -X -q "$dist_dir/$archive_name" -@
)

(
	cd "$dist_dir"
	sha256sum "$archive_name" > "$archive_name.sha256"
)

echo "$dist_dir/$archive_name"
