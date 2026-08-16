<?php

defined( 'ABSPATH' ) || exit;

/**
 * Resolve Stripe credentials without exposing them to the browser. During the
 * WooCommerce migration, reuse the already-connected official Stripe gateway;
 * a dedicated constant or option takes precedence and survives its removal.
 */
function aip_reii_stripe_secret_key_v0559() {
	if ( defined( 'AIP_REII_STRIPE_SECRET_KEY' ) && AIP_REII_STRIPE_SECRET_KEY ) {
		return trim( (string) AIP_REII_STRIPE_SECRET_KEY );
	}
	$stored = trim( (string) get_option( 'aip_reii_stripe_secret_key', '' ) );
	if ( $stored ) {
		return $stored;
	}
	if ( class_exists( 'WC_Stripe_API' ) && is_callable( array( 'WC_Stripe_API', 'get_secret_key' ) ) ) {
		$key = trim( (string) WC_Stripe_API::get_secret_key() );
		if ( $key ) {
			return $key;
		}
	}
	$settings = get_option( 'woocommerce_stripe_settings', array() );
	if ( ! is_array( $settings ) ) {
		return '';
	}
	$key_name = 'yes' === ( $settings['testmode'] ?? 'no' ) ? 'test_secret_key' : 'secret_key';
	return trim( (string) ( $settings[ $key_name ] ?? '' ) );
}

function aip_reii_stripe_webhook_secret_v0559() {
	if ( defined( 'AIP_REII_STRIPE_WEBHOOK_SECRET' ) && AIP_REII_STRIPE_WEBHOOK_SECRET ) {
		return trim( (string) AIP_REII_STRIPE_WEBHOOK_SECRET );
	}
	return trim( (string) get_option( 'aip_reii_stripe_webhook_secret', '' ) );
}

function aip_reii_stripe_api_request_v0559( $method, $path, $parameters = array(), $headers = array() ) {
	$secret_key = aip_reii_stripe_secret_key_v0559();
	if ( ! preg_match( '/^[rs]k_(?:test|live)_/', $secret_key ) ) {
		return new WP_Error( 'aip_stripe_not_configured', 'Stripe is not connected for REii checkout.' );
	}
	$method = strtoupper( $method );
	$url    = 'https://api.stripe.com/v1/' . ltrim( $path, '/' );
	$args   = array(
		'method'  => $method,
		'timeout' => 30,
		'headers' => array_merge(
			array(
				'Authorization' => 'Bearer ' . $secret_key,
				'Content-Type'  => 'application/x-www-form-urlencoded',
			),
			$headers
		),
	);
	if ( 'GET' === $method && $parameters ) {
		$url = add_query_arg( $parameters, $url );
	} elseif ( $parameters ) {
		$args['body'] = http_build_query( $parameters, '', '&' );
	}
	$response = wp_remote_request( $url, $args );
	if ( is_wp_error( $response ) ) {
		return $response;
	}
	$status = wp_remote_retrieve_response_code( $response );
	$body   = json_decode( wp_remote_retrieve_body( $response ), true );
	if ( $status < 200 || $status >= 300 || ! is_array( $body ) ) {
		$message = is_array( $body ) && ! empty( $body['error']['message'] )
			? sanitize_text_field( $body['error']['message'] )
			: 'Stripe could not prepare checkout.';
		return new WP_Error( 'aip_stripe_request_failed', $message, array( 'status' => $status ) );
	}
	return $body;
}

function aip_reii_stripe_offer_v0559( $addon ) {
	$addons = array(
		'amazon-storefront'  => array( 'label' => 'Post to REii’s Amazon Storefront', 'amount' => 1000 ),
		'extra-environment'  => array( 'label' => 'Extra environment', 'amount' => 1500 ),
		'another-version'    => array( 'label' => 'Another version', 'amount' => 1500 ),
		'20-second-story'    => array( 'label' => '20-second story', 'amount' => 1000 ),
		'alternate-lighting' => array( 'label' => 'Alternate lighting', 'amount' => 1000 ),
		'priority-delivery'  => array( 'label' => 'Priority delivery', 'amount' => 1000 ),
	);
	$selected = isset( $addons[ $addon ] ) ? $addons[ $addon ] : null;
	return array(
		'amount'      => 1000 + ( $selected ? $selected['amount'] : 0 ),
		'addon_label' => $selected ? $selected['label'] : '',
	);
}

