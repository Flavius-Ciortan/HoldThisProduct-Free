# HoldThisProduct Plugin Review

## Executive summary

- **Plugin path reviewed:** `/Users/anghelemanuel/Documents/Codex/2026-07-26/so-2/HoldMyProduct` (the directory name is `HoldMyProduct`; `HoldThisProduct.php` and its plugin header identify the plugin as Hold This Product).
- **Review date:** 2026-07-28 (Europe/Bucharest).
- **Commit and working-tree state:** `3d654c3d9ba963d26a742e8420886d0a408d9ec9` (`Merge pull request #30 from Flavius-Ciortan/flv--code-cleanup`, 2026-02-10). The tracked tree was clean before this report was created. An ignored `.vscode/` directory already existed and was not changed.
- **Overall risk assessment:** **High.** The plugin does not expose a confirmed unauthenticated takeover path, but its central inventory workflow is unsafe for production: reserving the last unit makes it unavailable even to the reserver, successful purchases normally reduce inventory twice, and concurrent state changes are not atomic.
- **Counts by severity:** Critical 0; High 3; Medium 12; Low 6; Informational 2 (23 total).
- **Most important conclusions:** The stock model must be redesigned and concurrency-tested before release. Order fulfillment needs a durable reservation-to-order association. Expiration, privacy, compatibility metadata, accessibility, and translation support also fall short of the behavior and readiness claimed by the documentation.

## Scope and methodology

- Reviewed all plugin PHP, JavaScript, CSS, templates, Markdown documentation, `readme.txt`, `.gitignore`, WordPress.org asset notes, and the three existing ignored VS Code/Playground JSON files. Inspected the five PNG assets by type, dimensions, and size. There are no bundled third-party source libraries or dependency directories.
- Traced the bootstrap, dependency check, custom post type, option registration, AJAX handlers, WooCommerce account endpoint, order-status hooks, stock mutations, expiration path, admin settings/reservation/analytics pages, email hooks, templates, and browser behavior.
- Architecture: one bootstrap singleton creates `HTP_Reservations`, `HTP_Email_Manager`, admin classes on admin requests, and `HTP_Frontend` on frontend requests. Reservations are `htp_reservation` posts with `_htp_*` post meta. There are no REST routes, shortcodes, blocks, WP-CLI commands, scheduled cron events, custom tables, roles, capabilities, uploads, or remote application APIs. The only remote browser resource is optional Google Fonts.
- Exclusions: image pixels were not subjected to OCR or accessibility semantics because the images contain no executable behavior; their packaging and runtime uses were inspected. There are no `vendor/` or `node_modules/` contents to exclude.
- Local checks: complete file inventory; hook/input/output/SQL/remote-resource searches; JSON parsing with `jq`; Git state and history checks; image metadata and size checks. PHP syntax checks, WordPress integration tests, WPCS, PHPStan/Psalm, ESLint, and WP-CLI checks could not be run because those executables/configurations are absent. No packages were installed.
- Limitations: no runnable WordPress/WooCommerce site or test suite was present, so runtime behavior was verified by code tracing rather than browser/database execution. Dependency-vulnerability auditing was inapplicable because there are no lockfiles or bundled libraries. Color contrast was not measured against every possible theme or administrator-selected color.

## Findings summary

| ID | Severity | Category | Title | Location | Status |
|---|---|---|---|---|---|
| HTP-001 | High | Correctness | A reserved last unit cannot be purchased by its reserver | `includes/class-htp-reservations.php:145` | Confirmed |
| HTP-002 | High | Correctness | A fulfilled purchase normally decrements stock twice | `includes/class-htp-reservations.php:145`, `:563` | Confirmed |
| HTP-003 | High | Correctness | Reservation and stock transitions are non-atomic | `includes/class-htp-reservations.php:109`, `:141`, `:329`, `:594` | Confirmed |
| HTP-004 | Medium | Correctness | Fulfillment is correlated by mutable email/product and misses order states | `includes/class-htp-reservations.php:563` | Confirmed |
| HTP-005 | Medium | Correctness | Pending approvals never expire and permanently consume limits | `includes/class-htp-reservations.php:175`, `:302`, `:670` | Confirmed |
| HTP-006 | Medium | Performance | Expiration runs on requests, processes only 100, and has unreliable throttling | `includes/class-htp-reservations.php:23`, `:302` | Confirmed |
| HTP-007 | Medium | Correctness | Mixed timestamp models can expire stock early or late | `includes/class-htp-reservations.php:162`; `includes/admin/class-htp-admin-analytics.php:124` | Confirmed |
| HTP-008 | Medium | Security | Admin cancel/delete AJAX accepts non-reservation post IDs | `includes/admin/class-htp-admin-reservations.php:303`, `:329` | Confirmed |
| HTP-009 | Medium | Correctness | Settings are stored without schema, sanitization, or server-side bounds | `includes/admin/class-htp-admin.php:69` | Confirmed |
| HTP-010 | Medium | Security | Google Fonts discloses visitor data contrary to the privacy statement | `templates/modal_template.php:43`; `readme.txt:169` | Confirmed |
| HTP-011 | Medium | Security | Personal data has no uninstall cleanup, exporter, or eraser | `includes/class-htp-reservations.php:179`; plugin root | Confirmed |
| HTP-012 | Medium | Accessibility | Reservation modal lacks dialog semantics and focus management | `templates/modal_template.php:74`; `assets/js/holdthisproduct.js:3` | Confirmed |
| HTP-013 | Medium | Performance | Reservation queries scale poorly and admin results are silently capped | `includes/class-htp-reservations.php:249`, `:670`; admin list/analytics | Confirmed |
| HTP-014 | Medium | Compatibility | Required WordPress/PHP/WooCommerce metadata is absent from plugin header | `HoldThisProduct.php:3`; `readme.txt:4` | Confirmed |
| HTP-015 | Medium | Internationalization | Most user-facing and email text is not translatable | PHP templates, admin classes, email manager, JavaScript | Confirmed |
| HTP-016 | Low | Performance | Frontend assets load site-wide and CSS changes unrelated controls | `includes/frontend/class-htp-frontend.php:79`; `assets/css/style.css:41` | Confirmed |
| HTP-017 | Low | Correctness | Customer-name search breaks status filtering; list has no pagination | `includes/admin/class-htp-admin-reservations.php:412` | Confirmed |
| HTP-018 | Low | Correctness | The Reserve button is shown for products AJAX will reject | `includes/class-htp-reservations.php:216`; `templates/form_template.php:27` | Confirmed |
| HTP-019 | Low | Security | Cancellation is a state-changing GET action | `includes/class-htp-reservations.php:540`; account template `:116` | Confirmed |
| HTP-020 | Low | Maintainability | Frontend construction registers a second full reservations hook set | `includes/frontend/class-htp-frontend.php:28` | Confirmed |
| HTP-021 | Low | Maintainability | Published behavior and readiness claims contradict the implementation | `readme.txt`, `README.md`, `USER_GUIDE.md`, `CHANGELOG.md`, `PUBLISH_CHECKLIST.md` | Confirmed |
| HTP-022 | Informational | Testing | No automated test or quality-tool configuration exists | plugin root | Confirmed |
| HTP-023 | Informational | Maintainability | Dead and duplicated implementation paths increase drift | reservations/admin/analytics classes | Confirmed |

