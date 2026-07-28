=== Hold This Product ===
Contributors: flaviusciortan
Tags: woocommerce, reservation, product hold, cart reserve, stock management
Requires at least: 6.5
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.0.1
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Allow customers to reserve WooCommerce products for a limited time, ensuring stock availability while they complete their purchase.

== Description ==

**HoldThisProduct** is a powerful WooCommerce extension that allows customers to temporarily reserve products, guaranteeing stock availability while they complete their purchase. Perfect for high-demand items or stores where customers need time to finalize their decisions.

= Key Features =

* **Product Reservations** - Let logged-in customers reserve products for a specified duration
* **Automatic Expiration** - Reservations automatically expire and restore stock
* **Customizable Duration** - Set reservation time limits (1-168 hours)
* **My Account Integration** - Customers manage their reservations from My Account page
* **Admin Dashboard** - View and manage all reservations from one place
* **Email Notifications** - Automatic emails for reservation creation, expiration, and approval
* **Admin Approval System** - Optional: require admin approval before reservations become active
* **Stock Management** - Automatic stock updates when reservations are created, expired, or fulfilled
* **Reservation Limits** - Set maximum concurrent reservations per user
* **Beautiful UI** - Modern, responsive interface that matches your theme

= Perfect For =

* **High-demand products** - Prevent overselling of limited stock items
* **Custom orders** - Give customers time to prepare custom specifications
* **Quote-based sales** - Hold items while quotes are being prepared
* **Consultation-required products** - Reserve while customers schedule consultations
* **Pre-orders** - Manage pre-release product reservations

= How It Works =

1. Enable reservations globally in plugin settings
2. Ensure products have stock management enabled and a stock quantity set
3. Customers see "Reserve Product" button on eligible product pages
4. Product is reserved and stock is reduced
5. Customer completes purchase within the reservation time
6. Reservation is fulfilled, or stock is restored on expiration

= Pro Features (Coming Soon) =

* Guest reservations without login
* Custom reservation forms
* Advanced reporting and analytics
* Bulk reservation management
* SMS notifications
* Multi-language support

== Installation ==

= Minimum Requirements =

* WordPress 6.5 or greater
* WooCommerce 8.0 or greater
* PHP version 7.4 or greater
* MySQL version 5.6 or greater

= Automatic Installation =

1. Log in to your WordPress dashboard
2. Navigate to **Plugins > Add New**
3. Search for "HoldThisProduct"
4. Click **Install Now** and then **Activate**

= Manual Installation =

1. Download the plugin ZIP file
2. Navigate to **Plugins > Add New > Upload Plugin**
3. Select the downloaded ZIP file and click **Install Now**
4. Click **Activate Plugin**

= Configuration =

1. Go to **HoldThisProduct > Settings** in your WordPress admin
2. Enable reservations globally
3. Set your preferred reservation duration (default: 24 hours)
4. Configure email notifications (optional)
5. Set maximum reservations per user
6. Ensure products have **Stock management** enabled and a stock quantity set (WooCommerce Inventory tab)

== Frequently Asked Questions ==

= Do customers need to be logged in to reserve products? =

Yes, in the free version, customers must be logged in to create reservations. This ensures proper tracking and management of reservations.

= What happens when a reservation expires? =

When a reservation expires, the product stock is automatically restored, and the customer receives an expiration notification email (if enabled).

= Can I customize the reservation duration? =

Yes! You can set the reservation duration anywhere from 1 to 168 hours (1 week) in the plugin settings.

= Will reservations work with variable products? =

The current version supports simple products. Variable product support is planned for a future update.

= Can I manage all reservations from one place? =

Absolutely! Navigate to **HoldThisProduct > Reservations** to view, cancel, or delete all reservations from a centralized dashboard.

= What happens if a customer adds a reserved product to their cart? =

When a customer completes their purchase, the reservation is automatically marked as fulfilled, and the stock adjustment is maintained.

= Can I require admin approval for reservations? =

Yes! Enable "Require Admin Approval for Reservations" in the settings. Reservations will remain in pending status until you approve them from the admin dashboard.

= Does this work with my theme? =

HoldThisProduct is designed to work with any properly coded WordPress theme. The reservation button integrates seamlessly with WooCommerce product pages.

= Is this compatible with other WooCommerce extensions? =

HoldThisProduct is compatible with most WooCommerce extensions. If you experience any conflicts, please report them in the support forum.

= Can I translate the plugin? =

Yes! HoldThisProduct is fully translation-ready. You can use tools like Loco Translate or WPML to translate all text.

== Screenshots ==

1. Plugin settings page - Configure reservation duration, limits, and notifications
2. Product page with "Reserve Product" button
3. Reservation modal for logged-in users
4. My Account - Reserved Products page showing active reservations
5. Admin reservations management dashboard
6. Product inventory tab showing active reservations
7. Email notification example

== Changelog ==

= 1.0.1 - 2026-07-28 =
* Hardened reservation authorization, validation, and settings sanitization
* Made stock reservation, approval, cancellation, expiration, and checkout transfers idempotent
* Added scheduled expiration, privacy tools integration, pagination, and accessibility improvements
* Improved compatibility declarations, translations, and release metadata

= 1.0.0 - 2025-11-12 =
* Initial release
* Product reservation system for logged-in users
* Automatic expiration and stock restoration
* My Account integration
* Admin reservation management dashboard
* Email notifications system
* Admin approval workflow (optional)
* Customizable reservation duration
* Per-user reservation limits
* WooCommerce stock management integration

== Upgrade Notice ==

= 1.0.1 =
Recommended maintenance and security update for reservation and stock handling.

= 1.0.0 =
Initial release of HoldThisProduct. Start accepting product reservations today!

== Additional Information ==

= Support =

For support inquiries, please visit the [plugin support forum](https://wordpress.org/support/plugin/hold-this-product/) or contact us through [GitHub](https://github.com/Flavius-Ciortan/HoldThisProduct).

= Documentation =

Full documentation is available in the plugin's USER_GUIDE.md file or on our [GitHub repository](https://github.com/Flavius-Ciortan/HoldThisProduct).

= Privacy Policy =

HoldThisProduct stores reservation data including:
* User ID (for logged-in users)
* Product ID
* Reservation timestamps
* Email addresses (for notifications)

This data is used solely for managing product reservations and is deleted when reservations are removed. No data is shared with third parties.

= Contributing =

We welcome contributions! Visit our [GitHub repository](https://github.com/Flavius-Ciortan/HoldThisProduct) to report issues or submit pull requests.
