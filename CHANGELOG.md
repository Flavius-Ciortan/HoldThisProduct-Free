# Changelog

All notable changes to Hold This Product are documented here. The project follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and semantic versioning.

## [1.1.0] - 2026-08-31

### Added

- Focused lifecycle, repository, inventory, cart/order, expiration, privacy, notification, rules, locking, and service-container components.
- Stable add-on contracts for bootstrap, rules, lifecycle transitions, notifications, capabilities, repositories, and dependency notices.
- Site Health reporting for expiration scheduling and inconsistent inventory ownership.
- Deterministic release ZIP generation with a checksum.
- WordPress Coding Standards, PHP compatibility, Plugin Check, and GitHub Actions quality gates.
- Automated integration, concurrency, activation/deactivation/upgrade/uninstall, HPOS, and exact-artifact test harnesses.
- Live verification fixtures for classic checkout, block checkout, customer flow, Shop Manager access, and Administrator access.
- Reduced-motion handling for frontend and admin plugin controls.

### Changed

- Made status and inventory transitions transactional, locked, idempotent, and centrally validated.
- Made active holds explicit inventory obligations that transfer to orders at checkout.
- Linked cart items that existed before a reservation was created.
- Allowed Shop Managers to configure and operate reservations through `manage_woocommerce`.
- Centralized canonical statuses, metadata keys, labels, summaries, and cached status counts.
- Moved admin interactions from inline scripts to localized, versioned assets.
- Normalized internal filenames and release package folder naming while retaining the historical main plugin filename for upgrade compatibility.
- Clarified pending-request and active-reservation deadlines throughout the interface.
- Updated release and user documentation to describe only implemented behavior.

### Fixed

- Prevented checkout from decrementing stock a second time for a held unit.
- Prevented duplicate last-unit holds under concurrent requests.
- Prevented interrupted cancellation or expiration from leaving status and stock out of sync.
- Recreated a missing expiration schedule during normal health checks.
- Excluded expired pending requests from customer quota calculations.
- Prevented privacy erasure pagination from skipping reservation records.
- Preserved inventory obligations when a customer account is deleted.
- Represented cancelled-order reservations consistently in customer, admin, and analytics views.
- Made admin transition notices reflect the actual lifecycle result.
- Corrected settings validation feedback and product-search return types.
- Corrected pending and active frontend success messages and their visible inline timing.
- Restored stock during uninstall only when the reservation still owned a held unit.

## [1.0.1] - 2026-07-28

### Changed

- Hardened reservation authorization, validation, and settings sanitization.
- Improved compatibility declarations, translations, pagination, accessibility, privacy integration, and release metadata.

### Fixed

- Improved stock idempotency across reservation, approval, cancellation, expiration, and checkout paths.

## [1.0.0] - 2025-11-12

### Added

- Initial reservation workflow for logged-in customers and stock-managed simple products.
- Automatic expiration and stock restoration.
- My Account reservation management.
- Merchant settings, reservation dashboard, basic analytics, and product-level active reservations.
- Optional approval workflow, email notifications, modal customization, and per-customer limits.

[1.1.0]: https://github.com/Flavius-Ciortan/HoldThisProduct/compare/v1.0.1...v1.1.0
[1.0.1]: https://github.com/Flavius-Ciortan/HoldThisProduct/compare/v1.0.0...v1.0.1
[1.0.0]: https://github.com/Flavius-Ciortan/HoldThisProduct/releases/tag/v1.0.0
