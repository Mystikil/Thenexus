#!/usr/bin/env python3
"""Create a distributable archive of the Nexus server source tree."""

from __future__ import annotations

import argparse
import datetime as _dt
import subprocess
from pathlib import Path
from typing import Iterable, Set
import zipfile


EXCLUDED_NAMES: Set[str] = {
    ".git",
    "build",
    "dist",
    "releases",  # avoid nesting previously generated archives
    "__pycache__",
    ".venv",
    ".mypy_cache",
    ".pytest_cache",
}

EXCLUDED_SUFFIXES = {
    ".pyc",
    ".pyo",
    ".log",
    ".swp",
    ".swo",
}


def _should_skip(path: Path) -> bool:
    parts = path.parts
    if any(part in EXCLUDED_NAMES for part in parts):
        return True
    if path.suffix in EXCLUDED_SUFFIXES:
        return True
    return False


def iter_files(root: Path) -> Iterable[Path]:
    for path in root.rglob("*"):
        if not path.is_file():
            continue
        relative = path.relative_to(root)
        if _should_skip(relative):
            continue
        yield path


def git_info(root: Path) -> str:
    try:
        commit = subprocess.check_output(
            ["git", "rev-parse", "HEAD"], cwd=root, text=True
        ).strip()
    except Exception:
        commit = "unknown"
    try:
        branch = subprocess.check_output(
            ["git", "rev-parse", "--abbrev-ref", "HEAD"], cwd=root, text=True
        ).strip()
    except Exception:
        branch = "unknown"
    return f"commit: {commit}\nbranch: {branch}"


def build_archive(version: str, root: Path) -> Path:
    releases_dir = root / "releases"
    releases_dir.mkdir(exist_ok=True)
    archive_name = f"thenexus_server_{version}.zip"
    archive_path = releases_dir / archive_name

    if archive_path.exists():
        raise FileExistsError(f"Archive {archive_path} already exists")

    timestamp = _dt.datetime.now(_dt.UTC).replace(microsecond=0).isoformat()
    metadata = f"version: {version}\ncreated: {timestamp}\n{git_info(root)}\n"

    with zipfile.ZipFile(archive_path, "w", compression=zipfile.ZIP_DEFLATED) as zf:
        zf.writestr("RELEASE_INFO.txt", metadata)
        for file_path in iter_files(root):
            arcname = str(file_path.relative_to(root))
            zf.write(file_path, arcname)

    return archive_path


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("version", help="Version string to embed in the archive name")
    return parser.parse_args()


def main() -> None:
    args = parse_args()
    root = Path(__file__).resolve().parents[1]
    archive = build_archive(args.version, root)
    print(f"Created archive: {archive}")


if __name__ == "__main__":
    main()