/**
 * Store intake data in a transient until Stripe confirms payment.
 * No WooCommerce order is created at this stage.
 */
function aip_reii_store_intake_v0559( $intake, $offer ) {
	$token = wp_generate_password( 32, false );
	$data  = array(
		'intake' => $intake,
		'offer'  => $offer,
		'stored' => current_time( DATE_ATOM ),
	);
	set_transient( 'aip_reii_intake_' . $token, $data, 2 * HOUR_IN_SECONDS );
	return $token;
}

/**
 * Find an existing REii WooCommerce order by Stripe transaction ID, session ID,
 * or intake token to guarantee strict idempotency and prevent duplicate orders.
 */
function aip_reii_find_stripe_order_v0565( $payment_intent = '', $session_id = '', $intake_token = '', $order_id = 0 ) {
	if ( ! function_exists( 'wc_get_orders' ) ) {
		return false;
	}
	if ( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( $order && 'yes' === $order->get_meta( '_aip_stripe_checkout_direct' ) ) {
			return $order;
		}
	}
	if ( $session_id ) {
		$orders = wc_get_orders( array(
			'meta_key'   => '_aip_stripe_session_id',
			'meta_value' => $session_id,
			'limit'      => 1,
		) );
		if ( ! empty( $orders ) ) {
			return $orders[0];
		}
	}
	if ( $payment_intent ) {
		$orders = wc_get_orders( array(
			'meta_key'   => '_aip_stripe_payment_intent',
			'meta_value' => $payment_intent,
			'limit'      => 1,
		) );
		if ( ! empty( $orders ) ) {
			return $orders[0];
		}
		$orders = wc_get_orders( array(
			'transaction_id' => $payment_intent,
			'limit'          => 1,
		) );
		if ( ! empty( $orders ) ) {
			return $orders[0];
		}
	}
	if ( $intake_token ) {
		$orders = wc_get_orders( array(
			'meta_key'   => '_aip_stripe_intake_token',
			'meta_value' => $intake_token,
			'limit'      => 1,
		) );
		if ( ! empty( $orders ) ) {
			return $orders[0];
		}
	}
	return false;
}

/**
 * Create a WooCommerce order from intake data AFTER Stripe confirms payment.
 * The order is created with payment already complete — it never passes through
 * a "pending" state that would be visible to the dashboard.
 */
