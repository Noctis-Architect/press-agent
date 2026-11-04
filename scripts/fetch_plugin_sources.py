#!/usr/bin/env python3
"""Download official (free) WordPress.org plugin sources for capability analysis.

Never downloads premium, nulled, or third-party redistributed copies. Pro
features are documented from public sources and detected at runtime instead.
"""

import argparse
import hashlib
import json
import os
import shutil
import sys
import tempfile
import urllib.parse
import urllib.request
import zipfile
from datetime import datetime, timezone

PLUGINS = [
    "wordpress-seo",                          # Yoast SEO
    "seo-by-rank-math",                       # Rank Math SEO
    "wordfence",                              # Wordfence Security
    "all-in-one-wp-security-and-firewall",    # AIOS
    "elementor",                              # Elementor
    "litespeed-cache",                        # LiteSpeed Cache
    "contact-form-7",                         # Contact Form 7
    "wpforms-lite",                           # WPForms Lite
    "woocommerce",                            # WooCommerce
    "redirection",                            # Redirection
    "wp-mail-smtp",                           # WP Mail SMTP
    "really-simple-ssl",                      # Really Simple Security
]

API = "https://api.wordpress.org/plugins/info/1.2/"
ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))


def api_info(slug: str) -> dict:
    query = urllib.parse.urlencode(
        {"action": "plugin_information", "request[slug]": slug}
    )
    with urllib.request.urlopen(f"{API}?{query}", timeout=60) as resp:
        data = json.load(resp)
    if not data.get("download_link"):
        raise RuntimeError(f"{slug}: no download_link in API response")
    return data


def safe_extract(zip_path: str, dest: str) -> None:
    dest_abs = os.path.abspath(dest)
    with zipfile.ZipFile(zip_path) as zf:
        for member in zf.infolist():
            target = os.path.abspath(os.path.join(dest_abs, member.filename))
            if not target.startswith(dest_abs + os.sep) and target != dest_abs:
                raise RuntimeError(f"unsafe zip entry: {member.filename}")
        zf.extractall(dest_abs)


def fetch(slug: str, dest_root: str, force: bool) -> dict:
    info = api_info(slug)
    version = info["version"]
    plugin_dir = os.path.join(dest_root, slug)
    manifest_path = os.path.join(ROOT, "plugin-sources.json")

    manifest = {}
    if os.path.exists(manifest_path):
        with open(manifest_path, "r", encoding="utf-8") as fh:
            manifest = json.load(fh)

    existing = manifest.get(slug)
    if existing and existing.get("version") == version and os.path.isdir(plugin_dir) and not force:
        print(f"= {slug} {version} already present")
        return existing

    print(f"> downloading {slug} {version} ...")
    with tempfile.TemporaryDirectory() as tmp:
        zip_path = os.path.join(tmp, f"{slug}.zip")
        with urllib.request.urlopen(info["download_link"], timeout=300) as resp, open(zip_path, "wb") as out:
            shutil.copyfileobj(resp, out)

        digest = hashlib.sha256()
        with open(zip_path, "rb") as fh:
            for chunk in iter(lambda: fh.read(1024 * 1024), b""):
                digest.update(chunk)
        sha256 = digest.hexdigest()

        if os.path.isdir(plugin_dir):
            shutil.rmtree(plugin_dir)
        os.makedirs(dest_root, exist_ok=True)
        safe_extract(zip_path, dest_root)

    entry = {
        "slug": slug,
        "name": info.get("name", slug),
        "version": version,
        "requires": info.get("requires"),
        "tested": info.get("tested"),
        "requires_php": info.get("requires_php"),
        "last_updated": info.get("last_updated"),
        "download_url": info["download_link"],
        "sha256": sha256,
        "fetched_at": datetime.now(timezone.utc).strftime("%Y-%m-%dT%H:%M:%SZ"),
    }
    manifest[slug] = entry
    with open(manifest_path, "w", encoding="utf-8") as fh:
        json.dump(dict(sorted(manifest.items())), fh, ensure_ascii=False, indent=2)
        fh.write("\n")

    print(f"  {slug} {version} -> {plugin_dir}")
    return entry


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("--dest", default=os.path.join(ROOT, "vendor", "plugins"))
    parser.add_argument("--only", nargs="*", default=None)
    parser.add_argument("--force", action="store_true")
    args = parser.parse_args()

    slugs = args.only or PLUGINS
    os.makedirs(args.dest, exist_ok=True)
    failed = []
    for slug in slugs:
        try:
            fetch(slug, args.dest, args.force)
        except Exception as exc:  # noqa: BLE001 - CLI should continue and report
            print(f"! {slug}: {exc}", file=sys.stderr)
            failed.append(slug)

    if failed:
        print(f"failed: {', '.join(failed)}", file=sys.stderr)
        return 1
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
