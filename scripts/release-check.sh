#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

COMPOSER_BIN="${COMPOSER_BIN:-composer}"

if [[ ! -f vendor/autoload.php ]]; then
  echo "Run composer install before release-check."
  exit 1
fi

if ! command -v rg >/dev/null 2>&1; then
  echo "ripgrep (rg) is required for boundary scans."
  exit 1
fi

echo "==> composer validate --strict"
"$COMPOSER_BIN" validate --strict

echo "==> Architecture compliance - DBErp leakage"
if rg "DBErp|PurchaseRequest|DberpPermissionService|PurchaseRequestStatus" src/ config/ lang/en/ resources/ composer.json README.md; then
  echo "Forbidden DBErp terms found in package release paths."
  exit 1
fi

echo "==> Architecture compliance - stale App\\DBFlow namespace"
if rg "namespace App\\\\DBFlow|use App\\\\DBFlow|App\\\\\\\\DBFlow" src/ config/ lang/en/ resources/; then
  echo "Stale App\\DBFlow namespace found in package release paths."
  exit 1
fi

echo "==> Architecture compliance - forbidden brand terms"
if rg -i "loongdom|Loongdom\\\\DBFlow|dbflow/core" src/ config/ lang/en/ resources/ composer.json; then
  echo "Forbidden brand terms found in package release paths."
  exit 1
fi

echo "==> Architecture compliance - Pro/canvas runtime leakage"
if rg -i "LogicFlow|graph preview|visual builder|drag-and-drop|node editor" src/ config/ lang/en/ resources/ composer.json; then
  echo "Pro/canvas terms found in package runtime paths."
  exit 1
fi

echo "==> Security gate - embedded credentials"
if rg "ghp_|github_pat_|https://.*github.com.*@" src/ config/ lang/en/ resources/ composer.json README.md; then
  echo "Embedded credential patterns found in package release paths."
  exit 1
fi

echo "==> Architecture compliance - English-only source"
if rg -n "[\x{3400}-\x{4DBF}\x{4E00}-\x{9FFF}\x{F900}-\x{FAFF}]" src/ config/ lang/en/ resources/ composer.json README.md; then
  echo "Non-English characters found in package release paths."
  exit 1
fi

echo "==> composer test"
"$COMPOSER_BIN" test

echo "Release checks passed."