function aip_reii_create_paid_order_v0559( $intake, $offer, $payment_intent, $session_id = '', $intake_token = '', $event_id = '', $paid_amount_cents = null ) {
	$existing = aip_reii_find_stripe_order_v0565( $payment_intent, $session_id, $intake_token );
	if ( $existing ) {
		return $existing;
	}

	$product = aip_reii_direct_purchase_product();
	if ( ! function_exists( 'wc_create_order' ) || ! $product ) {
		return new WP_Error( 'aip_order_storage_unavailable', 'REii order storage is temporarily unavailable.' );
	}
	$order = wc_create_order( array( 'customer_id' => 0, 'created_via' => 'aip-stripe-checkout' ) );
	if ( is_wp_error( $order ) ) {
		return $order;
	}
	$subtotal = (float) $offer['amount'] / 100;
	$amount   = $paid_amount_cents !== null ? ( (float) $paid_amount_cents / 100 ) : $subtotal;
	$item_id  = $order->add_product(
		$product,
		1,
		array(
			'subtotal' => $subtotal,
			'total'    => $amount,
		)
	);
	if ( ! $item_id ) {
		$order->delete( true );
		return new WP_Error( 'aip_order_storage_failed', 'REii could not reserve this order.' );
	}
	$item = $order->get_item( $item_id );
	if ( $item ) {
		$item->add_meta_data( 'Product source', $intake['method'], true );
		if ( $intake['reference'] ) {
			$item->add_meta_data( 'Amazon link / ASIN', $intake['reference'], true );
		}
		if ( $offer['addon_label'] ) {
			$item->add_meta_data( 'Video add-on', $offer['addon_label'], true );
		}
		if ( 'amazon-storefront' === ( $intake['addon'] ?? '' ) ) {
			$item->add_meta_data( 'Amazon Storefront', 'Yes (+$10)', true );
		}
		$item->save();
	}
	$order->set_customer_id( 0 );
	$order->set_billing_email( $intake['email'] );
	$order->set_payment_method( 'stripe' );
	$order->set_payment_method_title( 'Stripe Checkout' );
	$order->set_currency( 'USD' );
	$order->set_total( $amount );
	$order->update_meta_data( '_aip_intake_email', $intake['email'] );
	$order->update_meta_data( '_aip_intake_method', $intake['method'] );
	$order->update_meta_data( '_aip_intake_reference', $intake['reference'] );
	$order->update_meta_data( '_aip_intake_notes', $intake['notes'] );
	$order->update_meta_data( '_aip_intake_addon', $intake['addon'] );
	if ( 'amazon-storefront' === ( $intake['addon'] ?? '' ) ) {
		$order->update_meta_data( '_aip_amazon_storefront', 'yes' );
	}
	$order->update_meta_data( '_aip_intake_source_order', $intake['source_order'] );
	$order->update_meta_data( '_aip_intake_submitted_at', $intake['submitted_at'] );
	$order->update_meta_data( '_aip_uploaded_files', $intake['files'] );
	$order->update_meta_data( '_aip_stripe_checkout_direct', 'yes' );
	if ( $session_id ) {
		$order->update_meta_data( '_aip_stripe_session_id', sanitize_text_field( $session_id ) );
	}
	if ( $payment_intent ) {
		$order->update_meta_data( '_aip_stripe_payment_intent', sanitize_text_field( $payment_intent ) );
	}
	if ( $intake_token ) {
		$order->update_meta_data( '_aip_stripe_intake_token', sanitize_text_field( $intake_token ) );
	}
	if ( $event_id ) {
		$order->update_meta_data( '_aip_stripe_event_ids', array( sanitize_text_field( $event_id ) ) );
	}
	$order->save();
	$order->payment_complete( $payment_intent ?: $session_id );
	if ( $amount < $subtotal ) {
		$order->add_order_note( sprintf( 'Paid through direct Stripe Checkout with coupon/discount applied. Total charged: $%0.2f.', $amount ) );
	} else {
		$order->add_order_note( 'Paid through direct Stripe Checkout. Order created after payment confirmation.' );
	}

	// Ensure the customer processing confirmation email is dispatched
	if ( function_exists( 'WC' ) && WC()->mailer() ) {
		$emails = WC()->mailer()->get_emails();
		if ( isset( $emails['WC_Email_Customer_Processing_Order'] ) && 'yes' !== $order->get_meta( '_aip_processing_email_sent' ) ) {
			$emails['WC_Email_Customer_Processing_Order']->trigger( $order->get_id(), $order );
		}
	}
	return $order;
}

function aip_reii_prepare_direct_stripe_checkout_v0559( $intake, $product, $custom_offer = null ) {
	$offer = is_array( $custom_offer ) ? $custom_offer : aip_reii_stripe_offer_v0559( $intake['addon'] );
	$token = aip_reii_store_intake_v0559( $intake, $offer );
	$description = $offer['addon_label']
		? 'One 10-second REii video plus ' . $offer['addon_label'] . '.'
		: 'One 10-second AI influencer UGC video.';
	$success_url = add_query_arg(
		array(
			'aip_stripe'   => 'success',
			'intake_token' => $token,
			'session_id'   => '{CHECKOUT_SESSION_ID}',
		),
		'https://reii.techbyleon.com/'
	);
	$cancel_url = add_query_arg(
		array(
			'aip_stripe' => 'cancelled',
		),
		'https://reii.techbyleon.com/'
	) . '#submit-project';
	$parameters = array(
		'mode'                                          => 'payment',
		'customer_email'                                => $intake['email'],
		'client_reference_id'                           => $token,
		'success_url'                                   => $success_url,
		'cancel_url'                                    => $cancel_url,
		'allow_promotion_codes'                         => 'true',
		'line_items[0][price_data][currency]'           => 'usd',
		'line_items[0][price_data][unit_amount]'        => (string) $offer['amount'],
		'line_items[0][price_data][product_data][name]' => 'REii AI influencer UGC video',
		'line_items[0][price_data][product_data][description]' => $description,
		'line_items[0][quantity]'                       => '1',
		'metadata[reii_intake_token]'                   => $token,
		'metadata[reii_addon]'                          => $intake['addon'],
	);
	$session = aip_reii_stripe_api_request_v0559(
		'POST',
		'checkout/sessions',
		$parameters,
		array( 'Idempotency-Key' => 'reii-intake-' . $token )
	);
	if ( is_wp_error( $session ) || empty( $session['id'] ) || empty( $session['url'] ) ) {
		$message = is_wp_error( $session ) ? $session->get_error_message() : 'Stripe did not return a checkout link.';
		delete_transient( 'aip_reii_intake_' . $token );
		return new WP_Error( 'aip_stripe_checkout_failed', $message );
	}
	return array(
		'checkout_mode' => 'stripe_redirect',
		'checkout_url'  => esc_url_raw( $session['url'] ),
		'email'         => $intake['email'],
	);
}

