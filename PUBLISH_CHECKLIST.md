# Hold This Product Release Checklist

Use this checklist for the 1.1.0 Free maintenance release and subsequent releases.

## Version and Metadata

- [x] Plugin header and `HTP_VERSION` match.
- [x] `readme.txt` stable tag matches the plugin version.
- [x] Minimum WordPress, WooCommerce, and PHP requirements match the CI matrix.
- [x] `README.md`, `USER_GUIDE.md`, `CHANGELOG.md`, and extension API describe implemented behavior only.
- [x] WordPress `Tested up to` is set to the verified current release.
- [ ] Confirm release date and final tag immediately before publishing.

## Static Quality

- [x] All PHP files pass `php -l`.
- [x] Production JavaScript passes `node --check`.
- [x] Production PHP passes WordPress Coding Standards and PHP compatibility rules.
- [x] Composer metadata validates in the CI environment.
- [x] No debug output, secrets, local credentials, or implementation plans are packaged.
- [x] Plugin Check runs against the exact release ZIP.
- [x] Remaining Plugin Check findings are advisory post-meta query warnings from the current CPT repository.

## Automated Behavior

- [x] Immediate, pending, approve, deny, cancel, expire, fulfill, and order-cancelled paths pass.
- [x] Every stock-changing transition is idempotent.
- [x] Last-unit concurrency permits one hold and rejects the competing request.
- [x] Cart-before-reservation and reservation-before-cart linkage pass.
- [x] Privacy batching does not skip records.
- [x] Customer deletion preserves unresolved inventory obligations.
- [x] Missing expiration scheduling self-heals.
- [x] Activation, deactivation, upgrade migration, uninstall, and reactivation pass.
- [x] Uninstall restores only inventory still owned by an active hold.

## Compatibility Matrix

- [x] PHP 7.4 with WordPress 6.5 and WooCommerce 8.0 is in CI.
- [x] Current PHP, WordPress, and WooCommerce are in CI.
- [x] HPOS enabled and legacy order storage both pass.
- [x] Classic checkout and Checkout Blocks pass against an installed release ZIP.
- [x] Administrator and Shop Manager access pass.
- [x] The frontend modal passes keyboard Escape, focus restoration, focus containment, live notice, and reduced-motion checks.
- [ ] Review CI results for the final release commit.

## Exact Artifact

```bash
bash bin/build-release.sh
(cd dist && sha256sum --check *.sha256)
unzip -Z1 dist/hold-this-product-*.zip
```

- [x] Repeated builds from the same commit produce the same checksum.
- [x] The archive has one `hold-this-product/` root directory.
- [x] The historical `HoldThisProduct.php` main filename is retained for upgrade compatibility.
- [x] Tests, CI, Composer dependencies, local plans, IDE files, and contributor-only documents are absent.
- [x] The archive installs and activates as `hold-this-product` on a clean WordPress site.
- [x] Integration, concurrency, lifecycle, HPOS, and Plugin Check gates run against the installed archive.

## WordPress.org Assets

- [ ] Confirm icon and banner files meet current WordPress.org dimensions and file-size guidance.
- [ ] Capture final screenshots from the exact release artifact.
- [ ] Ensure screenshot numbering and captions match `readme.txt`.
- [x] Generate or refresh `languages/hold-this-product.pot`.

## Publish

- [ ] Merge the reviewed release branch.
- [ ] Build from the clean release commit.
- [ ] Create signed/annotated tag `v1.1.0`.
- [ ] Publish GitHub release notes from `CHANGELOG.md`.
- [ ] Upload the exact checksum-verified artifact.
- [ ] Publish the matching WordPress.org SVN tag and assets if the plugin is listed there.
- [ ] Install the published package on a clean smoke-test site.
- [ ] Monitor support, Site Health reports, checkout behavior, and inventory reports after release.

Publishing, tagging, and WordPress.org submission require the repository and marketplace account holder to authorize the final release action. Do not mark those items complete based only on a local build.
