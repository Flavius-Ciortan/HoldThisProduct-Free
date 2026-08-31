# Hold This Product Extension API

This document describes the public contracts available to compatible add-ons in Hold This Product Free 1.1.0. Add-ons should require Free, wait for `htp_plugin_loaded`, and use these contracts instead of duplicating reservation or inventory logic.

## Bootstrap

```php
add_action(
	'htp_plugin_loaded',
	function ( HoldThisProduct $plugin, HTP_Service_Container $services ) {
		$lifecycle = $services->get( 'lifecycle' );
		if ( ! $lifecycle instanceof HTP_Reservation_Lifecycle_Interface ) {
			return;
		}

		// Register the add-on after all Free services are ready.
	}
);
```

The same service can be retrieved with `$plugin->get_service( 'lifecycle' )`.

Stable service IDs:

- `lifecycle`: implements `HTP_Reservation_Lifecycle_Interface`.
- `repository`: implements `HTP_Reservation_Repository_Interface`.
- `rules`: resolves the filtered Free reservation rules.
- `notifications`: dispatches canonical reservation events.
- `dependency_notices`: registers admin notices for add-on dependency failures.

Inventory, locking, expiration, privacy, and cart/order services are exposed for internal coordination but are not independent public APIs. Add-ons should use the lifecycle interface for state changes.

## Lifecycle Interface

`HTP_Reservation_Lifecycle_Interface` exposes:

```php
request( $product_id, $user_id );
create( $product_id, $user_id = 0, $guest_email = '' );
approve( $reservation_id );
deny( $reservation_id, $reason = '' );
cancel( $reservation_id );
```

Methods return their documented success value or `WP_Error`. Callers must preserve the returned error and must not update reservation status or WooCommerce stock directly.

## Rule Filters

### `htp_product_is_reservable`

```php
apply_filters( 'htp_product_is_reservable', bool $reservable, ?WC_Product $product, int $user_id );
```

Extends eligibility after Free has checked its global switch, customer identity, product type, published/purchasable state, managed stock, and positive quantity.

### `htp_customer_reservation_limit`

```php
apply_filters( 'htp_customer_reservation_limit', int $limit, int $user_id );
```

Changes the concurrent open-reservation limit for a customer. Return an integer of at least one.

### `htp_reservation_requires_approval`

```php
apply_filters( 'htp_reservation_requires_approval', bool $required, int $product_id, int $user_id );
```

Determines whether a request remains pending or becomes active immediately.

### `htp_reservation_duration_hours`

```php
apply_filters( 'htp_reservation_duration_hours', int $hours, string $context, int $product_id, int $user_id );
```

`$context` is `pending` or `active`. Return an integer of at least one.

### `htp_manage_reservations_capability`

```php
apply_filters( 'htp_manage_reservations_capability', string $capability );
```

Changes the capability used by settings and reservation operations. The default is `manage_woocommerce`.

## Transition Contracts

### `htp_reservation_transition_allowed`

```php
apply_filters(
	'htp_reservation_transition_allowed',
	bool $allowed,
	int $reservation_id,
	string $from_status,
	string $to_status,
	string $source
);
```

Runs before a lifecycle transaction changes status or inventory. Return `false` to veto the transition. A veto must be deterministic and fast.

### `htp_reservation_transitioned`

```php
do_action(
	'htp_reservation_transitioned',
	array(
		'reservation_id' => 123,
		'from'           => 'pending_approval',
		'to'             => 'active',
		'source'         => 'approve',
		'occurred_at'    => 1788134400,
	)
);
```

Runs only after a successful transition. Treat the payload as read-only. Handlers should be idempotent because external delivery can be retried by an add-on.

Canonical public statuses are defined by `HTP_Reservation_Status`: `pending_approval`, `active`, `expired`, `cancelled`, `fulfilled`, `denied`, and `order_cancelled`.

## Notification Contract

```php
do_action( 'htp_reservation_event', string $event, int $reservation_id, string $email, array $context );
```

Canonical events are `created`, `pending`, `approved`, `expired`, and `denied`. This event is the preferred integration point for custom email, webhook, or messaging providers. Free also emits its legacy event-specific hooks for backward compatibility.

## Dependency Notices

An add-on that cannot initialize should register a clear notice instead of deactivating Free:

```php
add_action(
	'htp_plugin_loaded',
	function ( HoldThisProduct $plugin ) {
		$notices = $plugin->get_service( 'dependency_notices' );
		if ( $notices instanceof HTP_Dependency_Notices ) {
			$notices->add( 'my-addon-version', 'My Add-on requires a newer Hold This Product Free version.' );
		}
	}
);
```

## Compatibility Rules

- Require a compatible `HTP_VERSION` before registering modules.
- Never write canonical reservation meta or product stock to emulate a lifecycle transition.
- Never replace or subclass the main Free plugin class.
- Keep add-on data readable when the add-on is disabled or its license expires.
- Use the canonical filters for product, customer, approval, and duration rules.
- Use lifecycle methods for approve, deny, and cancel operations.
- Keep callbacks idempotent and avoid remote requests inside inventory transactions.