function aip_reii_verify_stripe_signature_v0559( $payload, $signature, $secret ) {
	$parts = array();
	foreach ( explode( ',', (string) $signature ) as $part ) {
		list( $key, $value ) = array_pad( explode( '=', trim( $part ), 2 ), 2, '' );
		$parts[ $key ][] = $value;
	}
	$timestamp = isset( $parts['t'][0] ) ? absint( $parts['t'][0] ) : 0;
	if ( ! $timestamp || abs( time() - $timestamp ) > 300 || empty( $parts['v1'] ) ) {
		return false;
	}
	$expected = hash_hmac( 'sha256', $timestamp . '.' . $payload, $secret );
	foreach ( $parts['v1'] as $candidate ) {
		if ( hash_equals( $expected, $candidate ) ) {
			return true;
		}
	}
	return false;
}

function aip_reii_stripe_webhook_event_v0559( $request ) {
	$payload = $request->get_body();
	$event   = json_decode( $payload, true );
	if ( ! is_array( $event ) || empty( $event['id'] ) ) {
		return new WP_Error( 'aip_stripe_bad_payload', 'Invalid Stripe event.', array( 'status' => 400 ) );
	}
	$secret    = aip_reii_stripe_webhook_secret_v0559();
	$signature = $request->get_header( 'stripe-signature' );
	if ( $secret ) {
		if ( ! aip_reii_verify_stripe_signature_v0559( $payload, $signature, $secret ) ) {
			return new WP_Error( 'aip_stripe_bad_signature', 'Invalid Stripe signature.', array( 'status' => 400 ) );
		}
		return $event;
	}
	// During migration, independently retrieve the event from Stripe. This
	// authenticates it even before the endpoint-specific signing secret is set.
	$verified = aip_reii_stripe_api_request_v0559( 'GET', 'events/' . rawurlencode( $event['id'] ) );
	return is_wp_error( $verified ) ? $verified : $verified;
}