## Detailed findings

### HTP-001 — A reserved last unit cannot be purchased by its reserver

- **Severity:** High
- **Category:** Correctness
- **Confidence:** High
- **Location:** `includes/class-htp-reservations.php:145-147`; `templates/myaccount/my-reservations.php:115-119,186-189`
- **Affected component:** Customer reservation and checkout flow.
- **Description:** Creating an active reservation immediately reduces WooCommerce's saleable stock, but the plugin never grants the reservation owner a private stock entitlement or bypasses WooCommerce's stock validation. At quantity one, reservation changes stock to zero and the supplied Add to Cart action is rejected as out of stock. With more stock, the remaining stock remains available to everyone; the held unit is not associated with its owner.
- **Evidence:** `set_stock_quantity( max( 0, $stock_quantity - 1 ) )` is followed only by an ordinary `?add-to-cart=<id>` link. No cart/checkout stock filter or reservation ownership check exists anywhere in the plugin.
- **Impact:** The headline use case fails for scarce products. A customer can lock the last item yet be unable to buy it; meanwhile the plugin cannot guarantee any specific unit to that customer.
- **Trigger or reproduction:** Set managed stock to 1, reserve as a logged-in customer, then use My Account > Reserved products > Add to Cart. WooCommerce sees stock 0.
- **Recommended remediation:** Choose one coherent model: use WooCommerce's cart/order reservation facilities, or maintain separate held quantity and explicitly authorize only the owner at cart/checkout. Do not represent a temporary hold as an ownerless permanent stock decrement.
- **Verification after fixing:** Integration-test stock 1 and stock >1 with owner/non-owner carts, simultaneous carts, expiration, cancellation, and checkout failure.
- **References:** [WooCommerce stock functions](https://woocommerce.github.io/code-reference/files/woocommerce-includes-wc-stock-functions.html)

### HTP-002 — A fulfilled purchase normally decrements stock twice

- **Severity:** High
- **Category:** Correctness
- **Confidence:** High
- **Location:** `includes/class-htp-reservations.php:145-147,563-587`
- **Affected component:** WooCommerce inventory after checkout.
- **Description:** The plugin decrements stock once at reservation. WooCommerce independently reduces order stock during its paid/on-hold lifecycle. The fulfillment callback merely changes `_htp_status` to `fulfilled` and explicitly does not restore or reconcile the earlier decrement.
- **Evidence:** Reservation stock is set to `$stock_quantity - 1`; fulfillment says `// Don't restore stock since it was purchased`. WooCommerce's stock reducer tracks order reduction separately and does not know the reservation decrement belongs to that order.
- **Impact:** Each fulfilled reserved unit generally removes two units from inventory, causing false out-of-stock states, lost sales, and inaccurate accounting.
- **Trigger or reproduction:** Start at stock 2, reserve one (stock 1), then complete an order for one (WooCommerce stock 0). The correct physical balance is 1.
- **Recommended remediation:** Associate a reservation with a cart/order and atomically transfer the hold into the order reduction, or release the hold immediately before WooCommerce performs its single canonical reduction. Preserve WooCommerce's order `_order_stock_reduced` semantics.
- **Verification after fixing:** Assert stock deltas for processing, completed, on-hold, cancelled, failed, refunded, and payment-retry transitions, including duplicate webhooks.
- **References:** [WooCommerce stock functions](https://woocommerce.github.io/code-reference/files/woocommerce-includes-wc-stock-functions.html), [WooCommerce order-state stock guidance](https://developer.woocommerce.com/2026/07/17/failed-order-stock-update/)

### HTP-003 — Reservation and stock transitions are non-atomic

- **Severity:** High
- **Category:** Correctness
- **Confidence:** High
- **Location:** `includes/class-htp-reservations.php:109-147,329-369,594-627,670-745`
- **Affected component:** Reservation limits, inventory, approval, cancellation, and expiration.
- **Description:** Each workflow reads status/stock, performs separate post/meta writes, then sets an absolute stock value. There is no lock, compare-and-swap, transaction, unique constraint, or atomic stock `increase`/`decrease`. Limit and duplicate checks are also separated from insertion.
- **Evidence:** Two requests can both read stock 1, both pass the user/stock checks, both insert active reservations, and both write stock 0. Conversely, concurrent expiration/cancellation can both read `active` and each restore one unit. Approval has the same check-then-act race.
- **Impact:** Concurrent requests can create more holds than stock, bypass per-user limits, approve the same scarce unit twice, or inflate inventory through duplicate restoration.
- **Trigger or reproduction:** Send two authenticated reservation AJAX requests in parallel against stock 1, or invoke expiration and admin cancellation concurrently for one active reservation.
- **Recommended remediation:** Implement an atomic state machine. Use conditional database updates/locking for reservation status, an enforceable uniqueness/idempotency key, and WooCommerce's atomic stock operation (`wc_update_product_stock( ..., 'decrease'/'increase' )`) with rollback on failure.
- **Verification after fixing:** Add parallel-request tests (at least 20 contenders for stock 1), duplicate callbacks, and fault injection between every state and stock write.
- **References:** [WooCommerce `wc_update_product_stock()`](https://woocommerce.github.io/code-reference/files/woocommerce-includes-wc-stock-functions.html)

### HTP-004 — Fulfillment is correlated by mutable email/product and misses order states

- **Severity:** Medium
- **Category:** Correctness
- **Confidence:** High
- **Location:** `includes/class-htp-reservations.php:41-42,563-588`
- **Affected component:** Order-to-reservation reconciliation.
- **Description:** Fulfillment selects the first active reservation matching billing email and parent product ID. It does not require the reservation author to equal the order customer, store a reservation/order ID, check expiration, match variation ID, account for item quantity, or use a deterministic ordering. Only `processing` and `completed` are observed.
- **Evidence:** The query has only `_htp_status`, `_htp_product_id`, and `_htp_email`, with `posts_per_page => 1`. WooCommerce item `get_product_id()` returns the parent product rather than `get_variation_id()`. On-hold orders are not handled.
- **Impact:** An order can fulfill the wrong person's or wrong variant's hold. A payment method that leaves an order on hold can retain an active reservation until expiration, at which point stock is restored despite an order already owning it.
- **Trigger or reproduction:** Checkout using another account's billing email, place two historical matching reservations, purchase a variation, or use a payment method producing `on-hold`.
- **Recommended remediation:** Persist reservation IDs into cart item and order item meta, validate ownership and exact product/variation/quantity, and reconcile all WooCommerce stock-affecting status paths idempotently.
- **Verification after fixing:** Test shared emails, guest/admin-created orders, variations, quantities, BACS/COD/on-hold, asynchronous payment, cancellation, failed payment, and webhook replay.
- **References:** [WooCommerce order-state stock guidance](https://developer.woocommerce.com/2026/07/17/failed-order-stock-update/)

### HTP-005 — Pending approvals never expire and permanently consume limits

- **Severity:** Medium
- **Category:** Correctness
- **Confidence:** High
- **Location:** `includes/class-htp-reservations.php:160-184,308-317,670-705,718-746`
- **Affected component:** Admin-approval workflow.
- **Description:** Creation assigns every pending request an expiry timestamp, but expiration queries only `active`. Limit and duplicate logic counts every `pending_approval` record without considering its expiry.
- **Evidence:** `expire_old_reservations()` filters `_htp_status = active`; `count_open_reservations()` increments pending requests unconditionally.
- **Impact:** An unattended request blocks that customer's quota and product indefinitely, requiring manual approve/deny/cancel. Pending records accumulate forever.
- **Trigger or reproduction:** Enable approval and max reservations 1, submit a request, wait beyond duration, then try another reservation.
- **Recommended remediation:** Define a pending timeout/status transition and process it independently without stock restoration (pending does not hold stock). Exclude stale pending requests from open/duplicate checks.
- **Verification after fixing:** Test pending timeout, late approval race, cancellation, denial, notifications, and quota release.
- **References:** [WordPress cron](https://developer.wordpress.org/plugins/cron/)

### HTP-006 — Expiration runs on requests, processes only 100, and has unreliable throttling

- **Severity:** Medium
- **Category:** Performance
- **Confidence:** High
- **Location:** `includes/class-htp-reservations.php:20-24,302-324`
- **Affected component:** Automatic expiration.
- **Description:** Expiration is attached to every `init`, not a scheduled event. It fetches at most 100 expired reservations with no deterministic ordering, then uses `wp_cache_*` as a five-minute throttle. On default non-persistent object caching the throttle lasts only the request; with persistent caching, a backlog advances only 100 per five minutes.
- **Evidence:** `add_action( 'init', ...expire_old_reservations )`, `posts_per_page => 100`, and `wp_cache_set(..., 300)`; there is no `wp_schedule_event` or cron hook.
- **Impact:** Every request can incur a meta query and writes/emails, while large backlogs leave stock held long after expiry. Expiration mail timing is traffic-dependent. The user guide incorrectly diagnoses WP-Cron even though the plugin schedules none.
- **Trigger or reproduction:** Create 201 expired active records under persistent object cache; only 100 are processed, then another 100 after the cache interval.
- **Recommended remediation:** Schedule an idempotent WP-Cron/Action Scheduler job, process deterministic batches until drained within bounded runtime, use a real lock, and unschedule on deactivation.
- **Verification after fixing:** Test no-traffic catch-up, 10k records, overlapping workers, persistent/non-persistent caches, and deactivation cleanup.
- **References:** [Scheduling WP-Cron events](https://developer.wordpress.org/plugins/cron/scheduling-wp-cron-events/)

### HTP-007 — Mixed timestamp models can expire stock early or late

- **Severity:** Medium
- **Category:** Correctness
- **Confidence:** High
- **Location:** `includes/class-htp-reservations.php:162,257,315,469,515`; `includes/admin/class-htp-admin-analytics.php:124-131`
- **Affected component:** Expiration and displayed machine-readable dates.
- **Description:** Reservation times use WordPress's legacy offset-adjusted `current_time( 'timestamp' )`. PHP comparisons use the same model, but analytics compares stored values to SQL `UNIX_TIMESTAMP()`, a real epoch in the database session timezone. HTML `datetime` values use PHP `date()` on the adjusted value, adding server-timezone ambiguity.
- **Evidence:** Analytics can invoke `expire_reservation()` based on `CAST(meta_value AS UNSIGNED) < UNIX_TIMESTAMP()` while the core code compares against `current_time( 'timestamp' )`.
- **Impact:** Opening Analytics can release stock hours early or late on non-UTC sites, and machine-readable dates can disagree with visible dates.
- **Trigger or reproduction:** Use a negative or positive non-UTC site timezone, create a near-expiry reservation, then open Analytics around the nominal expiry.
- **Recommended remediation:** Store real UTC epochs (`time()`/`current_datetime()->getTimestamp()`), compare in one time model, and format with `wp_date()` using the site timezone.
- **Verification after fixing:** Test UTC, UTC-8, UTC+14, DST boundaries, PHP/DB timezone differences, and legacy-record migration.
- **References:** [WordPress date/time best practices (`wp_date`)](https://developer.wordpress.org/reference/functions/wp_date/)

### HTP-008 — Admin cancel/delete AJAX accepts non-reservation post IDs

- **Severity:** Medium
- **Category:** Security
- **Confidence:** High
- **Location:** `includes/admin/class-htp-admin-reservations.php:303-351`
- **Affected component:** Privileged AJAX actions.
- **Description:** Approve and deny validate `post_type`, but cancel and delete do not. A user with `manage_options` and the page nonce can substitute any post ID. Delete permanently calls `wp_delete_post( $id, true )` when the arbitrary post lacks `_htp_status = active`; cancel can rewrite arbitrary `_htp_*` metadata and follow forged product metadata into a stock change.
- **Evidence:** Lines 366-369 and 397-400 contain the missing validation for approve/deny; there is no equivalent at lines 303-351.
- **Impact:** On sites with custom capability assignments, a role granted settings access but not post deletion can use this handler as a confused deputy to permanently delete posts. An accidental/tampered request can target unrelated content.
- **Trigger or reproduction:** From the reservations screen, reuse the valid delete nonce but replace `reservation_id` with a normal post ID that has no `_htp_status`.
- **Recommended remediation:** Require `get_post_type( $id ) === 'htp_reservation'`, validate allowed source/target status, and use a dedicated capability mapped to the reservation object before mutation.
- **Verification after fixing:** Attempt each AJAX action against posts, pages, products, revisions, trash, missing IDs, and each reservation status under custom roles.
- **References:** [WordPress.org common security issues](https://developer.wordpress.org/plugins/wordpress-org/common-issues/)

### HTP-009 — Settings are stored without schema, sanitization, or server-side bounds

- **Severity:** Medium
- **Category:** Correctness
- **Confidence:** High
- **Location:** `includes/admin/class-htp-admin.php:69-70,101-176,258-320`; `templates/modal_template.php:24-66`
- **Affected component:** Plugin options and frontend modal CSS.
- **Description:** `register_setting()` has no type/default/sanitize callback. HTML `min`, `max`, input type, and select choices are client hints only. Crafted `options.php` submissions can store wrong shapes, unlimited durations/limits, invalid colors, or arbitrary CSS fragments in `font_family`/colors.
- **Evidence:** `register_setting( ..., 'holdthisproduct_options' );` accepts the submitted array unchanged. Core later assumes nested arrays and the template concatenates stored strings into a style attribute.
- **Impact:** Authorized but malformed requests can cause PHP warnings/type failures, reservations that last unexpectedly long, unbounded queries/limits, broken UI, or injected CSS declarations for all visitors to product pages.
- **Trigger or reproduction:** Submit `reservation_duration=999999999`, a scalar in place of `popup_customization_logged_in`, or a font value containing `;property:value` directly to `options.php` with a valid settings nonce.
- **Recommended remediation:** Add one strict sanitize callback with an allowlisted schema, booleans, bounded integers (1-168), validated hex colors, and an allowlisted font identifier; merge defaults safely.
- **Verification after fixing:** Unit-test missing, scalar, nested, duplicated, oversized, negative, malformed color/font, and unknown keys.
- **References:** [WordPress `register_setting()`](https://developer.wordpress.org/reference/functions/register_setting/)

### HTP-010 — Google Fonts discloses visitor data contrary to the privacy statement

- **Severity:** Medium
- **Category:** Security
- **Confidence:** High
- **Location:** `templates/modal_template.php:43-53,70-72`; `readme.txt:169-177`
- **Affected component:** Frontend privacy and external integrations.
- **Description:** Choosing one of four font options inserts a stylesheet from `fonts.googleapis.com` on product pages. This sends visitor network/browser data to Google without an in-plugin notice or consent mechanism. The readme says no data is shared with third parties.
- **Evidence:** The remote `<link>` is emitted whenever customization is enabled with Roboto, Open Sans, Lato, or Montserrat.
- **Impact:** Store visitors are unexpectedly connected to a third party, creating privacy/compliance and documentation risk.
- **Trigger or reproduction:** Enable popup customization, select a Google font, visit a product page, and inspect network requests.
- **Recommended remediation:** Bundle appropriately licensed font assets or use system fonts; otherwise make the external request explicit, consent-aware, and accurately documented.
- **Verification after fixing:** Network-test logged-in/out product pages under every font setting and verify privacy-policy text.
- **References:** [WordPress plugin privacy guidance](https://developer.wordpress.org/plugins/privacy/)

### HTP-011 — Personal data has no uninstall cleanup, exporter, or eraser

- **Severity:** Medium
- **Category:** Security
- **Confidence:** High
- **Location:** `includes/class-htp-reservations.php:164-199`; plugin root (no `uninstall.php` or uninstall hook); all bootstrap hooks (no privacy hooks)
- **Affected component:** Stored customer email/user/reservation data.
- **Description:** The plugin stores email addresses and user associations in posts/meta indefinitely. It provides neither uninstall cleanup nor WordPress personal-data exporter/eraser/privacy-policy integration.
- **Evidence:** `_htp_email` is persisted, while the only lifecycle cleanup is rewrite flushing on deactivation. Repository search found no `wp_privacy_personal_data_exporters`, `wp_privacy_personal_data_erasers`, `wp_add_privacy_policy_content`, `register_uninstall_hook`, or `uninstall.php`.
- **Impact:** Deleting the plugin leaves personal and operational data behind; site owners cannot fulfill export/erasure requests through core tools and are not guided about retention.
- **Trigger or reproduction:** Create a reservation, delete the plugin, or run Tools > Export/Erase Personal Data for its email; the reservation data remains/is omitted.
- **Recommended remediation:** Define retention policy, add exporter/eraser and privacy-policy text, handle deleted users, and provide an explicit uninstall data-retention choice with safe multisite cleanup.
- **Verification after fixing:** Test export/erase pagination, active versus historical reservations, user deletion, single-site/network uninstall, and administrator opt-in retention.
- **References:** [WordPress privacy hooks](https://developer.wordpress.org/plugins/privacy/privacy-related-options-hooks-and-capabilities/), [uninstall methods](https://developer.wordpress.org/plugins/plugin-basics/uninstall-methods/)

### HTP-012 — Reservation modal lacks dialog semantics and focus management

- **Severity:** Medium
- **Category:** Accessibility
- **Confidence:** High
- **Location:** `templates/modal_template.php:74-94`; `assets/js/holdthisproduct.js:3-27`
- **Affected component:** Frontend reservation modal.
- **Description:** The overlay is a plain titled `div`, with no `role="dialog"`, `aria-modal`, programmatic label/description, close control, focus move, focus trap, background inerting, or focus restoration. Show/hide only changes CSS display. Escape hides it but leaves keyboard focus wherever it was.
- **Evidence:** Opening calls `$('#reservation-modal').show()` and closing calls `.hide()`; no focus APIs or ARIA state are used.
- **Impact:** Keyboard and screen-reader users may not know a dialog opened, can tab into content behind it, and lose context when it closes.
- **Trigger or reproduction:** Navigate with keyboard/screen reader, activate Reserve, then tab and close via Escape/overlay.
- **Recommended remediation:** Implement an accessible dialog pattern: labelled semantic dialog, explicit close button, initial focus, trapped tab sequence, Escape, inert/background handling, and restored opener focus. Replace alerts with an announced status region.
- **Verification after fixing:** Keyboard-only and NVDA/JAWS/VoiceOver tests, zoom/reflow, Escape, overlay, failed/successful submission, and reduced motion.
- **References:** [WAI-ARIA modal dialog pattern](https://www.w3.org/WAI/ARIA/apg/patterns/dialog-modal/)

### HTP-013 — Reservation queries scale poorly and admin results are silently capped

- **Severity:** Medium
- **Category:** Performance
- **Confidence:** High
- **Location:** `includes/class-htp-reservations.php:249-269,431-444,670-705`; `includes/admin/class-htp-admin-reservations.php:27,412-491,527-584`; `includes/admin/class-htp-admin-analytics.php:163-237,242-295`
- **Affected component:** Limits, account/admin pages, analytics.
- **Description:** Count methods load all matching post IDs and call `count()` rather than using a count query; `count_open_reservations()` then performs two meta reads per record. Admin list loads only 100 with no paging. Row renderers issue repeated meta/product/user calls, and analytics executes seven near-identical count queries plus N+1 recent-row reads.
- **Evidence:** `posts_per_page => -1` occurs in both count paths; the admin list hard-codes 100 and reports `count( $reservations )` as the total displayed number.
- **Impact:** Large stores incur expensive postmeta joins, memory use, and N+1 cache misses on normal requests, while administrators cannot see/manage records after the newest 100.
- **Trigger or reproduction:** Populate tens of thousands of reservations, use a high per-user limit, then load product/account/admin/analytics pages with a cold object cache.
- **Recommended remediation:** Use bounded count queries, indexed/custom storage appropriate to lifecycle queries, pagination, bulk meta priming, cached aggregated analytics with invalidation, and query monitoring.
- **Verification after fixing:** Benchmark 1k/10k/100k records with cold/warm caches; assert pagination totals and query counts.
- **References:** [WordPress `WP_Query` pagination parameters](https://developer.wordpress.org/reference/classes/wp_query/)

### HTP-014 — Required WordPress/PHP/WooCommerce metadata is absent from plugin header

- **Severity:** Medium
- **Category:** Compatibility
- **Confidence:** High
- **Location:** `HoldThisProduct.php:3-14`; `readme.txt:4-6,60-63`
- **Affected component:** Installation, activation, and WordPress.org packaging.
- **Description:** Requirements are declared only in `readme.txt`; the main plugin header omits `Requires at least`, `Requires PHP`, and `Requires Plugins: woocommerce`. WordPress.org has parsed WordPress/PHP requirements from the main file since WordPress 5.8. The runtime dependency check merely returns during activation and does not prevent activation.
- **Evidence:** The header contains name/version/text domain/license but no requirement fields. Code uses PHP 7 syntax (`??`) and WooCommerce classes/functions.
- **Impact:** Unsupported sites can be offered/activate the plugin without correct compatibility/dependency gating, leading to fatal parse/class failures or a silently inert plugin.
- **Trigger or reproduction:** Package the current plugin for WordPress.org or activate without WooCommerce/on a PHP version below the documented 7.4 requirement.
- **Recommended remediation:** Add the three canonical header fields, keep readme metadata consistent, and retain a graceful runtime guard for non-directory installs.
- **Verification after fixing:** Validate the ZIP/header with official tools and test activation with missing/inactive WooCommerce and minimum PHP/WordPress versions.
- **References:** [Plugin header requirements](https://developer.wordpress.org/plugins/plugin-basics/header-requirements/), [WordPress.org readme parsing](https://developer.wordpress.org/plugins/wordpress-org/how-your-readme-txt-works/)

### HTP-015 — Most user-facing and email text is not translatable

- **Severity:** Medium
- **Category:** Internationalization
- **Confidence:** High
- **Location:** `includes/class-htp-reservations.php:49-55,89-152,481-485,594-659`; `includes/class-htp-email-manager.php:50-167`; `includes/admin/class-htp-admin.php:35-329`; `includes/admin/class-htp-admin-reservations.php:22-409,527-630`; `includes/admin/class-htp-admin-analytics.php:28-297`; `includes/frontend/class-htp-frontend.php:65-67`; `templates/modal_template.php:74-92`; `assets/js/holdthisproduct.js:7-59`
- **Affected component:** Frontend, admin, AJAX, and emails.
- **Description:** Large portions of visible UI, JavaScript alerts, server responses, status/time phrases, menu/page titles, and all email content are hard-coded English. Some translated output uses bare `__()` directly in HTML rather than escaped translation functions, and placeholder strings lack translator comments. There is no POT/languages artifact despite the documented `Domain Path` and “fully translation-ready” claim.
- **Evidence:** Representative strings include `Invalid product ID.`, `Processing...`, `Reservation Confirmed: %s`, the complete email bodies, and all analytics headings.
- **Impact:** Stores cannot provide a consistent localized experience; AJAX/email content remains English even when surrounding WordPress/WooCommerce is translated. Unescaped third-party translation output is avoidable hardening debt.
- **Trigger or reproduction:** Switch the site to a non-English locale and exercise reserve, error, email, admin, and analytics flows.
- **Recommended remediation:** Wrap all PHP strings with context-appropriate gettext/escaping, use plural functions and translator comments, expose JS strings through `wp_set_script_translations` or localized data, and generate a POT during release.
- **Verification after fixing:** Run `wp i18n make-pot`, inspect extraction warnings, install a test locale, and cover singular/plural, AJAX, email, and JavaScript paths.
- **References:** [Internationalizing plugins](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/), [internationalization security](https://developer.wordpress.org/plugins/internationalization/security/)

### HTP-016 — Frontend assets load site-wide and CSS changes unrelated controls

- **Severity:** Low
- **Category:** Performance
- **Confidence:** High
- **Location:** `includes/frontend/class-htp-frontend.php:37,79-100`; `assets/css/style.css:41-45,339-350,475-479`
- **Affected component:** Every public page.
- **Description:** CSS, jQuery, plugin JS, AJAX URL, and a per-session nonce are enqueued on all frontend requests even though UI renders only on product/account pages. CSS contains unscoped selectors such as `input[type="checkbox"]`, `.description`, and WooCommerce/block selectors.
- **Evidence:** `enqueue_frontend_assets()` has no `is_product()`/account condition and `style.css:41` styles every checkbox.
- **Impact:** Unrelated pages pay transfer/parse/cache-variation cost and themes/plugins can suffer changed checkbox/description/layout styling.
- **Trigger or reproduction:** Visit a blog post containing a checkbox or `.description` element with the plugin active.
- **Recommended remediation:** Enqueue only where used and scope every rule under an HTP root class. Avoid creating AJAX nonces on pages without the feature.
- **Verification after fixing:** Asset/network and visual-regression tests on posts, checkout, cart, product, account, admin bar, and common form plugins.
- **References:** [WordPress enqueueing scripts/styles](https://developer.wordpress.org/plugins/javascript/enqueuing/)

### HTP-017 — Customer-name search breaks status filtering; list has no pagination

- **Severity:** Low
- **Category:** Correctness
- **Confidence:** High
- **Location:** `includes/admin/class-htp-admin-reservations.php:23-27,412-491`
- **Affected component:** Admin reservation search/list.
- **Description:** When customer-name search is selected, the function sets the entire `meta_query` relation to `OR`. If a status filter already exists, results match status OR first name OR surname, not status AND (first name OR surname). Separately, the list silently returns only 100 newest records.
- **Evidence:** `$meta_query['relation'] = 'OR'` is added at line 464 to the same array that may already contain `_htp_status`; `posts_per_page` is 100 with no `paged` input/UI.
- **Impact:** Admins receive incorrect filtered results and cannot reach older matching records, which can cause incorrect operational decisions.
- **Trigger or reproduction:** Filter Active plus a customer name belonging only to a Cancelled reservation; unrelated Active records and the matching Cancelled record both appear.
- **Recommended remediation:** Nest an OR name group inside a top-level AND group and implement `WP_List_Table`-style pagination with a real total.
- **Verification after fixing:** Test all status/search combinations, empty/partial names, >100 matches, and page boundaries.
- **References:** [WordPress `WP_Meta_Query`](https://developer.wordpress.org/reference/classes/wp_meta_query/)

### HTP-018 — The Reserve button is shown for products AJAX will reject

- **Severity:** Low
- **Category:** Correctness
- **Confidence:** High
- **Location:** `includes/class-htp-reservations.php:99-111,216-228`; `templates/form_template.php:22-39`; `includes/frontend/class-htp-frontend.php:62-73`
- **Affected component:** Product-page UX.
- **Description:** Display eligibility checks only global enablement and login. It does not verify that the object is a supported simple product, published/purchasable, stock-managed, in stock, or has positive quantity. AJAX performs only some of those checks after the customer opens and submits the modal.
- **Evidence:** `is_product_reservable()` returns `true` unconditionally after global/login checks, while AJAX rejects unmanaged or zero stock.
- **Impact:** Customers are offered an action that predictably fails on out-of-stock, variable, external, grouped, and unmanaged-stock products, contradicting the documented support boundary.
- **Trigger or reproduction:** Log in and visit an out-of-stock or unmanaged-stock product with reservations globally enabled.
- **Recommended remediation:** Centralize one side-effect-free eligibility validator shared by rendering and mutation, including exact product-type/variation rules and user-facing reason codes.
- **Verification after fixing:** Matrix-test product type, status, visibility, stock-management/in-stock/backorder values, and selected variations.
- **References:** [WooCommerce product code reference](https://woocommerce.github.io/code-reference/classes/WC-Product.html)

### HTP-019 — Cancellation is a state-changing GET action

- **Severity:** Low
- **Category:** Security
- **Confidence:** High
- **Location:** `templates/myaccount/my-reservations.php:115-119,191-194`; `includes/class-htp-reservations.php:540-557`
- **Affected component:** Customer cancellation.
- **Description:** Cancellation is a nonce-bearing URL and runs on `template_redirect`. The nonce and author check prevent ordinary CSRF/IDOR, but GET is expected to be safe and can be followed by link scanners, browser prefetch, history replays, or assistive tooling.
- **Evidence:** An `<a href="...?htp_cancel_res=...&_wpnonce=...">` directly triggers `cancel_reservation()`.
- **Impact:** A valid link can cancel a hold without a deliberate form submission; nonce lifetime permits replay, though repeat restoration is avoided by the status check inside cancellation.
- **Trigger or reproduction:** Copy a live cancel URL and have an authenticated browser/prefetcher request it before the user confirms the inline JavaScript prompt.
- **Recommended remediation:** Use a POST form/AJAX action with nonce, ownership, post-type, and allowed-state validation, then show an accessible confirmation/result notice.
- **Verification after fixing:** Verify GET is inert and POST handles replay, expired nonce, wrong owner/type/status, disabled JS, and back/refresh.
- **References:** [WordPress nonces](https://developer.wordpress.org/apis/security/nonces/)

### HTP-020 — Frontend construction registers a second full reservations hook set

- **Severity:** Low
- **Category:** Maintainability
- **Confidence:** High
- **Location:** `HoldThisProduct.php:145-158`; `includes/frontend/class-htp-frontend.php:28-30`; `includes/class-htp-reservations.php:13-43`
- **Affected component:** Frontend hook lifecycle.
- **Description:** The bootstrap creates one `HTP_Reservations`, then `HTP_Frontend` creates another instead of receiving the existing service. Every hook in `HTP_Reservations::init()` is registered twice on frontend requests, including init, account filters, template redirect, and order-status callbacks.
- **Evidence:** `new HTP_Reservations()` occurs in both bootstrap and frontend constructor. Each instance has distinct callback identity, so WordPress does not deduplicate them.
- **Impact:** Queries/filter work is duplicated and behavior becomes order-dependent. Current redirects/cache/status checks mask some duplicate side effects, making future changes fragile.
- **Trigger or reproduction:** Inspect `$wp_filter` on a product/account frontend request; each reservations callback is present twice.
- **Recommended remediation:** Construct one reservations service and inject it into frontend/admin consumers; make hook registration explicit and idempotent.
- **Verification after fixing:** Assert one callback per hook in frontend, admin, AJAX, REST, cron, and checkout contexts.
- **References:** [WordPress actions](https://developer.wordpress.org/plugins/hooks/actions/)

### HTP-021 — Published behavior and readiness claims contradict the implementation

- **Severity:** Low
- **Category:** Maintainability
- **Confidence:** High
- **Location:** `README.md:9,24,31`; `readme.txt:15,24-28,96,112,128,169-177`; `CHANGELOG.md:24,39,42,52,67,71-77`; `USER_GUIDE.md:108-117,154-175,216-237,271-285,296,341-350`; `PUBLISH_CHECKLIST.md:329-345`
- **Affected component:** User expectations, support, and release packaging.
- **Description:** Confirmed contradictions include: guaranteed/private stock despite HTP-001; automatic expiration described as cron despite no scheduled event; “removed from My Account” though all statuses remain (up to 50); cancellation email and customizable email templates though neither exists; modal title/button customization controls that do not exist; pending stock said to be held although it is not decremented until approval; analytics simultaneously shipped and documented as planned; REST compatibility without routes; “full i18n/security/WPCS” and ready-to-publish claims despite HTP-008/009/015 and no checks/tests.
- **Evidence:** The cited documentation lines make these claims; the complete code inventory has no email template directory, REST registration, cron schedule, cancellation email hook, or those text settings.
- **Impact:** Administrators may deploy under false operational/privacy assumptions, and support/troubleshooting advice sends them toward nonexistent mechanisms.
- **Trigger or reproduction:** Follow the user guide to customize email templates or diagnose expiration with WP Crontrol.
- **Recommended remediation:** Make documentation release-generated/verified against supported behavior; remove unimplemented and readiness claims until their acceptance tests pass.
- **Verification after fixing:** Add a release checklist that maps every feature/privacy/compatibility statement to an automated or recorded manual test.
- **References:** [WordPress.org readme guidance](https://developer.wordpress.org/plugins/wordpress-org/how-your-readme-txt-works/)

### HTP-022 — No automated test or quality-tool configuration exists

- **Severity:** Informational
- **Category:** Testing
- **Confidence:** High
- **Location:** Plugin root (complete file inventory)
- **Affected component:** Release confidence.
- **Description:** There are no unit/integration/end-to-end tests, fixtures, Composer/npm manifests, PHPCS/PHPStan/Psalm/ESLint configs, CI workflow, or executable build/release definition. The publish checklist calls tests “nice to have.”
- **Evidence:** The repository contains only runtime source/assets/docs and editor configuration.
- **Impact:** Inventory, concurrency, authorization, timezone, accessibility, and compatibility regressions have no repeatable detection.
- **Trigger or reproduction:** Not applicable; this is an inventory-confirmed coverage gap.
- **Recommended remediation:** Establish WordPress/WooCommerce integration tests first, then unit/static/lint/E2E/accessibility matrices and CI across supported PHP/WP/WooCommerce versions.
- **Verification after fixing:** CI must run from a clean checkout and fail on deliberately seeded stock, auth, i18n, and accessibility regressions.
- **References:** [WordPress PHPUnit testing handbook](https://make.wordpress.org/core/handbook/testing/automated-testing/phpunit/)

### HTP-023 — Dead and duplicated implementation paths increase drift

- **Severity:** Informational
- **Category:** Maintainability
- **Confidence:** High
- **Location:** `includes/class-htp-reservations.php:33-34,457-535`; `includes/admin/class-htp-admin.php:459-494,573-611`; `includes/admin/class-htp-admin-analytics.php:120-157`
- **Affected component:** Reservation rendering/activation/expiration/admin cancellation.
- **Description:** `display_reservation_row()` is private and never called; activation is registered both in the bootstrap and again from a constructor reached only on `init`; product-edit cancellation markup/script duplicates the central reservations page implementation; analytics duplicates expiration SQL and contains an unreachable-in-normal-load fallback that changes stock differently.
- **Evidence:** Complete reference search found no call to `display_reservation_row`; bootstrap always loads `HTP_Reservations` before analytics, so its `class_exists` fallback is normally false only under nonstandard direct construction.
- **Impact:** Multiple representations of the same state machine already disagree on time/query behavior and make fixes easy to apply incompletely.
- **Trigger or reproduction:** Compare core and analytics expiration implementations or the two admin cancellation UIs.
- **Recommended remediation:** Remove dead paths and centralize querying, status transitions, stock reconciliation, rendering view-models, and admin actions in tested services.
- **Verification after fixing:** Static unused-code analysis plus contract tests proving every UI invokes the same transition service.
- **References:** None required.

## Open questions and unverified risks

- Compatibility with WooCommerce's current Cart/Checkout blocks, product blocks, High-Performance Order Storage, backorders, product bundles/subscriptions, multilingual plugins, multisite/network activation, and object caches was not runtime-tested. The code uses order/product APIs compatible with HPOS in the inspected paths, but no declaration or matrix exists.
- The free/Pro mutual-exclusion guard depends on `HTP_PRO_VERSION` already being defined when this plugin file loads. The Pro plugin was not present, so load-order behavior and class/constant collisions could not be confirmed.
- Email clients commonly sanitize HTML, but the plugin labels mail as HTML and interpolates product titles/denial text without explicit HTML escaping. No practical client-side exploit was claimed without a tested mail-client path.
- Actual color contrast depends partly on theme and administrator-selected colors; only structural CSS issues were confirmed.
- No lockfile exists, so there was no dependency advisory audit to run. Optional Google Fonts is the only external browser integration discovered.

## Test coverage gaps

- Stock invariants across reserve, approve, deny, cancel, expire, add-to-cart, checkout, order status changes, refunds, failures, deletion, and retries.
- Parallel reservation/approval/cancel/expiration workers and duplicate AJAX/webhook requests.
- Exact customer/product/variation/quantity correlation and malicious billing-email substitution.
- Capability matrices, wrong post types, nonce expiry/replay, IDOR, and custom roles.
- Timezones, DST, database/PHP timezone mismatch, clock boundaries, and legacy timestamps.
- Large datasets, paging, query counts, cache modes, and backlogs.
- Settings schema fuzzing and partial storage/write failures.
- Keyboard/screen-reader dialog behavior and automated accessibility checks.
- Localization extraction, plurals, JavaScript, emails, RTL, and locale-safe dates/numbers.
- Activation/deactivation/uninstall, missing WooCommerce, multisite, supported PHP/WP/WooCommerce versions, blocks, HPOS, product types, and theme conflicts.

## Positive observations

- Public reservation AJAX requires login and verifies a purpose-specific nonce; admin AJAX handlers verify nonces and `manage_options`.
- Customer account queries are author-scoped, and cancellation verifies the post author before mutation.
- Direct request guards are present in PHP files; SQL containing search input uses `$wpdb->prepare()` plus `esc_like()`.
- Most dynamic HTML values in the account/admin tables are contextually escaped, redirects use `wp_safe_redirect()`, and product IDs are normalized with `absint()`.
- Pending approval correctly avoids decrementing stock until approval, and cancel/expire restore stock only when the prior status is `active` in sequential execution.
- No uploads, filesystem mutation, dynamic includes, deserialization, command execution, REST permission callbacks, embedded secrets, tracking SDK, or bundled dependency code were found.
- JSON editor/Playground configuration parsed successfully, and PNG assets were valid recognizable PNG files by local metadata inspection.

## Recommended remediation order

1. **Immediate:** Disable production release; redesign the stock/ownership model (HTP-001/002), make transitions atomic/idempotent (HTP-003), and bind reservations explicitly to cart/order items across all stock-affecting states (HTP-004). Add integration and concurrency tests before re-enabling reservations.
2. **Next release:** Fix pending/active expiration and UTC storage (HTP-005/006/007), post-type/capability validation (HTP-008), settings schema (HTP-009), privacy/external fonts/data lifecycle (HTP-010/011), and the accessible dialog (HTP-012). Add compatibility headers and complete i18n (HTP-014/015).
3. **Longer-term improvements:** Replace postmeta-heavy queries/paging (HTP-013/017), scope assets and eligibility (HTP-016/018), convert cancellation to POST, remove duplicate/dead paths, correct documentation, and institutionalize CI/release verification (HTP-019-023).

## Appendix: verification log

- `pwd`; `git rev-parse --show-toplevel`; `git rev-parse --short HEAD`; `git log -1`; `git status --short`; `git status --short --ignored` — confirmed the reviewed root/commit, an initially clean tracked tree, and a pre-existing ignored `.vscode/` directory.
- `rg --files` plus `find ... | file` and `wc -l` — inventoried all runtime, configuration, documentation, and asset files; runtime/text scope was 6,459 lines before this report.
- `nl -ba`/`sed` over every PHP/JS/template/document/config file; targeted `rg` over inputs, hooks, SQL, mail, filesystem, redirects, remote resources, escaping, hard-coded UI, and lifecycle APIs — used for the architecture and data-flow review.
- `file assets/images/*`; `du -h assets/images/*` — PNG dimensions/types and sizes: menu icon 100x100/4 KiB; banner 1536x1024/1.2 MiB; transparent logo 1024x1024/392 KiB; white logo 1024x1024/20 KiB; logo 1024x1024/796 KiB.
- `jq empty .vscode/playground-blueprint.json .vscode/settings.json .vscode/tasks.json` — passed (jq `/usr/bin/jq`; version output was not separately requested).
- Attempted `php -v` and PHP lint loop — not run: `php` was not installed/on PATH (`zsh: command not found: php`).
- `command -v wp phpcs phpstan psalm eslint` — none present, so WordPress runtime tests, WPCS, static analysis, and ESLint were not run. No Composer/npm manifest or test runner exists.
- No code, dependencies, configuration, or generated assets were changed. `HoldThisProduct-review.md` is the only authorized review artifact.
