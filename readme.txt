=== Hold This Product ===
Contributors: flaviusciortan
Tags: woocommerce, reservation, product hold, stock management, inventory
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 7.4
WC requires at least: 8.0
WC tested up to: 11.0
Stable tag: 1.1.0
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Let logged-in customers reserve WooCommerce products for a limited time while keeping inventory and checkout fulfillment consistent.

== Description ==

Hold This Product adds a complete reservation workflow for WooCommerce stores that sell stock-managed simple products.

= Customer features =

* Reserve an eligible product from its product page
* View active and historical reservations in My Account
* Add an active reservation to the cart and complete checkout
* Cancel an eligible reservation
* Receive reservation, approval, denial, and expiration emails when notifications are enabled

= Merchant features =

* Configure reservation and pending-approval durations from 1 to 168 hours
* Limit concurrent open reservations per customer
* Require merchant approval before stock is held
* Search, filter, approve, deny, cancel, or delete reservations
* View product-level active reservations and basic reservation analytics
* Customize reservation modal colors, typography, and border radius
* Allow Administrators and WooCommerce Shop Managers to operate reservations

= Inventory behavior =

An immediate reservation reduces stock once. When approval is required, the request remains pending without changing stock; approval starts the active hold and its duration. Cancellation or expiration releases held stock once. Checkout transfers the held unit to the order without a second stock decrement. Qualifying cancelled orders restore the unit once.

The Free edition supports logged-in customers, simple products with WooCommerce stock management enabled, and one unit per reservation.

== Installation ==

1. Install and activate WooCommerce.
2. Install Hold This Product from WordPress.org, or upload the release ZIP through **Plugins > Add New > Upload Plugin**.
3. Activate the plugin.
4. Open **Hold This Product > Settings** and enable reservations.
5. Configure durations, limits, notifications, and approval behavior.
6. Ensure reservable products are published simple products with stock management enabled and positive stock.

== Frequently Asked Questions ==

= Do customers need an account? =

Yes. The current Free workflow requires a logged-in WordPress customer.

= Does a pending approval request reduce stock? =

No. Stock is held only when the request is approved. A pending request has its own deadline; the active reservation duration starts at approval.

= What happens when an active reservation expires? =

The reservation becomes expired and its held stock is restored exactly once. If notifications are enabled, the customer receives an expiration email.

= Which products are supported? =

Published, purchasable simple products with WooCommerce stock management enabled and positive stock are supported. Variable, grouped, external, composite, and bundled products are outside the current Free scope.

= Does checkout reduce stock twice? =

No. The reservation owns the held unit until checkout transfers that inventory obligation to the order.

= Does it work with Cart and Checkout Blocks and HPOS? =

Yes. The plugin declares and tests compatibility with WooCommerce Cart/Checkout Blocks and High-Performance Order Storage.

= Do I need an SMTP plugin? =

Not necessarily. Hold This Product uses the standard WordPress mail pipeline. Each merchant should decide whether their host delivers mail reliably or whether an SMTP service/plugin is appropriate.

= Can Shop Managers operate reservations? =

Yes. Reservation settings and operations use WooCommerce's management capability, which is available to Administrators and Shop Managers.

= What data does the plugin store? =

Reservation records contain the customer, product, status, timestamps, email, quantity, inventory state, and linked order where applicable. The plugin integrates with WordPress personal-data export and erasure tools.

== Screenshots ==

1. Reservation settings and approval controls
2. Eligible product with the Reserve Product button
3. Accessible reservation confirmation modal
4. Customer reservations in My Account
5. Merchant reservation management and filters
6. Product inventory panel with active reservations
7. Basic reservation analytics

== Changelog ==

= 1.1.0 - 2026-08-31 =

* Reworked lifecycle and inventory ownership into transactional, idempotent services
* Added safe cart-before-reservation linkage and checkout fulfillment without double stock reduction
* Added concurrency locking, cron self-healing, inventory diagnostics, and upgrade reconciliation
* Corrected pending quotas, privacy erasure pagination, order-cancelled handling, and Shop Manager access
* Added stable service, rule, lifecycle, and notification extension contracts
* Added HPOS, classic checkout, block checkout, accessibility, role, lifecycle, and exact-artifact verification
* Added deterministic release packaging, WordPress coding standards, Plugin Check, and CI quality gates

= 1.0.1 - 2026-07-28 =

* Hardened authorization, validation, settings sanitization, and stock state handling
* Added scheduled expiration, privacy tools, pagination, and compatibility declarations

= 1.0.0 - 2025-11-12 =

* Initial public release

== Upgrade Notice ==

= 1.1.0 =

Recommended reliability update for reservation lifecycle, inventory ownership, checkout compatibility, and release quality.

== Support ==

Use the WordPress.org support forum or the GitHub issue tracker at https://github.com/Flavius-Ciortan/HoldThisProduct.