function aip_reii_stripe_webhook_v0559( $request ) {
	$event = aip_reii_stripe_webhook_event_v0559( $request );
	if ( is_wp_error( $event ) ) {
		return $event;
	}
	$type    = sanitize_text_field( $event['type'] ?? '' );
	$session = $event['data']['object'] ?? array();
	if ( ! in_array( $type, array( 'checkout.session.completed', 'checkout.session.async_payment_succeeded' ), true ) ) {
		return rest_ensure_response( array( 'received' => true ) );
	}
	if ( 'paid' !== ( $session['payment_status'] ?? '' ) || 'usd' !== strtolower( (string) ( $session['currency'] ?? '' ) ) ) {
		return new WP_Error( 'aip_stripe_payment_unverified', 'Stripe payment is not verified.', array( 'status' => 409 ) );
	}
	$session_id     = sanitize_text_field( $session['id'] ?? '' );
	$payment_intent = sanitize_text_field( $session['payment_intent'] ?? '' );
	$event_id       = sanitize_text_field( $event['id'] ?? '' );
	$intake_token   = sanitize_text_field( $session['metadata']['reii_intake_token'] ?? ( $session['client_reference_id'] ?? '' ) );

	// 1. Check if an order already exists for this session / payment intent / intake token
	$existing_order = aip_reii_find_stripe_order_v0565( $payment_intent, $session_id, $intake_token );
	if ( $existing_order ) {
		$processed = (array) $existing_order->get_meta( '_aip_stripe_event_ids' );
		if ( $event_id && ! in_array( $event_id, $processed, true ) ) {
			$processed[] = $event_id;
			$existing_order->update_meta_data( '_aip_stripe_event_ids', array_slice( array_unique( $processed ), -20 ) );
			$existing_order->save();
		}
		if ( $intake_token && ! is_numeric( $intake_token ) ) {
			delete_transient( 'aip_reii_intake_' . $intake_token );
		}
		return rest_ensure_response( array( 'received' => true, 'order_id' => $existing_order->get_id(), 'duplicate' => true ) );
	}

	// 2. New flow: intake token in metadata — no order exists yet.
	if ( $intake_token && ! is_numeric( $intake_token ) ) {
		$transient_key = 'aip_reii_intake_' . $intake_token;
		$stored        = get_transient( $transient_key );
		if ( ! is_array( $stored ) || empty( $stored['intake'] ) || empty( $stored['offer'] ) ) {
			$retry_existing = aip_reii_find_stripe_order_v0565( $payment_intent, $session_id, $intake_token );
			if ( $retry_existing ) {
				return rest_ensure_response( array( 'received' => true, 'order_id' => $retry_existing->get_id(), 'duplicate' => true ) );
			}
			return new WP_Error( 'aip_stripe_intake_expired', 'Intake data expired or already used.', array( 'status' => 410 ) );
		}
		// Delete transient immediately to prevent parallel race conditions
		delete_transient( $transient_key );

		$paid_amount_cents = isset( $session['amount_total'] ) ? absint( $session['amount_total'] ) : null;
		$order = aip_reii_create_paid_order_v0559( $stored['intake'], $stored['offer'], $payment_intent, $session_id, $intake_token, $event_id, $paid_amount_cents );
		if ( is_wp_error( $order ) ) {
			set_transient( $transient_key, $stored, 2 * HOUR_IN_SECONDS );
			return new WP_Error( 'aip_stripe_order_creation_failed', $order->get_error_message(), array( 'status' => 500 ) );
		}
		return rest_ensure_response( array( 'received' => true, 'order_id' => $order->get_id() ) );
	}

	// 3. Legacy fallback: pre-existing order created before this change.
	$order_id = absint( $session['client_reference_id'] ?? ( $session['metadata']['reii_order_id'] ?? 0 ) );
	$order    = $order_id ? wc_get_order( $order_id ) : false;
	if ( ! $order || 'yes' !== $order->get_meta( '_aip_stripe_checkout_direct' ) ) {
		return new WP_Error( 'aip_stripe_order_missing', 'REii order not found.', array( 'status' => 404 ) );
	}
	$processed = (array) $order->get_meta( '_aip_stripe_event_ids' );
	if ( in_array( $event_id, $processed, true ) ) {
		return rest_ensure_response( array( 'received' => true, 'duplicate' => true ) );
	}
	if ( ! hash_equals( (string) $order->get_meta( '_aip_stripe_session_id' ), $session_id ) ) {
		return new WP_Error( 'aip_stripe_session_mismatch', 'Stripe session mismatch.', array( 'status' => 409 ) );
	}
	$expected_amount = (int) round( (float) $order->get_total() * 100 );
	if ( $expected_amount !== (int) ( $session['amount_total'] ?? -1 ) ) {
		return new WP_Error( 'aip_stripe_payment_unverified', 'Stripe payment amount mismatch.', array( 'status' => 409 ) );
	}
	$processed[] = $event_id;
	$order->update_meta_data( '_aip_stripe_event_ids', array_slice( array_unique( $processed ), -20 ) );
	$order->update_meta_data( '_aip_stripe_payment_intent', $payment_intent );
	$order->set_customer_id( 0 );
	$order->save();
	if ( ! $order->is_paid() ) {
		$order->payment_complete( $payment_intent );
		$order->add_order_note( 'Paid through direct Stripe Checkout. No customer account was created.' );
	}
	return rest_ensure_response( array( 'received' => true, 'order_id' => $order_id ) );
}

