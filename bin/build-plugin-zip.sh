#!/usr/bin/env bash

set -euo pipefail

root_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
build_dir="${root_dir}/build"
package_dir="${build_dir}/aspen-addon-manager"
archive="${build_dir}/aspen-addon-manager.zip"

rm -rf "${package_dir}" "${archive}"
mkdir -p "${package_dir}"

cp "${root_dir}/aspen-addon-manager.php" "${package_dir}/"
cp "${root_dir}/README.md" "${package_dir}/"
cp -R "${root_dir}/includes" "${package_dir}/"

(
	cd "${build_dir}"
	zip -qr "${archive}" aspen-addon-manager
)

printf 'Created %s\n' "${archive}"
