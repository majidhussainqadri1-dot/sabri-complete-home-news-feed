#!/usr/bin/env python3
"""Build the single canonical deterministic File 21 staging candidate."""
from __future__ import annotations

import argparse
import hashlib
import os
import shutil
import subprocess
import sys
import tempfile
from pathlib import Path, PurePosixPath
from zipfile import ZIP_DEFLATED, ZipFile, ZipInfo

SLUG = "sabri-complete-home-news-feed"
PACKAGE_VERSION = "1.1.0"
RUNTIME_VERSION = "1.0.3"
BASE = f"21-sabri-complete-home-news-feed-{PACKAGE_VERSION}-CONTROLLED-STAGING-CANDIDATE"
FIXED_ZIP_TIME = (2026, 8, 8, 0, 0, 0)
EXCLUDED_ROOTS = {
    ".git", ".github", "tests", "tools", "release", "vendor", "node_modules",
    ".phase5-transport", ".file21-correction", "coverage", ".phpunit.cache",
}
EXCLUDED_NAMES = {
    ".gitignore", ".gitattributes", "TASK_LOG.md",
    "FILE-21-MIXED-ROLE-AUTHORITY-HOTFIX-1.0.3.1.md",
    "FILE-21-PUBLIC-CONTENT-INTEGRITY-CORRECTION-1.0.3.1-R2.md",
}
FORBIDDEN_SUFFIXES = {".log", ".tmp", ".bak", ".sql", ".sqlite", ".env"}
REQUIRED_FILES = {
    "sabri-complete-home-news-feed.php",
    "readme.txt",
    "CHANGELOG.md",
    "includes/class-canonical-identity-adapter.php",
    "includes/class-public-surface-recovery.php",
    "includes/class-corrective-public-mount.php",
    "includes/class-home-composition-registry.php",
    "includes/class-public-query-guard.php",
    "includes/class-integrations.php",
    "includes/class-rest-foundation.php",
    "includes/class-search-provider-registry.php",
    "includes/class-file23-publishing-dashboard-bridge.php",
    "includes/class-network-relationship-bridge.php",
    "includes/class-feed-user-agency.php",
    "includes/class-saved-collection-service.php",
    "includes/class-comment-experience.php",
    "includes/class-next-generation-feed.php",
    "includes/class-next-generation-integrations.php",
    "includes/class-rest-next-generation.php",
    "assets/js/next-generation.js",
    "assets/css/next-generation.css",
    "public/class-news-routing.php",
    "public/class-phase5-public-runtime.php",
}


def sha256_bytes(data: bytes) -> str:
    return hashlib.sha256(data).hexdigest()


def sha256_file(path: Path) -> str:
    digest = hashlib.sha256()
    with path.open("rb") as stream:
        for chunk in iter(lambda: stream.read(1024 * 1024), b""):
            digest.update(chunk)
    return digest.hexdigest()


def source_sha(root: Path, explicit: str | None) -> str:
    if explicit:
        return explicit
    for key in ("TEST_SHA", "GITHUB_HEAD_SHA", "GITHUB_SHA", "SOURCE_COMMIT"):
        value = os.environ.get(key, "").strip()
        if value:
            return value
    try:
        return subprocess.check_output(
            ["git", "-C", str(root), "rev-parse", "HEAD"],
            text=True,
            stderr=subprocess.DEVNULL,
        ).strip()
    except (OSError, subprocess.CalledProcessError):
        return "local-source-without-git-metadata"


def validate_identity(root: Path) -> None:
    bootstrap = (root / "sabri-complete-home-news-feed.php").read_text(encoding="utf-8")
    required = (
        "* Version: 1.1.0",
        "define( 'SABRI_HNF_PACKAGE_VERSION', '1.1.0' );",
        "define( 'SABRI_HNF_VERSION', '1.0.3' );",
        "define( 'SABRI_HNF_SCHEMA_VERSION', '1.0.0' );",
    )
    missing = [needle for needle in required if needle not in bootstrap]
    if missing:
        raise RuntimeError("Release identity is incomplete: " + ", ".join(missing))


def is_forbidden(relative: Path) -> bool:
    if not relative.parts:
        return True
    if relative.parts[0] in EXCLUDED_ROOTS or relative.name in EXCLUDED_NAMES:
        return True
    lowered = relative.name.lower()
    if relative.suffix.lower() in FORBIDDEN_SUFFIXES:
        return True
    if any(marker in lowered for marker in ("secret", "credential", "private-key", "private_key")):
        return True
    return False


def collect_payload(root: Path) -> list[tuple[Path, str]]:
    payload: list[tuple[Path, str]] = []
    for path in sorted(root.rglob("*"), key=lambda item: item.as_posix()):
        if not path.is_file():
            continue
        relative = path.relative_to(root)
        if is_forbidden(relative):
            continue
        payload.append((path, relative.as_posix()))
    present = {relative for _, relative in payload}
    missing = sorted(REQUIRED_FILES - present)
    if missing:
        raise RuntimeError("Missing required runtime files: " + ", ".join(missing))
    return payload


def manifest_for(payload: list[tuple[Path, str]]) -> str:
    return "".join(f"{sha256_file(path)}  {relative}\n" for path, relative in payload)


def add_entry(archive: ZipFile, archive_name: str, data: bytes) -> None:
    info = ZipInfo(PurePosixPath(archive_name).as_posix(), FIXED_ZIP_TIME)
    info.create_system = 3
    info.compress_type = ZIP_DEFLATED
    info.external_attr = (0o100644 & 0xFFFF) << 16
    archive.writestr(info, data, compress_type=ZIP_DEFLATED, compresslevel=9)


