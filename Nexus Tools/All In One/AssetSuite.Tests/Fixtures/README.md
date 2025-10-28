# Test Fixtures

All fixtures are stored as JSON to avoid embedding binary blobs in the repository. The conversion tests deserialize these resources into the simplified formats implemented by the core library.

- `sprites.json` – base64 encoded RGBA tiles used to exercise the legacy `.spr` reader.
- `items.json` – sample item definitions consumed by the DAT/OTB serializers.
- `appearances.json` – small appearance set mimicking a subset of the 11+ schema.

The helper methods in `FixtureFactory` hydrate these payloads at runtime and feed them into the writers so the regression tests can perform byte-for-byte comparisons.
