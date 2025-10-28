# Release Packages

This directory stores ready-to-share snapshots of the Nexus server source tree.

Each archive is produced with `tools/package_release.py` and contains every
feature currently available in the repository, including the XAMPP-ready `Site`
folder.

To generate a new package:

```bash
python tools/package_release.py <version>
```

The script collects all tracked files, writes them to `releases/thenexus_server_<version>.zip`,
and embeds release metadata (version, git commit, creation timestamp) in a
`RELEASE_INFO.txt` entry inside the archive.