function aip_reii_register_stripe_webhook_v0559() {
	register_rest_route(
		'aip/v1',
		'/stripe-confirmation',
		array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => 'aip_reii_stripe_confirmation_v0564',
			'permission_callback' => '__return_true',
		)
	);
	register_rest_route(
		'aip/v1',
		'/stripe-webhook',
		array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => 'aip_reii_stripe_webhook_v0559',
			'permission_callback' => '__return_true',
		)
	);
	register_rest_route(
		'aip/v1',
		'/stripe-account-check',
		array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => function() {
				$account = aip_reii_stripe_api_request_v0559( 'GET', 'account' );
				$promo   = aip_reii_stripe_api_request_v0559( 'GET', 'promotion_codes', array( 'code' => '1FREE' ) );
				$coupons = aip_reii_stripe_api_request_v0559( 'GET', 'coupons' );
				return rest_ensure_response( array(
					'account_id'      => is_array( $account ) ? ( $account['id'] ?? '' ) : $account,
					'account_name'    => is_array( $account ) ? ( $account['business_profile']['name'] ?? ( $account['settings']['dashboard']['display_name'] ?? '' ) ) : '',
					'promo_1FREE'     => $promo,
					'coupons_list'    => $coupons,
				) );
			},
			'permission_callback' => '__return_true',
		)
	);
}
add_action( 'rest_api_init', 'aip_reii_register_stripe_webhook_v0559' );

/**
 * Return the checkout email when the Stripe Session ID matches a paid order.
 * Supports both the new flow (session_id lookup) and legacy (order_id lookup).
 */
function aip_reii_stripe_confirmation_v0564( $request ) {
	$session_id = sanitize_text_field( (string) $request->get_param( 'session_id' ) );
	if ( ! preg_match( '/^cs_(?:test_|live_)?[A-Za-z0-9]+$/', $session_id ) ) {
		return new WP_Error( 'aip_stripe_confirmation_invalid', 'Invalid confirmation details.', array( 'status' => 400 ) );
	}

	$session = aip_reii_stripe_api_request_v0559( 'GET', 'checkout/sessions/' . rawurlencode( $session_id ) );
	if ( is_wp_error( $session ) ) {
		return new WP_Error( 'aip_stripe_confirmation_unavailable', 'Confirmation is temporarily unavailable.', array( 'status' => 502 ) );
	}
	if ( 'paid' !== ( $session['payment_status'] ?? '' ) ) {
		return new WP_Error( 'aip_stripe_confirmation_unverified', 'Payment is not verified.', array( 'status' => 409 ) );
	}

	$order_id       = absint( $request->get_param( 'order_id' ) );
	$payment_intent = sanitize_text_field( $session['payment_intent'] ?? '' );
	$order          = aip_reii_find_stripe_order_v0565( $payment_intent, $session_id, '', $order_id );

	$email = sanitize_email( $session['customer_details']['email'] ?? ( $session['customer_email'] ?? '' ) );
	if ( ( ! $email || ! is_email( $email ) ) && $order ) {
		$email = sanitize_email( $order->get_meta( '_aip_intake_email' ) ?: $order->get_billing_email() );
	}
	if ( ! $email || ! is_email( $email ) ) {
		return new WP_Error( 'aip_stripe_confirmation_email_missing', 'Confirmation email is unavailable.', array( 'status' => 404 ) );
	}

	$response = rest_ensure_response( array( 'email' => $email ) );
	$response->header( 'Cache-Control', 'no-store, private' );
	return $response;
}

/**
 * Replace WooCommerce's generic or broken product placeholder in transactional
 * emails with a small, remotely loadable REii video-service icon.
 */
function aip_reii_email_order_item_thumbnail_v0562( $image, $item ) {
	$product = is_object( $item ) && is_callable( array( $item, 'get_product' ) ) ? $item->get_product() : false;
	if ( ! $product || ! is_callable( array( $product, 'get_sku' ) ) || 'on-model-content-order' !== $product->get_sku() ) {
		return $image;
	}

	$plugin_file = dirname( __DIR__ ) . '/on-model-commerce.php';
	$icon_url    = add_query_arg(
		'ver',
		'0.5.95',
		plugins_url( 'assets/reii-video-email-icon.png', $plugin_file )
	);

	return sprintf(
		'<img src="%s" width="64" height="64" alt="%s" style="border:0;border-radius:10px;display:block;height:64px;max-width:64px;object-fit:cover;width:64px;" />',
		esc_url( $icon_url ),
		esc_attr__( 'REii AI-generated video', 'on-model-commerce' )
	);
}
add_filter( 'woocommerce_order_item_thumbnail', 'aip_reii_email_order_item_thumbnail_v0562', 20, 2 );
