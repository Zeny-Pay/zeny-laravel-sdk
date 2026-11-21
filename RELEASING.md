# Releasing usezeny/laravel

Packagist publishes a version as soon as it sees the tag, through its GitHub
webhook. The release workflow cannot stop that, so every check runs before the
tag is pushed.

1. Refresh the contract: `composer sync:openapi` and `composer generate`, then commit any diff.
2. Bump `VALUE` in `src/Infrastructure/Http/Version.php` (it is also the `User-Agent` version) and add the entry to `CHANGELOG.md`. Commit on `main`.
3. Run the checks locally on that commit: `composer validate --strict`, `composer lint`, `composer typecheck`, `composer check:generated`, `composer test`. With a Sandbox key, also `ZENY_E2E_KEY=sk_test_... vendor/bin/pest tests/E2eTest.php`.
4. One-time maintainer setup:
   - Decide the repository's visibility first: Packagist only reads public repositories (a private one needs Private Packagist).
   - Submit the repository URL on packagist.org under the `usezeny` vendor.
   - Connect the Packagist GitHub integration (or add the webhook Packagist shows, content type `application/json`) so tags are picked up without a token in CI.
5. Tag and push: `git tag vX.Y.Z && git push origin vX.Y.Z`. The tag must equal `Version::VALUE`.
6. Check that the Release workflow is green, then check the package page on packagist.org and install it once: `composer require usezeny/laravel:X.Y.Z`.

Notes:

- `.github/workflows/release.yml` runs the full CI (`ci.yml`), then fails unless the tagged commit is on `main`, the tag equals `Version::VALUE`, and the version is 1.0 or later and not a pre-release.
- A red Release run means a tag that must not ship already reached Packagist. Delete the tag (`git push --delete origin vX.Y.Z`), let Packagist drop the version, and release a fixed version under a new number; never move a published tag.
- Pre-1.0 versions (major `0`) and pre-releases (version contains `-`) are refused for now. Remove or loosen that step before the first 1.0 release.
- Release in step with the Node SDK, so both expose the same API surface.
