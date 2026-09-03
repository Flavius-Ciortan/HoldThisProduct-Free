# WordPress.org Submission and Release Checklist

This checklist applies to Hold This Product Free 1.1.0.

## Submission Package

- Plugin name: Hold This Product
- Requested directory slug: `hold-this-product`
- Main plugin file: `HoldThisProduct.php`
- Public source repository: the standalone Free repository
- License: GPL-3.0-or-later
- Minimum WordPress: 6.5
- Tested WordPress: 7.1
- Minimum PHP: 7.4
- Required plugin: WooCommerce
- Minimum WooCommerce: 8.0
- Tested WooCommerce: 11.0
- Release version and stable tag: 1.1.0

The final slug is assigned by the WordPress.org Plugin Review team. An exact directory page did not exist when checked on 2026-09-03, but this does not reserve or guarantee the requested slug.

## Account Prerequisites

- [ ] Create or verify the WordPress.org account that will submit and maintain the plugin.
- [ ] Decide whether to list any WordPress.org account IDs in a future `Contributors:` field. The field is intentionally omitted from this release metadata.
- [ ] Use a monitored email address on the WordPress.org account.
- [ ] Allow email from `plugins@wordpress.org` so review messages are not lost.
- [ ] Ensure every person named as a contributor has approved the GPL-3.0-or-later distribution terms.

Only WordPress.org account IDs belong in `Contributors:`. Repository usernames and display names are not interchangeable with WordPress.org IDs. WordPress.org can still publicly associate the submitting account with the plugin even when this field is omitted.

## Reviewer Overview

Use this concise description in the submission form:

> Hold This Product adds timed product reservations for logged-in WooCommerce customers. It supports immediate or merchant-approved reservations, transactional stock holds and releases, customer and merchant cancellation, expiration through WP-Cron, checkout fulfillment without a second stock reduction, My Account management, email notifications, privacy export/erasure, basic analytics, HPOS, and Cart/Checkout Blocks. Free supports one unit per reservation for published simple products using WooCommerce stock management. It has no author-operated service, remote account, telemetry, tracking, or advertisements.

Do not describe Pro-only capabilities in the Free submission.

## Source and Licensing Audit

- [x] Main plugin headers use the canonical Free repository and current requirements.
- [x] Main header, `readme.txt`, Composer metadata, and `LICENSE` consistently declare GPL-3.0-or-later.
- [x] The full GNU GPL version 3 license text is included.
- [x] Bundled source, documentation, and artwork are covered by the project license notice.
- [x] No production third-party libraries are bundled.
- [x] No minified JavaScript or CSS requires a separate human-readable source link.
- [x] No author-operated external service, telemetry, tracking, or advertising is present.
- [x] Email delivery is delegated only to the mail transport chosen by the site owner.
- [x] Stored personal data, retention, export, erasure, and uninstall behavior are disclosed.

## Readme and Header Audit

- [x] Plugin name, description, author, URLs, text domain, domain path, and license are complete.
- [x] `Requires Plugins: woocommerce` is declared in the main plugin header.
- [x] `Stable tag` matches the plugin version exactly.
- [x] The short description is below 150 characters.
- [x] No more than five relevant tags are declared.
- [x] Installation, FAQ, privacy, external-service, changelog, upgrade, and support content describe implemented Free behavior.
- [x] Screenshot captions are omitted until matching directory assets exist.
- [x] No personal or collaborator identity is declared in distributable metadata.

## Quality Gates

Run from a clean repository:

```bash
composer install
vendor/bin/phpcs
find . -type f -name '*.php' -not -path './vendor/*' -print0 | xargs -0 -n1 php -l
find assets -type f -name '*.js' -print0 | xargs -0 -n1 node --check
bash bin/build-release.sh
(cd dist && sha256sum --check hold-this-product-1.1.0.zip.sha256)
```

- [x] Static syntax and WordPress coding standards pass.
- [x] Integration, concurrency, HPOS, checkout, activation, deactivation, upgrade, uninstall, and privacy tests pass.
- [x] Plugin Check runs against the installed exact ZIP, not the development source tree.
- [x] Remaining Plugin Check findings are reviewed: only advisory custom-post-type `meta_query` performance warnings remain.
- [x] The exact ZIP installs and activates with WooCommerce on a clean site.
- [x] The ZIP has one `hold-this-product/` root and excludes tests, CI, Git metadata, Composer tooling, local plans, development ZIPs, and contributor-only documents.
- [x] The release contents contain only product-related attribution in text and image metadata.
- [x] A second clean build produces the same SHA-256 checksum.

Verified release candidate: `hold-this-product-1.1.0.zip`

SHA-256: `f69cdd5a16c1cc2b63e6b48a00b0866a4798d8f9b02fcd5795b6bbfe3ead273a`

## Initial Submission

- [ ] Sign in at https://wordpress.org/plugins/developers/add/
- [ ] Upload the checksum-verified `dist/hold-this-product-1.1.0.zip`.
- [ ] Paste the reviewer overview above and answer review questions accurately.
- [ ] Do not submit duplicate ZIPs while review is pending.
- [ ] Respond to review email from the submitting WordPress.org account.
- [ ] Make any requested changes in the public source repository and submit a newly built exact artifact.

## Directory Assets

Icons, banners, and screenshots are optional for initial submission and are not included in the release ZIP.

- [ ] Finalize `icon-128x128.png` and `icon-256x256.png`.
- [ ] Finalize `banner-772x250.png` and `banner-1544x500.png`.
- [ ] Capture screenshots from the exact release artifact.
- [ ] Add screenshot captions to `readme.txt` only when all matching files are ready.
- [ ] Upload assets to the WordPress.org SVN repository's top-level `/assets` directory after approval.

## After Approval

- [ ] Check out the assigned WordPress.org SVN repository.
- [ ] Copy the exact release contents, without the outer ZIP directory, into SVN `/trunk`.
- [ ] Copy approved directory artwork into SVN `/assets`.
- [ ] Create SVN `/tags/1.1.0` from the exact release contents.
- [ ] Commit trunk, tag, and assets with the WordPress.org account.
- [ ] Verify the public directory page, download ZIP, dependency installation, readme formatting, and support forum.
- [ ] Compare the WordPress.org download package to the approved source contents.
- [ ] Tag `v1.1.0` in GitHub and publish matching release notes.
- [ ] Install the published WordPress.org package on a clean site and repeat the smoke test.

## Ongoing Releases

Increment the plugin header version and `HTP_VERSION`, update `Stable tag`, changelog, tested versions, POT file, source tag, SVN trunk, and SVN version tag together. Never move an existing SVN version tag to different code.