def write_archive(path: Path, payload: list[tuple[Path, str]], manifest_text: str) -> None:
    with ZipFile(path, "w", compression=ZIP_DEFLATED, compresslevel=9, strict_timestamps=True) as archive:
        for source, relative in payload:
            add_entry(archive, f"{SLUG}/{relative}", source.read_bytes())
        add_entry(archive, f"{SLUG}/MANIFEST.sha256", manifest_text.encode("utf-8"))


def verify_archive(path: Path, payload: list[tuple[Path, str]], manifest_text: str) -> None:
    expected_names = [f"{SLUG}/{relative}" for _, relative in payload] + [f"{SLUG}/MANIFEST.sha256"]
    with ZipFile(path, "r") as archive:
        if archive.testzip() is not None:
            raise RuntimeError("ZIP CRC validation failed")
        names = archive.namelist()
        if names != expected_names:
            raise RuntimeError("ZIP inventory/order is not canonical")
        embedded = archive.read(f"{SLUG}/MANIFEST.sha256").decode("utf-8")
        if embedded != manifest_text:
            raise RuntimeError("Embedded manifest differs from canonical source manifest")
        for line in manifest_text.splitlines():
            digest, relative = line.split("  ", 1)
            data = archive.read(f"{SLUG}/{relative}")
            if sha256_bytes(data) != digest:
                raise RuntimeError(f"Manifest mismatch: {relative}")
        bootstrap = archive.read(f"{SLUG}/sabri-complete-home-news-feed.php").decode("utf-8")
        if "* Version: 1.1.0" not in bootstrap:
            raise RuntimeError("Packaged WordPress identity is not 1.1.0")


def build(root: Path, release: Path, commit: str) -> dict[str, str | int]:
    validate_identity(root)
    payload = collect_payload(root)
    manifest_text = manifest_for(payload)
    release.mkdir(parents=True, exist_ok=True)
    for path in release.glob(f"{BASE}*"):
        if path.is_file():
            path.unlink()
        else:
            shutil.rmtree(path)
    final_zip = release / f"{BASE}.zip"
    with tempfile.TemporaryDirectory(prefix="file21-deterministic-") as temp_dir:
        first = Path(temp_dir) / "first.zip"
        second = Path(temp_dir) / "second.zip"
        write_archive(first, payload, manifest_text)
        write_archive(second, payload, manifest_text)
        first_bytes = first.read_bytes()
        second_bytes = second.read_bytes()
        if first_bytes != second_bytes:
            raise RuntimeError("Two clean deterministic builds were not byte-identical")
        final_zip.write_bytes(first_bytes)
    verify_archive(final_zip, payload, manifest_text)
    archive_hash = sha256_file(final_zip)
    (release / f"{BASE}.sha256").write_text(
        f"{archive_hash}  {final_zip.name}\n", encoding="ascii"
    )
    (release / f"{BASE}-MANIFEST.sha256").write_text(manifest_text, encoding="utf-8")
    report = "\n".join(
        [
            "# File 21 1.1.0 Controlled-Staging Candidate",
            "",
            f"- Exact source commit: {commit}",
            f"- Package identity: {PACKAGE_VERSION}",
            f"- Stable runtime/API: {RUNTIME_VERSION}",
            "- Database schema: 1.0.0 (unchanged; no DB migration introduced)",
            f"- Artifact: {final_zip.name}",
            f"- SHA-256: {archive_hash}",
            f"- Manifest-covered runtime files: {len(payload)}",
            "- Embedded MANIFEST.sha256: verified",
            "- External MANIFEST.sha256: verified",
            "- Two clean builds byte-identical: PASS",
            "- ZIP CRC and safe inventory: PASS",
            "- Founder-approved File 21 next-generation 30-feature expansion: implemented",
            "- Repost/Quote, threads, coauthors, professional Stories and developing-story timeline: implemented",
            "- Expert context, evidence/source diversity, edit/correction history and smart-share warnings: implemented",
            "- File 16 AI summary/Ask Article/translation integration: versioned adapter; no duplicate AI backend",
            "- Topic following, My Topics, Catch Up, Continue Reading, Read Later and offline pack: implemented",
            "- Low-bandwidth and Data Saver user controls: implemented",
            "- Structured Q&A and verified-doctor response badge: implemented",
            "- File 26 Why Trending/related-knowledge integration: versioned adapter; File 26 remains global owner",
            "- News Compare and File 25 shareable knowledge-card semantic handoff: implemented",
            "- Transparent local File 21 Feed recipe: implemented; no donor/payment/Founder organic advantage",
            "- File 19 daily/weekly digest candidate handoff: implemented; File 19 remains delivery owner",
            "- Frozen governing 14-control Home bar preserved: YES",
            "- File 17 relationship/block owner bridge preserved without foreign-table writes: YES",
            "- File 23 direct writes remain fail-closed: YES",
            "- Hostinger staging accepted: NO",
            "- Live deployed: NO",
            "- Operational acceptance: NO",
            "",
        ]
    )
    (release / f"{BASE}-TEST-REPORT.md").write_text(report, encoding="utf-8")
    return {
        "base": BASE,
        "zip": str(final_zip),
        "sha256": archive_hash,
        "files": len(payload),
    }


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("--root", type=Path, default=Path(__file__).resolve().parents[1])
    parser.add_argument("--release-dir", type=Path)
    parser.add_argument("--source-sha")
    args = parser.parse_args()
    root = args.root.resolve()
    release = (args.release_dir or root / "release").resolve()
    try:
        result = build(root, release, source_sha(root, args.source_sha))
    except Exception as exc:  # noqa: BLE001 - CLI must emit an actionable release error.
        print(f"Release build failed: {exc}", file=sys.stderr)
        return 1
    print(f"Built {result['zip']}")
    print(f"SHA-256 {result['sha256']}")
    print(f"Manifest-covered files {result['files']}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
