<?php
/**
 * Plugin Name: REii Commerce
 * Description: Direct Stripe ordering and private delivery for REii AI influencer UGC videos.
 * Version: 0.5.72
 * Author: Tech by Leon
 * Requires Plugins: woocommerce
 * Update URI: https://github.com/whoisleon/on-model-commerce
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/includes/direct-stripe.php';

// REii is one WordPress category presented on its own public hostname. Keep
// WordPress itself, REST, Admin, uploads, WooCommerce, and webhooks on the
// canonical Tech by Leon installation; only public REii permalinks move.
if ( ! function_exists( 'aip_reii_public_origin_v0561' ) ) {
function aip_reii_public_origin_v0561() {
	return 'https://reii.techbyleon.com';
}

function aip_reii_category_id_v0561() {
	return 9792391;
}

function aip_reii_is_public_post_v0561( $post ) {
	$post = get_post( $post );
	return $post && 'post' === $post->post_type && has_category( aip_reii_category_id_v0561(), $post );
}

function aip_reii_public_url_v0561( $url ) {
	$parts = wp_parse_url( $url );
	if ( ! is_array( $parts ) ) {
		return $url;
	}
	$path     = isset( $parts['path'] ) ? $parts['path'] : '/';
	$query    = isset( $parts['query'] ) && '' !== $parts['query'] ? '?' . $parts['query'] : '';
	$fragment = isset( $parts['fragment'] ) && '' !== $parts['fragment'] ? '#' . $parts['fragment'] : '';
	return aip_reii_public_origin_v0561() . '/' . ltrim( $path, '/' ) . $query . $fragment;
}

function aip_reii_public_post_link_v0561( $url, $post ) {
	return aip_reii_is_public_post_v0561( $post ) ? aip_reii_public_url_v0561( $url ) : $url;
}
add_filter( 'post_link', 'aip_reii_public_post_link_v0561', 20, 2 );

function aip_reii_public_category_link_v0561( $url, $term_id ) {
	return aip_reii_category_id_v0561() === (int) $term_id
		? trailingslashit( aip_reii_public_origin_v0561() )
		: $url;
}
add_filter( 'category_link', 'aip_reii_public_category_link_v0561', 20, 2 );

function aip_reii_public_canonical_v0561( $url ) {
	if ( is_singular( 'post' ) && aip_reii_is_public_post_v0561( get_queried_object_id() ) ) {
		return get_permalink( get_queried_object_id() );
	}
	return $url;
}
add_filter( 'get_canonical_url', 'aip_reii_public_canonical_v0561', 20 );
add_filter( 'wpseo_canonical', 'aip_reii_public_canonical_v0561', 20 );
add_filter( 'wpseo_opengraph_url', 'aip_reii_public_canonical_v0561', 20 );
add_filter( 'rank_math/frontend/canonical', 'aip_reii_public_canonical_v0561', 20 );
add_filter( 'rank_math/opengraph/facebook/url', 'aip_reii_public_canonical_v0561', 20 );

function aip_reii_allowed_redirect_host_v0561( $hosts ) {
	$hosts[] = 'reii.techbyleon.com';
	return array_values( array_unique( $hosts ) );
}
add_filter( 'allowed_redirect_hosts', 'aip_reii_allowed_redirect_host_v0561' );

function aip_reii_route_public_posts_v0561() {
	if (
		is_admin()
		|| wp_doing_ajax()
		|| wp_doing_cron()
		|| ( defined( 'REST_REQUEST' ) && REST_REQUEST )
		|| ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST )
		|| is_preview()
		|| is_feed()
	) {
		return;
	}

	$host = strtolower( (string) wp_unslash( $_SERVER['HTTP_HOST'] ?? '' ) );
	$host = preg_replace( '/:\d+$/', '', $host );
	if ( is_category( aip_reii_category_id_v0561() ) ) {
		wp_safe_redirect( trailingslashit( aip_reii_public_origin_v0561() ), 301, 'REii Commerce' );
		exit;
	}
	if ( ! is_singular( 'post' ) ) {
		return;
	}

	$post_id = get_queried_object_id();
	$is_reii = aip_reii_is_public_post_v0561( $post_id );
	if ( $is_reii && in_array( $host, array( 'techbyleon.com', 'www.techbyleon.com' ), true ) ) {
		wp_safe_redirect( get_permalink( $post_id ), 301, 'REii Commerce' );
		exit;
	}
	if ( ! $is_reii && 'reii.techbyleon.com' === $host ) {
		$request_uri = (string) wp_unslash( $_SERVER['REQUEST_URI'] ?? '/' );
		$path        = wp_parse_url( $request_uri, PHP_URL_PATH );
		$query       = wp_parse_url( $request_uri, PHP_URL_QUERY );
		$destination = home_url( $path ? $path : '/' );
		if ( $query ) {
			$destination .= '?' . $query;
		}
		wp_safe_redirect( $destination, 301, 'REii Commerce' );
		exit;
	}
}
add_action( 'template_redirect', 'aip_reii_route_public_posts_v0561', 1 );
}

// Keep the updater outside the legacy plugin class. Some WordPress.com sites
// still preload an orphaned copy of that class, so class-scoped hooks can be
// skipped before this plugin gets a chance to repair or replace itself.
if ( ! function_exists( 'aip_github_updater_can_manage' ) ) {
function aip_github_updater_can_manage() {
	return current_user_can( 'update_plugins' ) || current_user_can( 'install_plugins' );
}

function aip_github_updater_action_links( $links, $plugin_file ) {
	if ( plugin_basename( __FILE__ ) !== $plugin_file || ! aip_github_updater_can_manage() ) {
		return $links;
	}
	$url = wp_nonce_url(
		admin_url( 'admin-post.php?action=aip_github_update' ),
		'aip_github_update'
	);
	array_unshift(
		$links,
		'<a href="' . esc_url( $url ) . '"><strong>' . esc_html__( 'Update from GitHub', 'on-model-commerce' ) . '</strong></a>'
	);
	return $links;
}

function aip_github_updater_redirect( $status ) {
	wp_safe_redirect(
		add_query_arg( 'aip_github_update', sanitize_key( $status ), self_admin_url( 'plugins.php' ) )
	);
	exit;
}

function aip_github_updater_run() {
	if ( ! aip_github_updater_can_manage() ) {
		wp_die(
			esc_html__( 'You are not allowed to update plugins.', 'on-model-commerce' ),
			esc_html__( 'Plugin update denied', 'on-model-commerce' ),
			array( 'response' => 403 )
		);
	}
	check_admin_referer( 'aip_github_update' );

	$response = wp_remote_get(
		'https://raw.githubusercontent.com/whoisleon/on-model-commerce/main/readme.txt?cache=' . time(),
		array(
			'headers' => array( 'User-Agent' => 'Style-by-REii-Commerce-Updater' ),
			'timeout' => 15,
		)
	);
	$plugin_file = plugin_basename( __FILE__ );
	$plugin_data = get_file_data( __FILE__, array( 'version' => 'Version' ), 'plugin' );
	$current     = isset( $plugin_data['version'] ) ? $plugin_data['version'] : '0.0.0';
	// WordPress requires a version greater than the installed plugin before it
	// will hand a package to the upgrader. Use a sentinel only when the host
	// blocks GitHub's optional readme request; the installed ZIP still carries
	// and displays its real semantic version.
	$latest      = '999.0.0';
	if ( ! is_wp_error( $response ) && 200 === wp_remote_retrieve_response_code( $response ) ) {
		$readme = wp_remote_retrieve_body( $response );
		if ( preg_match( '/^Stable tag:\s*(\d+\.\d+\.\d+(?:[-+][0-9A-Za-z.-]+)?)\s*$/mi', $readme, $matches ) ) {
			$latest = $matches[1];
		}
	}

	require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
	$skin     = new Automatic_Upgrader_Skin();
	$upgrader = new Plugin_Upgrader( $skin );
	$result   = $upgrader->install(
		'https://github.com/whoisleon/on-model-commerce/releases/latest/download/on-model-commerce.zip',
		array( 'overwrite_package' => true )
	);
	if ( is_wp_error( $result ) || ! $result ) {
		aip_github_updater_redirect( 'failed' );
	}
	wp_clean_plugins_cache( true );
	aip_github_updater_redirect( 'updated' );
}

function aip_github_updater_notice() {
	if ( empty( $_GET['aip_github_update'] ) || ! aip_github_updater_can_manage() ) {
		return;
	}
	$status = sanitize_key( wp_unslash( $_GET['aip_github_update'] ) );
	if ( 'current' === $status ) {
		$message = __( 'REii Commerce is already on the latest GitHub release.', 'on-model-commerce' );
		$class   = 'notice notice-success is-dismissible';
	} elseif ( 'unavailable' === $status ) {
		$message = __( 'The latest GitHub release could not be reached. Please try again shortly.', 'on-model-commerce' );
		$class   = 'notice notice-error is-dismissible';
	} elseif ( 'updated' === $status ) {
		$message = __( 'REii Commerce was updated from the latest GitHub release.', 'on-model-commerce' );
		$class   = 'notice notice-success is-dismissible';
	} elseif ( 'failed' === $status ) {
		$message = __( 'WordPress could not install the latest GitHub release. Please try again shortly.', 'on-model-commerce' );
		$class   = 'notice notice-error is-dismissible';
	} else {
		return;
	}
	echo '<div class="' . esc_attr( $class ) . '"><p>' . esc_html( $message ) . '</p></div>';
}

add_filter( 'plugin_action_links', 'aip_github_updater_action_links', 10, 2 );
add_action( 'admin_post_aip_github_update', 'aip_github_updater_run' );
add_action( 'admin_notices', 'aip_github_updater_notice' );
}

// Keep the native intake configuration outside the commerce class. A legacy
// preloaded class must not be able to remove the WooCommerce checkout handoff.
if ( ! function_exists( 'aip_reii_same_origin_checkout_url_v0557' ) ) {
function aip_reii_same_origin_checkout_url_v0557( $embedded = true ) {
	$checkout_url = wc_get_checkout_url();
	if ( $embedded ) {
		$checkout_url = add_query_arg( 'aip_embedded', '1', $checkout_url );
	}
	$relative_url = wp_make_link_relative( $checkout_url );
	return $relative_url ? $relative_url : '/checkout/';
}
}

if ( ! function_exists( 'aip_reii_checkout_fallback_bridge' ) ) {
function aip_reii_checkout_fallback_bridge() {
	if ( ! is_page( array( 'style-by-reii', 'on-model-content' ) ) || ! function_exists( 'wc_get_checkout_url' ) ) {
		return;
	}

	wp_enqueue_script( 'jquery' );
	$ajax_path = wp_parse_url( admin_url( 'admin-ajax.php' ), PHP_URL_PATH );
	$config = array(
		// Keep the request on the visible host so the REii subdomain carries its
		// WooCommerce session cookie without triggering a cross-origin fetch.
		'ajaxUrl'     => $ajax_path ? $ajax_path : '/wp-admin/admin-ajax.php',
		'checkoutUrl' => aip_reii_same_origin_checkout_url_v0557(),
		'nonce'       => wp_create_nonce( 'aip_reii_prepare_checkout' ),
	);
	$script = 'window.aipNativeCheckoutConfig=' . wp_json_encode( $config ) . ';';
	wp_add_inline_script( 'jquery', $script, 'after' );
}
add_action( 'wp_enqueue_scripts', 'aip_reii_checkout_fallback_bridge' );
}

// Show the version WordPress is actually running at the end of the REii page.
// Reading the plugin header here keeps the customer-facing marker honest when
// the source repository is newer than the plugin installed on the site.
if ( ! function_exists( 'aip_reii_render_live_version_v0555' ) ) {
function aip_reii_render_live_version_v0555() {
	if ( ! is_page( array( 'style-by-reii', 'on-model-content' ) ) ) {
		return;
	}

	$plugin_data = get_file_data( __FILE__, array( 'version' => 'Version' ), 'plugin' );
	$version     = isset( $plugin_data['version'] ) ? sanitize_text_field( $plugin_data['version'] ) : '';
	if ( '' === $version ) {
		return;
	}

	echo '<style id="aip-reii-live-version-style">.aip-reii-live-version{box-sizing:border-box;margin:0;padding:10px 24px 18px;text-align:center;background:#f7f5f1;color:#8b8490;font:600 10px/1.4 Inter,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;letter-spacing:.06em}</style>';
	echo '<p class="aip-reii-live-version">' . esc_html( 'REii Commerce v' . $version ) . '</p>';
}
add_action( 'wp_footer', 'aip_reii_render_live_version_v0555', 5 );
}

// Keep the embedded digital checkout outside the commerce class as well. Some
// migrated hosts preload an older copy of the class before this plugin, but the
// current popup must still get a compact, address-free Stripe checkout.
if ( ! function_exists( 'aip_reii_is_embedded_checkout' ) ) {
function aip_reii_has_service_checkout_context() {
	if ( isset( $_GET['aip_embedded'] ) ) {
		return true;
	}
	if ( ! function_exists( 'WC' ) ) {
		return false;
	}
	if ( WC()->session && WC()->session->get( 'aip_intake' ) ) {
		return true;
	}
	if ( WC()->cart ) {
		foreach ( WC()->cart->get_cart() as $cart_item ) {
			$product = isset( $cart_item['data'] ) ? $cart_item['data'] : null;
			if ( $product && 'on-model-content-order' === $product->get_sku() ) {
				return true;
			}
		}
	}
	return false;
}

function aip_reii_is_reii_order_v0551( $order ) {
	if ( ! is_a( $order, 'WC_Order' ) ) {
		return false;
	}
	foreach ( $order->get_items() as $item ) {
		$product = is_callable( array( $item, 'get_product' ) ) ? $item->get_product() : false;
		if ( $product && 'on-model-content-order' === $product->get_sku() ) {
			return true;
		}
	}
	return false;
}

function aip_reii_order_received_order_v0551() {
	if ( ! function_exists( 'is_wc_endpoint_url' ) || ! is_wc_endpoint_url( 'order-received' ) || ! function_exists( 'wc_get_order' ) ) {
		return false;
	}
	global $wp;
	$order_id = isset( $wp->query_vars['order-received'] ) ? absint( $wp->query_vars['order-received'] ) : 0;
	return $order_id ? wc_get_order( $order_id ) : false;
}

function aip_reii_is_embedded_checkout() {
	if ( ! function_exists( 'is_checkout' ) || ! is_checkout() ) {
		return false;
	}
	return aip_reii_has_service_checkout_context() || aip_reii_is_reii_order_v0551( aip_reii_order_received_order_v0551() );
}

function aip_reii_default_billing_country() {
	$country = 'US';
	if ( function_exists( 'WC' ) && WC()->countries ) {
		$base_country = strtoupper( (string) WC()->countries->get_base_country() );
		$countries    = WC()->countries->get_countries();
		if ( isset( $countries[ $base_country ] ) ) {
			$country = $base_country;
		}
	}
	return $country;
}

function aip_reii_seed_checkout_country() {
	if ( ! aip_reii_has_service_checkout_context() || ! function_exists( 'WC' ) || ! WC()->customer ) {
		return;
	}
	if ( ! WC()->customer->get_billing_country( 'edit' ) ) {
		WC()->customer->set_billing_country( aip_reii_default_billing_country() );
	}
	if ( ! WC()->customer->get_shipping_country( 'edit' ) ) {
		WC()->customer->set_shipping_country( aip_reii_default_billing_country() );
	}
}

function aip_reii_embedded_body_class( $classes ) {
	if ( aip_reii_is_embedded_checkout() ) {
		$classes[] = 'aip-embedded-checkout';
		if ( aip_reii_is_reii_order_v0551( aip_reii_order_received_order_v0551() ) ) {
			$classes[] = 'aip-checkout-complete';
		}
		show_admin_bar( false );
	}
	return $classes;
}

function aip_reii_embedded_default_fields( $fields ) {
	if ( ! aip_reii_has_service_checkout_context() ) {
		return $fields;
	}
	foreach ( $fields as $key => $field ) {
		$fields[ $key ]['required'] = false;
	}
	return $fields;
}

function aip_reii_embedded_checkout_fields( $fields ) {
	if ( ! aip_reii_has_service_checkout_context() || empty( $fields['billing'] ) ) {
		return $fields;
	}
	$email   = isset( $fields['billing']['billing_email'] ) ? $fields['billing']['billing_email'] : array();
	$country = isset( $fields['billing']['billing_country'] ) ? $fields['billing']['billing_country'] : array();
	$email['type']     = 'hidden';
	$email['required'] = false;
	$email['label']    = '';
	$country['type']     = 'hidden';
	$country['required'] = false;
	$country['label']    = '';
	$country['default']  = aip_reii_default_billing_country();
	$fields['billing'] = array(
		'billing_email'   => $email,
		'billing_country' => $country,
	);
	unset( $fields['shipping'], $fields['order'] );
	return $fields;
}

function aip_reii_embedded_checkout_value( $value, $input ) {
	if ( aip_reii_has_service_checkout_context() && 'billing_country' === $input && empty( $value ) ) {
		return aip_reii_default_billing_country();
	}
	return $value;
}

function aip_reii_embedded_customer_country( $value ) {
	if ( aip_reii_has_service_checkout_context() && empty( $value ) ) {
		return aip_reii_default_billing_country();
	}
	return $value;
}

function aip_reii_store_api_billing_country( $order, $request ) {
	if ( ! aip_reii_has_service_checkout_context() || ! is_a( $order, 'WC_Order' ) ) {
		return;
	}
	if ( ! $order->get_billing_country() ) {
		$order->set_billing_country( aip_reii_default_billing_country() );
	}
	$intake = function_exists( 'WC' ) && WC()->session ? WC()->session->get( 'aip_intake' ) : array();
	$email  = ! empty( $intake['email'] ) ? sanitize_email( $intake['email'] ) : '';
	if ( $email && is_email( $email ) ) {
		// The email entered in the current REii intake must override any stale
		// billing email restored by WooCommerce from an earlier checkout.
		$order->set_billing_email( $email );
		$order->update_meta_data( '_aip_intake_email', $email );
	}
}

function aip_reii_embedded_billing_fields( $fields ) {
	if ( ! aip_reii_has_service_checkout_context() ) {
		return $fields;
	}
	foreach ( $fields as $key => $field ) {
		$fields[ $key ]['required'] = false;
	}
	return $fields;
}

function aip_reii_remove_service_address_errors( $data, $errors ) {
	if ( ! aip_reii_has_service_checkout_context() || ! is_wp_error( $errors ) ) {
		return;
	}
	$address_fields = '(first_name|last_name|company|country|address_1|address_2|city|state|postcode|phone)';
	foreach ( $errors->get_error_codes() as $code ) {
		if ( preg_match( '/^billing_' . $address_fields . '(_required)?$/', (string) $code ) ) {
			$errors->remove( $code );
		}
	}
}

function aip_reii_checkout_intro_v0551( $checkout = null ) {
	if ( ! aip_reii_has_service_checkout_context() || ( function_exists( 'is_wc_endpoint_url' ) && is_wc_endpoint_url( 'order-received' ) ) ) {
		return;
	}
	$intake = function_exists( 'WC' ) && WC()->session ? WC()->session->get( 'aip_intake' ) : array();
	$email  = ! empty( $intake['email'] ) ? sanitize_email( $intake['email'] ) : '';
	?>
	<section class="aip-checkout-intro" aria-labelledby="aip-checkout-intro-title">
		<small>SECURE PAYMENT</small>
		<h1 id="aip-checkout-intro-title">Choose how you&rsquo;d like to pay.</h1>
		<p>Your product details are saved. Review the total, then complete protected payment.</p>
		<?php if ( $email && is_email( $email ) ) : ?>
			<p class="aip-checkout-email-note">Order confirmation and private delivery will be sent to <strong><?php echo esc_html( $email ); ?></strong>.</p>
		<?php endif; ?>
	</section>
	<?php
}

function aip_reii_checkout_button_text_v0551( $text ) {
	return aip_reii_has_service_checkout_context() ? 'Pay securely' : $text;
}

function aip_reii_confirmation_intro_v0551( $order_id ) {
	$order = function_exists( 'wc_get_order' ) ? wc_get_order( $order_id ) : false;
	if ( ! aip_reii_is_reii_order_v0551( $order ) ) {
		return;
	}
	?>
	<section class="aip-confirmation-hero" aria-labelledby="aip-confirmation-title">
		<div class="aip-confirmation-mark" aria-hidden="true">
			<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m5 12 4 4L19 6"/></svg>
		</div>
		<small>PAYMENT COMPLETE &middot; ORDER #<?php echo esc_html( $order->get_order_number() ); ?></small>
		<h1 id="aip-confirmation-title">Your order is confirmed.</h1>
		<p>REii will creatively direct, render, and quality-check your AI influencer UGC video. We&rsquo;ll email your private delivery link when it&rsquo;s ready.</p>
		<ol class="aip-confirmation-next" aria-label="What happens next">
			<li><b>01</b><span>Product received</span></li>
			<li><b>02</b><span>REii creates</span></li>
			<li><b>03</b><span>Private delivery</span></li>
		</ol>
		<a class="aip-confirmation-again" href="<?php echo esc_url( home_url( '/style-by-reii/#submit-project' ) ); ?>" target="_top">Create another video</a>
	</section>
	<?php
}

function aip_reii_checkout_parent_bridge_v0551() {
	if ( ! aip_reii_is_embedded_checkout() ) {
		return;
	}
	$is_complete = aip_reii_is_reii_order_v0551( aip_reii_order_received_order_v0551() );
	wp_enqueue_script( 'jquery' );
	$script = "(function(){if(window.parent===window)return;function notify(){window.parent.postMessage({type:'aipCheckoutReady'},window.location.origin);" . ( $is_complete ? "window.parent.postMessage({type:'aipCheckoutComplete'},window.location.origin);" : '' ) . "}if(document.readyState==='loading'){document.addEventListener('DOMContentLoaded',notify,{once:true});}else{notify();}})();";
	wp_add_inline_script( 'jquery', $script, 'after' );
}

function aip_reii_checkout_theme_css_v0551() {
	return '
	body.woocommerce-checkout.aip-embedded-checkout{--aip-paper:#f7f5f1;--aip-ink:#17151d;--aip-dark:#19161d;--aip-purple:#5d32ea;--aip-soft:#f2efff;--aip-line:#ded9df;background:var(--aip-paper)!important;color:var(--aip-ink)!important;font-family:Inter,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif!important;margin:0!important}
	body.woocommerce-checkout.aip-embedded-checkout .main-container,body.woocommerce-checkout.aip-embedded-checkout .page-body,body.woocommerce-checkout.aip-embedded-checkout .sections-container{background:var(--aip-paper)!important}
	body.woocommerce-checkout.aip-embedded-checkout .row-parent{margin:0 auto!important;max-width:700px!important;padding:28px 24px 48px!important}
	body.woocommerce-checkout.aip-embedded-checkout .aip-checkout-intro{background:var(--aip-dark);border-radius:24px;color:#fff;margin:0 0 22px;overflow:hidden;padding:25px 28px 27px;position:relative}
	body.woocommerce-checkout.aip-embedded-checkout .aip-checkout-intro:after{background:radial-gradient(circle,rgba(126,86,255,.72),transparent 68%);content:"";height:190px;position:absolute;right:-65px;top:-105px;width:230px}
	body.woocommerce-checkout.aip-embedded-checkout .aip-checkout-intro small{color:#a993f4;font-size:8px;font-weight:850;letter-spacing:.22em;position:relative;z-index:1}
	body.woocommerce-checkout.aip-embedded-checkout .aip-checkout-intro h1{color:#fff;font-family:Georgia,"Times New Roman",serif;font-size:29px;font-weight:500;letter-spacing:-.04em;line-height:1.05;margin:8px 0 9px;position:relative;z-index:1}
	body.woocommerce-checkout.aip-embedded-checkout .aip-checkout-intro p{color:#c7c0cb;font-size:10px;line-height:1.55;margin:0;max-width:470px;position:relative;z-index:1}
	body.woocommerce-checkout.aip-embedded-checkout .aip-checkout-intro .aip-checkout-email-note{background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.12);border-radius:12px;color:#eeeaf2;margin-top:16px;max-width:none;padding:12px 14px}
	body.woocommerce-checkout.aip-embedded-checkout .aip-checkout-intro .aip-checkout-email-note strong{color:#fff;overflow-wrap:anywhere}
	body.woocommerce-checkout.aip-embedded-checkout #wc-stripe-express-checkout-element,body.woocommerce-checkout.aip-embedded-checkout .wc-block-express-payment,body.woocommerce-checkout.aip-embedded-checkout .wc-block-components-express-payment{background:#fff!important;border:1px solid var(--aip-line)!important;border-radius:20px!important;box-shadow:0 14px 40px rgba(31,24,38,.06)!important;padding:18px!important}
	body.woocommerce-checkout.aip-embedded-checkout .payment_methods,body.woocommerce-checkout.aip-embedded-checkout .wc-block-checkout__payment-method,body.woocommerce-checkout.aip-embedded-checkout .wc-block-components-checkout-step--payment{background:#fff!important;border:1px solid var(--aip-line)!important;border-radius:20px!important;box-shadow:0 14px 40px rgba(31,24,38,.06)!important;overflow:hidden!important;padding:18px!important}
	body.woocommerce-checkout.aip-embedded-checkout .shop_table.woocommerce-checkout-review-order-table,body.woocommerce-checkout.aip-embedded-checkout .wc-block-components-sidebar,body.woocommerce-checkout.aip-embedded-checkout .wc-block-checkout__sidebar{background:#fff!important;border:1px solid var(--aip-line)!important;border-radius:20px!important;box-shadow:0 14px 40px rgba(31,24,38,.06)!important;overflow:hidden!important}
	body.woocommerce-checkout.aip-embedded-checkout .shop_table th,body.woocommerce-checkout.aip-embedded-checkout .shop_table td{border-color:#ece7ec!important;padding:16px 18px!important}
	body.woocommerce-checkout.aip-embedded-checkout .shop_table thead th{background:#fbfaf8!important;color:#6c6570!important;font-size:9px!important;font-weight:850!important;letter-spacing:.1em!important;text-transform:uppercase!important}
	body.woocommerce-checkout.aip-embedded-checkout .shop_table .order-total th,body.woocommerce-checkout.aip-embedded-checkout .shop_table .order-total td{background:#faf8ff!important}
	body.woocommerce-checkout.aip-embedded-checkout .shop_table .order-total .amount{color:var(--aip-purple)!important;font-size:18px!important}
	body.woocommerce-checkout.aip-embedded-checkout .woocommerce-form-coupon-toggle,body.woocommerce-checkout.aip-embedded-checkout .wc-block-components-totals-coupon{border-bottom:1px solid var(--aip-line)!important;border-top:1px solid var(--aip-line)!important;color:#625b68!important;font-size:16px!important;line-height:1.5!important;padding:15px 2px!important}
	body.woocommerce-checkout.aip-embedded-checkout .woocommerce-form-coupon-toggle .woocommerce-info{font-size:16px!important;line-height:1.5!important}
	body.woocommerce-checkout.aip-embedded-checkout input,body.woocommerce-checkout.aip-embedded-checkout select,body.woocommerce-checkout.aip-embedded-checkout textarea{border-color:#d7d0da!important;border-radius:10px!important;min-height:48px!important}
	body.woocommerce-checkout.aip-embedded-checkout input:focus,body.woocommerce-checkout.aip-embedded-checkout select:focus,body.woocommerce-checkout.aip-embedded-checkout textarea:focus{border-color:var(--aip-purple)!important;box-shadow:0 0 0 3px rgba(93,50,234,.12)!important;outline:0!important}
	body.woocommerce-checkout.aip-embedded-checkout #place_order,body.woocommerce-checkout.aip-embedded-checkout .wc-block-components-checkout-place-order-button{background:var(--aip-purple)!important;border:0!important;border-radius:14px!important;box-shadow:0 14px 30px rgba(93,50,234,.22)!important;color:#fff!important;font-size:12px!important;font-weight:800!important;min-height:58px!important;transition:background .2s ease,transform .2s ease!important;width:100%!important}
	body.woocommerce-checkout.aip-embedded-checkout #place_order:hover,body.woocommerce-checkout.aip-embedded-checkout .wc-block-components-checkout-place-order-button:hover{background:#4823c8!important;transform:translateY(-1px)}
	body.woocommerce-checkout.aip-embedded-checkout button:focus-visible,body.woocommerce-checkout.aip-embedded-checkout a:focus-visible{outline:3px solid rgba(93,50,234,.3)!important;outline-offset:3px!important}
	body.woocommerce-checkout.aip-checkout-complete .row-parent{max-width:760px!important;padding:32px 24px 54px!important}
	body.woocommerce-checkout.aip-checkout-complete .woocommerce-order{background:transparent!important}
	body.woocommerce-checkout.aip-checkout-complete .aip-confirmation-hero{background:radial-gradient(circle at 86% -12%,rgba(119,77,255,.55),transparent 39%),var(--aip-dark);border-radius:28px;color:#fff;margin:0 0 22px;overflow:hidden;padding:42px 44px 38px;position:relative;text-align:left}
	body.woocommerce-checkout.aip-checkout-complete .aip-confirmation-mark{align-items:center;background:var(--aip-soft);border-radius:50%;color:var(--aip-purple);display:flex;height:58px;justify-content:center;margin-bottom:27px;width:58px}
	body.woocommerce-checkout.aip-checkout-complete .aip-confirmation-mark svg{height:26px;width:26px}
	body.woocommerce-checkout.aip-checkout-complete .aip-confirmation-hero>small{color:#ac9af2;font-size:8px;font-weight:850;letter-spacing:.2em}
	body.woocommerce-checkout.aip-checkout-complete .aip-confirmation-hero h1{color:#fff;font-family:Georgia,"Times New Roman",serif;font-size:42px;font-weight:500;letter-spacing:-.045em;line-height:1;margin:10px 0 15px}
	body.woocommerce-checkout.aip-checkout-complete .aip-confirmation-hero>p{color:#cbc4cf;font-size:12px;line-height:1.65;margin:0;max-width:590px}
	body.woocommerce-checkout.aip-checkout-complete .aip-confirmation-next{display:grid;gap:1px;grid-template-columns:repeat(3,1fr);list-style:none;margin:30px 0 24px;padding:0}
	body.woocommerce-checkout.aip-checkout-complete .aip-confirmation-next li{background:rgba(255,255,255,.07);border:1px solid rgba(255,255,255,.08);min-height:76px;padding:15px}
	body.woocommerce-checkout.aip-checkout-complete .aip-confirmation-next li:first-child{border-radius:13px 0 0 13px}
	body.woocommerce-checkout.aip-checkout-complete .aip-confirmation-next li:last-child{border-radius:0 13px 13px 0}
	body.woocommerce-checkout.aip-checkout-complete .aip-confirmation-next b,body.woocommerce-checkout.aip-checkout-complete .aip-confirmation-next span{display:block}
	body.woocommerce-checkout.aip-checkout-complete .aip-confirmation-next b{color:#9c84f7;font-size:8px;letter-spacing:.12em;margin-bottom:7px}
	body.woocommerce-checkout.aip-checkout-complete .aip-confirmation-next span{color:#fff;font-size:10px;font-weight:750}
	body.woocommerce-checkout.aip-checkout-complete .aip-confirmation-again{align-items:center;background:#fff;border:0;border-radius:0;box-shadow:none;color:#17151d!important;display:inline-flex;font-size:16px!important;font-weight:800;justify-content:center;letter-spacing:.06em;min-height:48px;padding:0 24px;text-decoration:none!important;text-transform:uppercase;transition:background .2s ease,color .2s ease}
	body.woocommerce-checkout.aip-checkout-complete .aip-confirmation-again:hover{background:var(--aip-purple);color:#fff!important}
	body.woocommerce-checkout.aip-checkout-complete .aip-confirmation-again:active{background:#4823c8;color:#fff!important}
	body.woocommerce-checkout.aip-checkout-complete .woocommerce-thankyou-order-received{display:none!important}
	body.woocommerce-checkout.aip-checkout-complete .woocommerce-order-overview{background:var(--aip-soft)!important;border:1px solid #ddd3f4!important;border-radius:20px!important;display:grid!important;gap:0!important;grid-template-columns:repeat(2,minmax(0,1fr))!important;margin:0 0 22px!important;overflow:hidden!important;padding:0!important}
	body.woocommerce-checkout.aip-checkout-complete .woocommerce-order-overview li{border-bottom:1px solid #ddd3f4!important;border-right:1px solid #ddd3f4!important;float:none!important;font-size:8px!important;margin:0!important;min-width:0!important;padding:17px 20px!important;text-transform:uppercase!important}
	body.woocommerce-checkout.aip-checkout-complete .woocommerce-order-overview li:nth-child(even){border-right:0!important}
	body.woocommerce-checkout.aip-checkout-complete .woocommerce-order-overview li strong{color:var(--aip-ink)!important;font-size:12px!important;margin-top:6px!important;overflow-wrap:anywhere!important;text-transform:none!important}
	body.woocommerce-checkout.aip-checkout-complete .woocommerce-order-details{background:#fff;border:1px solid var(--aip-line);border-radius:22px;box-shadow:0 16px 44px rgba(31,24,38,.06);margin:0 0 22px!important;overflow:hidden;padding:24px}
	body.woocommerce-checkout.aip-checkout-complete .woocommerce-order-details h2{font-family:Georgia,"Times New Roman",serif;font-size:25px;font-weight:500;letter-spacing:-.035em;margin:0 0 16px}
	body.woocommerce-checkout.aip-checkout-complete .woocommerce-order-details table{border:0!important;margin:0!important}
	body.woocommerce-checkout.aip-checkout-complete .woocommerce-customer-details{display:none!important}
	body.woocommerce-checkout.aip-checkout-complete .aip-confirmation-thumbs img{border-color:#ddd3f4!important;border-radius:11px!important;height:78px!important;width:64px!important}
	.aip-checkout-panel{background:var(--aip-paper,#f7f5f1);border-radius:28px 0 0 28px;box-shadow:-28px 0 90px rgba(12,8,17,.28);overflow:hidden}.aip-checkout-panel header{background:#19161d;border:0;color:#fff;height:88px}.aip-checkout-panel header small{color:#a994f4}.aip-checkout-panel header strong{color:#fff;font-family:Georgia,"Times New Roman",serif}.aip-checkout-close{background:rgba(255,255,255,.1);color:#fff}.aip-checkout-panel iframe{background:#f7f5f1;height:calc(100% - 88px)}.aip-checkout-loading span{border-top-color:#5d32ea}
	@media(max-width:600px){body.woocommerce-checkout.aip-embedded-checkout .row-parent{padding:18px 14px 36px!important}body.woocommerce-checkout.aip-embedded-checkout .aip-checkout-intro{border-radius:18px;padding:22px 20px}body.woocommerce-checkout.aip-embedded-checkout .aip-checkout-intro h1{font-size:25px}body.woocommerce-checkout.aip-embedded-checkout #wc-stripe-express-checkout-element,body.woocommerce-checkout.aip-embedded-checkout .payment_methods,body.woocommerce-checkout.aip-embedded-checkout .shop_table.woocommerce-checkout-review-order-table{border-radius:16px!important}body.woocommerce-checkout.aip-checkout-complete .aip-confirmation-hero{border-radius:22px;padding:32px 22px 27px}body.woocommerce-checkout.aip-checkout-complete .aip-confirmation-hero h1{font-size:34px}body.woocommerce-checkout.aip-checkout-complete .aip-confirmation-next{grid-template-columns:1fr}body.woocommerce-checkout.aip-checkout-complete .aip-confirmation-next li,body.woocommerce-checkout.aip-checkout-complete .aip-confirmation-next li:first-child,body.woocommerce-checkout.aip-checkout-complete .aip-confirmation-next li:last-child{border-radius:10px;min-height:0}body.woocommerce-checkout.aip-checkout-complete .woocommerce-order-overview{grid-template-columns:1fr!important}body.woocommerce-checkout.aip-checkout-complete .woocommerce-order-overview li{border-right:0!important}.aip-checkout-panel{border-radius:0;max-width:none;width:100%}}
	body.woocommerce-checkout.aip-embedded-checkout :where(p,small,label,span,strong,em,b,a,button,input,select,textarea,option,th,td,li,dt,dd,h4,h5,h6){font-size:16px!important;line-height:1.5!important}
	body.woocommerce-checkout.aip-embedded-checkout .shop_table .order-total .amount{font-size:18px!important}
	body.woocommerce-checkout.aip-embedded-checkout .aip-checkout-upload-file{font-size:20px!important}
	@media(prefers-reduced-motion:reduce){body.woocommerce-checkout.aip-embedded-checkout #place_order,body.woocommerce-checkout.aip-embedded-checkout .wc-block-components-checkout-place-order-button,body.woocommerce-checkout.aip-checkout-complete .aip-confirmation-again{transition:none!important}}
	';
}

function aip_reii_embedded_checkout_compat_styles() {
	if ( ! aip_reii_is_embedded_checkout() ) {
		return;
	}
	$css = '
	body.woocommerce-checkout.aip-embedded-checkout #wpadminbar,body.woocommerce-checkout.aip-embedded-checkout #masthead,body.woocommerce-checkout.aip-embedded-checkout #colophon,body.woocommerce-checkout.aip-embedded-checkout .post-title-wrapper{display:none!important}html{margin-top:0!important}body.woocommerce-checkout.aip-embedded-checkout{background:#f8f7fb!important;margin:0!important}body.woocommerce-checkout.aip-embedded-checkout .main-container,body.woocommerce-checkout.aip-embedded-checkout .page-body{background:#f8f7fb!important;padding:0!important}body.woocommerce-checkout.aip-embedded-checkout .row-parent{margin:0 auto!important;max-width:620px!important;padding:22px 20px 36px!important}body.woocommerce-checkout.aip-embedded-checkout .woocommerce-billing-fields,body.woocommerce-checkout.aip-embedded-checkout .woocommerce-shipping-fields,body.woocommerce-checkout.aip-embedded-checkout .woocommerce-additional-fields,body.woocommerce-checkout.aip-embedded-checkout #customer_details,body.woocommerce-checkout.aip-embedded-checkout #order_review_heading,body.woocommerce-checkout.aip-embedded-checkout .woocommerce-form-login-toggle,body.woocommerce-checkout.aip-embedded-checkout form.woocommerce-form-login,body.woocommerce-checkout.aip-embedded-checkout .wc-block-checkout__login-prompt,body.woocommerce-checkout.aip-embedded-checkout .wc-block-components-checkout-returning-customer{display:none!important}body.woocommerce-checkout.aip-embedded-checkout .woocommerce{display:flex!important;flex-direction:column!important}body.woocommerce-checkout.aip-embedded-checkout form.checkout.woocommerce-checkout,body.woocommerce-checkout.aip-embedded-checkout #order_review,body.woocommerce-checkout.aip-embedded-checkout #payment{display:contents!important}body.woocommerce-checkout.aip-embedded-checkout #wc-stripe-express-checkout-element{order:10!important}body.woocommerce-checkout.aip-embedded-checkout #wc-stripe-express-checkout-button-separator{order:20!important}body.woocommerce-checkout.aip-embedded-checkout .payment_methods{margin:0 0 18px!important;order:30!important}body.woocommerce-checkout.aip-embedded-checkout .shop_table.woocommerce-checkout-review-order-table{background:#fff!important;border:1px solid #e5dfea!important;border-radius:16px!important;box-shadow:0 12px 35px rgba(35,27,45,.06)!important;margin:0 0 18px!important;order:40!important;overflow:hidden!important;padding:0!important;width:100%!important}body.woocommerce-checkout.aip-embedded-checkout .woocommerce-form-coupon-toggle{margin:0 0 10px!important;order:50!important}body.woocommerce-checkout.aip-embedded-checkout form.checkout_coupon{margin:0 0 18px!important;order:51!important}body.woocommerce-checkout.aip-embedded-checkout .place-order{margin-top:0!important;order:60!important}body.woocommerce-checkout.aip-embedded-checkout button,body.woocommerce-checkout.aip-embedded-checkout .button{min-height:52px!important}@media(max-width:600px){body.woocommerce-checkout.aip-embedded-checkout .row-parent{padding:16px 14px 30px!important}}
	';
	$css .= aip_reii_checkout_theme_css_v0551();
	wp_register_style( 'aip-reii-embedded-compat', false, array(), '0.5.72' );
	wp_enqueue_style( 'aip-reii-embedded-compat' );
	wp_add_inline_style( 'aip-reii-embedded-compat', $css );
}

function aip_reii_disable_embedded_checkout_login_reminder_v0555( $value ) {
	return aip_reii_has_service_checkout_context() ? 'no' : $value;
}

function aip_reii_enable_guest_checkout_v0558( $value ) {
	return aip_reii_has_service_checkout_context() ? 'yes' : $value;
}

function aip_reii_disable_checkout_registration_v0558( $value ) {
	return aip_reii_has_service_checkout_context() ? 'no' : $value;
}

function aip_reii_registration_not_required_v0558( $required ) {
	return aip_reii_has_service_checkout_context() ? false : $required;
}

function aip_reii_registration_not_enabled_v0558( $enabled ) {
	return aip_reii_has_service_checkout_context() ? false : $enabled;
}

function aip_reii_force_guest_posted_checkout_v0558( $data ) {
	if ( aip_reii_has_service_checkout_context() && is_array( $data ) ) {
		$data['createaccount'] = 0;
	}
	return $data;
}

add_filter( 'body_class', 'aip_reii_embedded_body_class', 999 );
add_filter( 'option_woocommerce_enable_checkout_login_reminder', 'aip_reii_disable_embedded_checkout_login_reminder_v0555', 999 );
add_filter( 'option_woocommerce_enable_guest_checkout', 'aip_reii_enable_guest_checkout_v0558', 999 );
add_filter( 'option_woocommerce_enable_signup_and_login_from_checkout', 'aip_reii_disable_checkout_registration_v0558', 999 );
add_filter( 'woocommerce_checkout_registration_required', 'aip_reii_registration_not_required_v0558', 999 );
add_filter( 'woocommerce_checkout_registration_enabled', 'aip_reii_registration_not_enabled_v0558', 999 );
add_filter( 'woocommerce_checkout_posted_data', 'aip_reii_force_guest_posted_checkout_v0558', 999 );
add_action( 'wp', 'aip_reii_seed_checkout_country', 1 );
add_filter( 'woocommerce_default_address_fields', 'aip_reii_embedded_default_fields', 999 );
add_filter( 'woocommerce_billing_fields', 'aip_reii_embedded_billing_fields', 999 );
add_filter( 'woocommerce_checkout_fields', 'aip_reii_embedded_checkout_fields', 999 );
add_filter( 'woocommerce_checkout_get_value', 'aip_reii_embedded_checkout_value', 999, 2 );
add_filter( 'woocommerce_customer_get_billing_country', 'aip_reii_embedded_customer_country', 999 );
add_action( 'woocommerce_after_checkout_validation', 'aip_reii_remove_service_address_errors', 999, 2 );
add_action( 'woocommerce_store_api_checkout_update_order_from_request', 'aip_reii_store_api_billing_country', 999, 2 );
add_filter( 'woocommerce_order_button_text', 'aip_reii_checkout_button_text_v0551', 999 );
add_action( 'woocommerce_before_checkout_form', 'aip_reii_checkout_intro_v0551', 5, 1 );
add_action( 'woocommerce_before_thankyou', 'aip_reii_confirmation_intro_v0551', 5, 1 );
add_action( 'wp_enqueue_scripts', 'aip_reii_embedded_checkout_compat_styles', 999 );
add_action( 'wp_enqueue_scripts', 'aip_reii_checkout_parent_bridge_v0551', 1000 );
}

// Treat the embedded payment screen as a one-product direct purchase. This is
// intentionally independent of the commerce class so a legacy duplicate class
// cannot send a customer to an empty WooCommerce cart.
if ( ! function_exists( 'aip_reii_direct_purchase_product' ) ) {
function aip_reii_direct_purchase_product() {
	if ( ! function_exists( 'wc_get_product_id_by_sku' ) || ! function_exists( 'wc_get_product' ) ) {
		return null;
	}
	$product_id = wc_get_product_id_by_sku( 'on-model-content-order' );
	return $product_id ? wc_get_product( $product_id ) : null;
}

function aip_reii_ensure_direct_purchase_product() {
	if ( ! class_exists( 'WC_Product_Simple' ) ) {
		return;
	}
	$product = aip_reii_direct_purchase_product();
	if ( ! $product ) {
		$product = new WC_Product_Simple();
		$product->set_sku( 'on-model-content-order' );
	}
	$changed = false;
	$fields  = array(
		'name'               => 'REii AI-Generated UGC Video',
		'slug'               => 'style-by-reii-shoppable-video-feature',
		'status'             => 'publish',
		'catalog_visibility' => 'hidden',
		'regular_price'      => '10',
		'price'              => '10',
	);
	foreach ( $fields as $field => $value ) {
		$getter = 'get_' . $field;
		$setter = 'set_' . $field;
		if ( (string) $product->{$getter}( 'edit' ) !== (string) $value ) {
			$product->{$setter}( $value );
			$changed = true;
		}
	}
	if ( ! $product->is_virtual() ) {
		$product->set_virtual( true );
		$changed = true;
	}
	if ( ! $product->get_sold_individually( 'edit' ) ) {
		$product->set_sold_individually( true );
		$changed = true;
	}
	if ( $changed || ! $product->get_id() ) {
		$product_id = $product->save();
		if ( $product_id ) {
			update_option( 'aip_on_model_product_id', $product_id, false );
		}
	}
}

function aip_reii_cart_has_direct_purchase() {
	if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
		return false;
	}
	foreach ( WC()->cart->get_cart() as $cart_item ) {
		$product = isset( $cart_item['data'] ) ? $cart_item['data'] : null;
		if ( $product && 'on-model-content-order' === $product->get_sku() ) {
			return true;
		}
	}
	return false;
}

function aip_reii_persist_fallback_file( $file ) {
	if ( ! $file || ! is_readable( $file ) ) {
		return null;
	}
	$uploads = wp_upload_dir();
	if ( ! empty( $uploads['error'] ) ) {
		return null;
	}
	$relative_dir = 'aip-order-intake/' . gmdate( 'Y/m' );
	$target_dir   = trailingslashit( $uploads['basedir'] ) . $relative_dir;
	if ( ! wp_mkdir_p( $target_dir ) ) {
		return null;
	}
	$source_name = sanitize_file_name( basename( $file ) );
	$file_name   = wp_unique_filename( $target_dir, substr( wp_generate_uuid4(), 0, 8 ) . '-' . $source_name );
	$target      = trailingslashit( $target_dir ) . $file_name;
	if ( ! copy( $file, $target ) ) {
		return null;
	}
	$file_type = wp_check_filetype( $file_name );
	return array(
		'name' => $source_name,
		'url'  => trailingslashit( $uploads['baseurl'] ) . $relative_dir . '/' . rawurlencode( $file_name ),
		'type' => isset( $file_type['type'] ) ? $file_type['type'] : '',
		'size' => (int) filesize( $target ),
	);
}

function aip_reii_native_checkout_error_v0554( $message, $status = 400 ) {
	wp_send_json_error( array( 'message' => $message ), $status );
}

function aip_reii_persist_native_upload_v0554( $upload ) {
	if ( ! is_array( $upload ) || UPLOAD_ERR_OK !== (int) ( $upload['error'] ?? UPLOAD_ERR_NO_FILE ) ) {
		return new WP_Error( 'aip_upload_failed', 'One of the product files could not be uploaded.' );
	}
	$size = (int) ( $upload['size'] ?? 0 );
	if ( $size < 1 || $size > 20 * MB_IN_BYTES ) {
		return new WP_Error( 'aip_upload_size', 'Each product file must be 20 MB or smaller.' );
	}
	$source_name = sanitize_file_name( (string) ( $upload['name'] ?? '' ) );
	$extension   = strtolower( pathinfo( $source_name, PATHINFO_EXTENSION ) );
	$allowed     = array(
		'jpg'  => 'image/jpeg',
		'jpeg' => 'image/jpeg',
		'png'  => 'image/png',
		'webp' => 'image/webp',
		'pdf'  => 'application/pdf',
		'zip'  => 'application/zip',
	);
	if ( ! isset( $allowed[ $extension ] ) || empty( $upload['tmp_name'] ) || ! is_uploaded_file( $upload['tmp_name'] ) ) {
		return new WP_Error( 'aip_upload_type', 'Use a JPG, PNG, WEBP, PDF, or ZIP product file.' );
	}
	$uploads = wp_upload_dir();
	if ( ! empty( $uploads['error'] ) ) {
		return new WP_Error( 'aip_upload_storage', 'Product files could not be stored right now.' );
	}
	$relative_dir = 'aip-order-intake/' . gmdate( 'Y/m' );
	$target_dir   = trailingslashit( $uploads['basedir'] ) . $relative_dir;
	if ( ! wp_mkdir_p( $target_dir ) ) {
		return new WP_Error( 'aip_upload_storage', 'Product files could not be stored right now.' );
	}
	$file_name = wp_unique_filename( $target_dir, substr( wp_generate_uuid4(), 0, 8 ) . '-' . $source_name );
	$target    = trailingslashit( $target_dir ) . $file_name;
	if ( ! move_uploaded_file( $upload['tmp_name'], $target ) ) {
		return new WP_Error( 'aip_upload_storage', 'Product files could not be stored right now.' );
	}
	return array(
		'name' => $source_name,
		'url'  => trailingslashit( $uploads['baseurl'] ) . $relative_dir . '/' . rawurlencode( $file_name ),
		'type' => $allowed[ $extension ],
		'size' => (int) filesize( $target ),
	);
}

function aip_reii_prepare_native_checkout_v0554() {
	if ( ! check_ajax_referer( 'aip_reii_prepare_checkout', 'nonce', false ) ) {
		aip_reii_native_checkout_error_v0554( 'Your checkout session expired. Please refresh and try again.', 403 );
	}
	if ( ! function_exists( 'wc_get_product' ) || ! function_exists( 'wc_create_order' ) ) {
		aip_reii_native_checkout_error_v0554( 'Secure checkout is temporarily unavailable.', 503 );
	}

	$email     = sanitize_email( wp_unslash( $_POST['your-email'] ?? '' ) );
	$method    = sanitize_text_field( wp_unslash( $_POST['source-method'] ?? '' ) );
	$reference = sanitize_text_field( wp_unslash( $_POST['product-reference'] ?? '' ) );
	$rights    = '1' === (string) ( $_POST['rights-confirmed'] ?? '' );
	if ( ! $email || ! is_email( $email ) ) {
		aip_reii_native_checkout_error_v0554( 'Please provide a valid email address.' );
	}
	if ( ! in_array( $method, array( 'Amazon link / ASIN', 'Upload product files' ), true ) ) {
		aip_reii_native_checkout_error_v0554( 'Please choose an Amazon link or product-file upload.' );
	}
	if ( ! $rights ) {
		aip_reii_native_checkout_error_v0554( 'Please confirm you have permission to use these product details.' );
	}
	if ( 'Amazon link / ASIN' === $method && ! $reference ) {
		aip_reii_native_checkout_error_v0554( 'Please paste an Amazon link or ASIN.' );
	}

	$product = aip_reii_direct_purchase_product();
	if ( ! $product || ! $product->is_purchasable() ) {
		aip_reii_native_checkout_error_v0554( 'Checkout is temporarily unavailable. Please try again shortly.', 503 );
	}
	$files = array();
	if ( 'Upload product files' === $method ) {
		foreach ( range( 1, 4 ) as $index ) {
			$key = 'product-file-' . $index;
			if ( empty( $_FILES[ $key ] ) || UPLOAD_ERR_NO_FILE === (int) ( $_FILES[ $key ]['error'] ?? UPLOAD_ERR_NO_FILE ) ) {
				continue;
			}
			$file = aip_reii_persist_native_upload_v0554( $_FILES[ $key ] );
			if ( is_wp_error( $file ) ) {
				aip_reii_native_checkout_error_v0554( $file->get_error_message() );
			}
			$files[] = $file;
		}
		if ( ! $files ) {
			aip_reii_native_checkout_error_v0554( 'Please upload at least one product image, PDF, or ZIP file.' );
		}
	}

	$allowed_addons = array( 'amazon-storefront', 'extra-environment', 'another-version', '20-second-story', 'alternate-lighting', 'priority-delivery' );
	$addon          = sanitize_key( wp_unslash( $_POST['aip-addon'] ?? '' ) );
	$addon          = in_array( $addon, $allowed_addons, true ) ? $addon : '';
	$intake         = array(
		'email'        => $email,
		'method'       => $method,
		'reference'    => 'Amazon link / ASIN' === $method ? $reference : '',
		'notes'        => sanitize_textarea_field( wp_unslash( $_POST['creative-notes'] ?? '' ) ),
		'addon'        => $addon,
		'source_order' => absint( $_POST['aip-source-order'] ?? 0 ),
		'file_names'   => array_values( wp_list_pluck( $files, 'name' ) ),
		'files'        => $files,
		'submitted_at' => current_time( DATE_ATOM ),
	);

	$stripe_checkout = aip_reii_prepare_direct_stripe_checkout_v0559( $intake, $product );
	if ( is_wp_error( $stripe_checkout ) ) {
		aip_reii_native_checkout_error_v0554( $stripe_checkout->get_error_message(), 503 );
	}
	wp_send_json_success( $stripe_checkout );
}
add_action( 'wp_ajax_aip_reii_prepare_checkout', 'aip_reii_prepare_native_checkout_v0554' );
add_action( 'wp_ajax_nopriv_aip_reii_prepare_checkout', 'aip_reii_prepare_native_checkout_v0554' );

function aip_reii_capture_direct_purchase( $contact_form, &$abort, $submission ) {
	if ( ! is_object( $contact_form ) || 'On-Model Content Order Form' !== $contact_form->title() ) {
		return;
	}
	if ( function_exists( 'wc_load_cart' ) && ( ! function_exists( 'WC' ) || ! WC()->cart ) ) {
		wc_load_cart();
	}
	if ( ! function_exists( 'WC' ) || ! WC()->cart || ! WC()->session || aip_reii_cart_has_direct_purchase() ) {
		return;
	}
	$product = aip_reii_direct_purchase_product();
	if ( ! $product || ! $product->is_purchasable() ) {
		return;
	}
	if ( ! $submission && class_exists( 'WPCF7_Submission' ) ) {
		$submission = WPCF7_Submission::get_instance();
	}
	if ( ! $submission ) {
		return;
	}

	$data       = $submission->get_posted_data();
	$uploaded   = $submission->uploaded_files();
	$file_names = array();
	$files_data = array();
	for ( $index = 1; $index <= 4; $index++ ) {
		$key = 'product-file-' . $index;
		if ( empty( $uploaded[ $key ] ) ) {
			continue;
		}
		$files = is_array( $uploaded[ $key ] ) ? $uploaded[ $key ] : array( $uploaded[ $key ] );
		foreach ( $files as $file ) {
			if ( ! $file ) {
				continue;
			}
			$persisted = aip_reii_persist_fallback_file( $file );
			if ( $persisted ) {
				$files_data[] = $persisted;
				$file_names[] = $persisted['name'];
			}
		}
	}
	$allowed_addons = array( 'amazon-storefront', 'extra-environment', 'another-version', '20-second-story', 'alternate-lighting', 'priority-delivery' );
	$addon          = isset( $_POST['aip-addon'] ) ? sanitize_key( wp_unslash( $_POST['aip-addon'] ) ) : '';
	$addon          = in_array( $addon, $allowed_addons, true ) ? $addon : '';
	$intake         = array(
		'email'        => sanitize_email( isset( $data['your-email'] ) ? $data['your-email'] : '' ),
		'method'       => sanitize_text_field( isset( $data['source-method'] ) ? $data['source-method'] : '' ),
		'reference'    => sanitize_text_field( isset( $data['product-reference'] ) ? $data['product-reference'] : '' ),
		'notes'        => sanitize_textarea_field( isset( $data['creative-notes'] ) ? $data['creative-notes'] : '' ),
		'addon'        => $addon,
		'source_order' => isset( $_POST['aip-source-order'] ) ? absint( $_POST['aip-source-order'] ) : 0,
		'file_names'   => array_slice( array_values( array_unique( $file_names ) ), 0, 4 ),
		'files'        => array_slice( $files_data, 0, 4 ),
		'submitted_at' => current_time( DATE_ATOM ),
	);
	$is_upload = 'Upload product files' === $intake['method'];
	if ( ( $is_upload && empty( $intake['files'] ) ) || ( ! $is_upload && empty( $intake['reference'] ) ) ) {
		$abort = true;
		if ( method_exists( $submission, 'set_response' ) ) {
			$submission->set_response( $is_upload ? 'Please upload at least one product file.' : 'Please paste an Amazon link or ASIN.' );
		}
		return;
	}
	$added = WC()->cart->add_to_cart(
		$product->get_id(),
		1,
		0,
		array(),
		array( 'aip_intake' => $intake, 'aip_key' => wp_generate_uuid4() )
	);
	if ( $added ) {
		WC()->session->set( 'aip_intake', $intake );
		if ( $intake['email'] && WC()->customer ) {
			WC()->customer->set_billing_email( $intake['email'] );
		}
		WC()->session->set_customer_session_cookie( true );
		WC()->cart->set_session();
	}
}

function aip_reii_seed_direct_purchase_checkout() {
	if ( empty( $_GET['aip_embedded'] ) || ! function_exists( 'is_checkout' ) || ! is_checkout() || is_wc_endpoint_url( 'order-received' ) ) {
		return;
	}
	if ( function_exists( 'wc_load_cart' ) && ( ! function_exists( 'WC' ) || ! WC()->cart ) ) {
		wc_load_cart();
	}
	if ( ! function_exists( 'WC' ) || ! WC()->cart || ! WC()->session || aip_reii_cart_has_direct_purchase() ) {
		return;
	}
	$product = aip_reii_direct_purchase_product();
	if ( ! $product || ! $product->is_purchasable() ) {
		return;
	}
	$intake = WC()->session->get( 'aip_intake' );
	$intake = is_array( $intake ) ? $intake : array( 'submitted_at' => current_time( DATE_ATOM ) );
	$added  = WC()->cart->add_to_cart(
		$product->get_id(),
		1,
		0,
		array(),
		array( 'aip_intake' => $intake, 'aip_key' => wp_generate_uuid4() )
	);
	if ( $added ) {
		WC()->session->set_customer_session_cookie( true );
		WC()->cart->set_session();
	}
}

function aip_reii_price_direct_purchase( $cart ) {
	if ( ! $cart || ( is_admin() && ! wp_doing_ajax() ) ) {
		return;
	}
	$addon_prices = array(
		'amazon-storefront'  => 10,
		'extra-environment'  => 15,
		'another-version'    => 15,
		'20-second-story'    => 10,
		'alternate-lighting' => 10,
		'priority-delivery'  => 10,
	);
	foreach ( $cart->get_cart() as $cart_item ) {
		$product = isset( $cart_item['data'] ) ? $cart_item['data'] : null;
		if ( ! $product || 'on-model-content-order' !== $product->get_sku() ) {
			continue;
		}
		$addon = ! empty( $cart_item['aip_intake']['addon'] ) ? sanitize_key( $cart_item['aip_intake']['addon'] ) : '';
		$extra = isset( $addon_prices[ $addon ] ) ? $addon_prices[ $addon ] : 0;
		$product->set_price( 10 + $extra );
	}
}

add_action( 'init', 'aip_reii_ensure_direct_purchase_product', 20 );
add_action( 'template_redirect', 'aip_reii_seed_direct_purchase_checkout', 0 );
add_action( 'woocommerce_before_calculate_totals', 'aip_reii_price_direct_purchase', 999 );
}

// Always refresh the complete intake from the current form submission. This
// versioned fallback intentionally lives outside the legacy function and class
// guards: an orphaned plugin copy can otherwise leave an earlier service item
// in the cart and cause its email/reference to be reused for the next order.
if ( ! function_exists( 'aip_reii_capture_current_intake_v0549' ) ) {
function aip_reii_persist_current_file_v0549( $file ) {
	if ( ! $file || ! is_readable( $file ) ) {
		return null;
	}
	$uploads = wp_upload_dir();
	if ( ! empty( $uploads['error'] ) ) {
		return null;
	}
	$relative_dir = 'aip-order-intake/' . gmdate( 'Y/m' );
	$target_dir   = trailingslashit( $uploads['basedir'] ) . $relative_dir;
	if ( ! wp_mkdir_p( $target_dir ) ) {
		return null;
	}
	$source_name = sanitize_file_name( basename( $file ) );
	$file_name   = wp_unique_filename( $target_dir, substr( wp_generate_uuid4(), 0, 8 ) . '-' . $source_name );
	$target      = trailingslashit( $target_dir ) . $file_name;
	if ( ! copy( $file, $target ) ) {
		return null;
	}
	$file_type = wp_check_filetype( $file_name );
	return array(
		'name' => $source_name,
		'url'  => trailingslashit( $uploads['baseurl'] ) . $relative_dir . '/' . rawurlencode( $file_name ),
		'type' => isset( $file_type['type'] ) ? $file_type['type'] : '',
		'size' => (int) filesize( $target ),
	);
}

function aip_reii_scalar_text_v0549( $value ) {
	while ( is_array( $value ) ) {
		if ( empty( $value ) ) {
			return '';
		}
		$value = reset( $value );
	}
	return is_scalar( $value ) ? trim( (string) $value ) : '';
}

function aip_reii_posted_text_v0549( $submission, $posted_data, $field_name ) {
	// Contact Form 7 represents selectable controls (including radio buttons)
	// as arrays in get_posted_data(). sanitize_text_field() rejects arrays, so
	// normalize to the first submitted value before sanitizing it.
	if ( is_object( $submission ) && method_exists( $submission, 'get_posted_string' ) ) {
		return aip_reii_scalar_text_v0549( $submission->get_posted_string( $field_name ) );
	}
	$value = isset( $posted_data[ $field_name ] ) ? $posted_data[ $field_name ] : '';
	return aip_reii_scalar_text_v0549( $value );
}

function aip_reii_source_method_v0549( $submission, $posted_data ) {
	$value = sanitize_text_field( aip_reii_posted_text_v0549( $submission, $posted_data, 'source-method' ) );
	if ( 'upload' === $value || 'Upload product files' === $value ) {
		return 'Upload product files';
	}
	if ( 'amazon' === $value || 'Amazon link / ASIN' === $value ) {
		return 'Amazon link / ASIN';
	}
	return '';
}

function aip_reii_capture_current_intake_v0549( $contact_form, &$abort, $submission ) {
	if ( ! is_object( $contact_form ) || 'On-Model Content Order Form' !== $contact_form->title() ) {
		return;
	}
	if ( ! $submission && class_exists( 'WPCF7_Submission' ) ) {
		$submission = WPCF7_Submission::get_instance();
	}
	if ( ! $submission ) {
		return;
	}
	if ( function_exists( 'wc_load_cart' ) && ( ! function_exists( 'WC' ) || ! WC()->cart ) ) {
		wc_load_cart();
	}
	if ( ! function_exists( 'WC' ) || ! WC()->cart || ! WC()->session ) {
		return;
	}

	$data       = $submission->get_posted_data();
	$uploaded   = $submission->uploaded_files();
	$method     = aip_reii_source_method_v0549( $submission, $data );
	$raw_files  = array();
	foreach ( 'Upload product files' === $method ? range( 1, 4 ) : array() as $index ) {
		$key = 'product-file-' . $index;
		if ( empty( $uploaded[ $key ] ) ) {
			continue;
		}
		$files = is_array( $uploaded[ $key ] ) ? $uploaded[ $key ] : array( $uploaded[ $key ] );
		foreach ( $files as $file ) {
			if ( is_string( $file ) && is_file( $file ) && is_readable( $file ) ) {
				$raw_files[] = $file;
			}
		}
	}
	$allowed_addons = array( 'amazon-storefront', 'extra-environment', 'another-version', '20-second-story', 'alternate-lighting', 'priority-delivery' );
	$addon          = isset( $_POST['aip-addon'] ) ? sanitize_key( wp_unslash( $_POST['aip-addon'] ) ) : '';
	$addon          = in_array( $addon, $allowed_addons, true ) ? $addon : '';
	$current        = array(
		'email'        => sanitize_email( aip_reii_posted_text_v0549( $submission, $data, 'your-email' ) ),
		'method'       => $method,
		'reference'    => 'Amazon link / ASIN' === $method ? sanitize_text_field( aip_reii_posted_text_v0549( $submission, $data, 'product-reference' ) ) : '',
		'notes'        => sanitize_textarea_field( aip_reii_posted_text_v0549( $submission, $data, 'creative-notes' ) ),
		'addon'        => $addon,
		'source_order' => isset( $_POST['aip-source-order'] ) ? absint( $_POST['aip-source-order'] ) : 0,
	);
	if ( ! $method ) {
		$abort = true;
		if ( method_exists( $submission, 'set_response' ) ) {
			$submission->set_response( 'Please choose an Amazon link or product-file upload.' );
		}
		return;
	}
	$files_data = array();
	foreach ( array_slice( $raw_files, 0, 4 ) as $file ) {
		$persisted = aip_reii_persist_current_file_v0549( $file );
		if ( $persisted ) {
			$files_data[] = $persisted;
		}
	}
	$intake = array_merge(
		$current,
		array(
			'file_names'   => array_values( array_map( static function( $file ) { return $file['name']; }, $files_data ) ),
			'files'        => array_slice( $files_data, 0, 4 ),
			'submitted_at' => current_time( DATE_ATOM ),
		)
	);
	$is_upload = 'Upload product files' === $intake['method'];
	if ( ( $is_upload && empty( $intake['files'] ) ) || ( ! $is_upload && empty( $intake['reference'] ) ) ) {
		$abort = true;
		if ( method_exists( $submission, 'set_response' ) ) {
			$submission->set_response( $is_upload ? 'Please upload at least one product file.' : 'Please paste an Amazon link or ASIN.' );
		}
		return;
	}

	$product_id = function_exists( 'wc_get_product_id_by_sku' ) ? wc_get_product_id_by_sku( 'on-model-content-order' ) : 0;
	$product    = $product_id && function_exists( 'wc_get_product' ) ? wc_get_product( $product_id ) : null;
	if ( ! $product || ! $product->is_purchasable() ) {
		$abort = true;
		if ( method_exists( $submission, 'set_response' ) ) {
			$submission->set_response( 'Checkout is temporarily unavailable. Please try again shortly.' );
		}
		return;
	}
	// Replace every earlier service item so WooCommerce creates this order from
	// the current submission rather than reusing its previous cart-item payload.
	$removed_keys = array();
	foreach ( WC()->cart->get_cart() as $cart_key => $cart_item ) {
		$cart_product = isset( $cart_item['data'] ) ? $cart_item['data'] : null;
		if ( ( isset( $cart_item['product_id'] ) && $product_id === (int) $cart_item['product_id'] ) || ( $cart_product && 'on-model-content-order' === $cart_product->get_sku() ) ) {
			if ( WC()->cart->remove_cart_item( $cart_key ) ) {
				$removed_keys[] = $cart_key;
			}
		}
	}
	$added = WC()->cart->add_to_cart(
		$product_id,
		1,
		0,
		array(),
		array( 'aip_intake' => $intake, 'aip_key' => wp_generate_uuid4() )
	);
	if ( ! $added ) {
		foreach ( $removed_keys as $cart_key ) {
			WC()->cart->restore_cart_item( $cart_key );
		}
		WC()->cart->set_session();
		$abort = true;
		if ( method_exists( $submission, 'set_response' ) ) {
			$submission->set_response( 'Checkout could not prepare this order. Please try again.' );
		}
		return;
	}

	WC()->session->set( 'aip_intake', $intake );
	if ( $intake['email'] && WC()->customer ) {
		WC()->customer->set_billing_email( $intake['email'] );
		if ( ! WC()->customer->get_billing_country( 'edit' ) ) {
			WC()->customer->set_billing_country( aip_reii_default_billing_country() );
		}
		WC()->customer->save();
	}
	WC()->session->set_customer_session_cookie( true );
	WC()->cart->set_session();
}

function aip_reii_service_order_items_v0549( $order ) {
	$items = array();
	if ( ! is_a( $order, 'WC_Order' ) ) {
		return $items;
	}
	foreach ( $order->get_items() as $item ) {
		$product = is_callable( array( $item, 'get_product' ) ) ? $item->get_product() : null;
		if ( $product && 'on-model-content-order' === $product->get_sku() ) {
			$items[] = $item;
		}
	}
	return $items;
}

function aip_reii_is_service_checkout_v0549( $order ) {
	if ( aip_reii_service_order_items_v0549( $order ) ) {
		return true;
	}
	if ( function_exists( 'WC' ) && WC()->cart ) {
		foreach ( WC()->cart->get_cart() as $cart_item ) {
			$product = isset( $cart_item['data'] ) ? $cart_item['data'] : null;
			if ( $product && 'on-model-content-order' === $product->get_sku() ) {
				return true;
			}
		}
	}
	return false;
}

function aip_reii_cart_intake_v0549() {
	if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
		return array();
	}
	foreach ( WC()->cart->get_cart() as $cart_item ) {
		$product = isset( $cart_item['data'] ) ? $cart_item['data'] : null;
		if ( $product && 'on-model-content-order' === $product->get_sku() && ! empty( $cart_item['aip_intake'] ) && is_array( $cart_item['aip_intake'] ) ) {
			return $cart_item['aip_intake'];
		}
	}
	return array();
}

function aip_reii_display_reference_v0549( $reference ) {
	$reference = trim( (string) $reference );
	if ( preg_match( '/(?:^|[^A-Z0-9])(B0[A-Z0-9]{8})(?=$|[^A-Z0-9])/i', $reference, $matches ) ) {
		return 'ASIN: ' . strtoupper( $matches[1] );
	}
	if ( function_exists( 'mb_strimwidth' ) ) {
		return mb_strimwidth( $reference, 0, 45, '…' );
	}
	return strlen( $reference ) > 45 ? substr( $reference, 0, 42 ) . '...' : $reference;
}

function aip_reii_apply_intake_to_item_v0549( $item, $intake, $save = false ) {
	foreach ( array( 'Product source', 'Amazon link / ASIN', '_aip_raw_reference', 'Customer instructions', 'Video add-on', 'Source order', 'Uploaded file', 'Uploaded files' ) as $key ) {
		$item->delete_meta_data( $key );
	}
	if ( ! empty( $intake['method'] ) ) {
		$item->add_meta_data( 'Product source', wc_clean( $intake['method'] ), true );
	}
	if ( ! empty( $intake['reference'] ) ) {
		$item->add_meta_data( 'Amazon link / ASIN', aip_reii_display_reference_v0549( $intake['reference'] ), true );
		$item->add_meta_data( '_aip_raw_reference', wc_clean( $intake['reference'] ), true );
	}
	if ( ! empty( $intake['notes'] ) ) {
		$item->add_meta_data( 'Customer instructions', wc_clean( $intake['notes'] ), true );
	}
	$addon_labels = array(
		'amazon-storefront'  => 'Post to REii’s Amazon Storefront (+$10)',
		'extra-environment'  => 'Extra environment (+$15)',
		'another-version'    => 'Another version (+$15)',
		'20-second-story'    => '20-second story (+$10)',
		'alternate-lighting' => 'Alternate lighting (+$10)',
		'priority-delivery'  => 'Priority delivery (+$10)',
	);
	$addon = ! empty( $intake['addon'] ) ? sanitize_key( $intake['addon'] ) : '';
	if ( isset( $addon_labels[ $addon ] ) ) {
		$item->add_meta_data( 'Video add-on', $addon_labels[ $addon ], true );
	}
	if ( ! empty( $intake['source_order'] ) ) {
		$item->add_meta_data( 'Source order', '#' . absint( $intake['source_order'] ), true );
	}
	if ( ! empty( $intake['file_names'] ) && is_array( $intake['file_names'] ) ) {
		$item->add_meta_data( 'Uploaded files', implode( ', ', array_map( 'wc_clean', $intake['file_names'] ) ), true );
	}
	if ( $save ) {
		$item->save();
	}
}

function aip_reii_apply_current_intake_to_order_v0549( $order, $save_items = false ) {
	if ( ! is_a( $order, 'WC_Order' ) || ! aip_reii_is_service_checkout_v0549( $order ) ) {
		return false;
	}
	// A fresh order is authored from its exact cart line. Never use an unrelated
	// global session intake to rewrite an existing/retry order whose cart is gone.
	$intake = aip_reii_cart_intake_v0549();
	if ( ! is_array( $intake ) || empty( $intake ) ) {
		return false;
	}
	$email = ! empty( $intake['email'] ) ? sanitize_email( $intake['email'] ) : '';
	if ( $email && is_email( $email ) ) {
		$order->set_billing_email( $email );
	}
	$order->update_meta_data( '_aip_intake_email', $email );
	$order->update_meta_data( '_aip_intake_submitted_at', ! empty( $intake['submitted_at'] ) ? $intake['submitted_at'] : current_time( DATE_ATOM ) );
	$order->update_meta_data( '_aip_intake_method', ! empty( $intake['method'] ) ? wc_clean( $intake['method'] ) : '' );
	$order->update_meta_data( '_aip_intake_reference', ! empty( $intake['reference'] ) ? wc_clean( $intake['reference'] ) : '' );
	$order->update_meta_data( '_aip_intake_notes', ! empty( $intake['notes'] ) ? wc_clean( $intake['notes'] ) : '' );
	if ( ! empty( $intake['files'] ) && is_array( $intake['files'] ) ) {
		$order->update_meta_data( '_aip_uploaded_files', array_slice( $intake['files'], 0, 4 ) );
	} else {
		$order->delete_meta_data( '_aip_uploaded_files' );
	}
	foreach ( aip_reii_service_order_items_v0549( $order ) as $item ) {
		aip_reii_apply_intake_to_item_v0549( $item, $intake, $save_items );
	}
	return true;
}

function aip_reii_copy_current_intake_to_order_item_v0549( $item, $cart_item_key, $values, $order ) {
	if ( empty( $values['aip_intake'] ) || empty( $values['data'] ) || 'on-model-content-order' !== $values['data']->get_sku() ) {
		return;
	}
	aip_reii_apply_intake_to_item_v0549( $item, $values['aip_intake'] );
}

function aip_reii_apply_current_intake_to_classic_order_v0549( $order, $data ) {
	aip_reii_apply_current_intake_to_order_v0549( $order );
}

function aip_reii_apply_current_intake_to_store_order_v0549( $order, $request ) {
	if ( is_a( $order, 'WC_Order' ) && aip_reii_is_service_checkout_v0549( $order ) && ! $order->get_billing_country() ) {
		$order->set_billing_country( aip_reii_default_billing_country() );
	}
	aip_reii_apply_current_intake_to_order_v0549( $order );
}

add_action( 'woocommerce_checkout_create_order_line_item', 'aip_reii_copy_current_intake_to_order_item_v0549', 9999, 4 );
add_action( 'woocommerce_checkout_create_order', 'aip_reii_apply_current_intake_to_classic_order_v0549', 9999, 2 );
add_action( 'woocommerce_store_api_checkout_update_order_from_request', 'aip_reii_apply_current_intake_to_store_order_v0549', 10000, 2 );

function aip_reii_register_current_intake_capture_v0549() {
	remove_action( 'wpcf7_before_send_mail', 'aip_reii_capture_direct_purchase', 99 );
	remove_action( 'wpcf7_before_send_mail', array( 'AIP_On_Model_Commerce_GitHub', 'capture_intake' ), 10 );
	remove_action( 'wpcf7_before_send_mail', array( 'AIP_On_Model_Commerce', 'capture_intake' ), 10 );
	remove_action( 'wpcf7_before_send_mail', 'aip_reii_capture_current_intake_v0548', 1000 );
	remove_action( 'woocommerce_checkout_create_order_line_item', 'aip_reii_copy_current_intake_to_order_item_v0548', 9999 );
	remove_action( 'woocommerce_checkout_create_order', 'aip_reii_apply_current_intake_to_classic_order_v0548', 9999 );
	remove_action( 'woocommerce_store_api_checkout_update_order_from_request', 'aip_reii_apply_current_intake_to_store_order_v0548', 10000 );
	if ( ! has_action( 'woocommerce_checkout_create_order_line_item', 'aip_reii_copy_current_intake_to_order_item_v0549' ) ) {
		add_action( 'woocommerce_checkout_create_order_line_item', 'aip_reii_copy_current_intake_to_order_item_v0549', 9999, 4 );
	}
	if ( ! has_action( 'woocommerce_checkout_create_order', 'aip_reii_apply_current_intake_to_classic_order_v0549' ) ) {
		add_action( 'woocommerce_checkout_create_order', 'aip_reii_apply_current_intake_to_classic_order_v0549', 9999, 2 );
	}
	if ( ! has_action( 'woocommerce_store_api_checkout_update_order_from_request', 'aip_reii_apply_current_intake_to_store_order_v0549' ) ) {
		add_action( 'woocommerce_store_api_checkout_update_order_from_request', 'aip_reii_apply_current_intake_to_store_order_v0549', 10000, 2 );
	}
	// The pre-0.5.48 Store API fallback reads the global session. Leaving it
	// active could rewrite an existing payment-retry order with a later intake.
	remove_action( 'woocommerce_store_api_checkout_update_order_from_request', 'aip_reii_store_api_billing_country', 999 );
	if ( ! has_action( 'wpcf7_before_send_mail', 'aip_reii_capture_current_intake_v0549' ) ) {
		add_action( 'wpcf7_before_send_mail', 'aip_reii_capture_current_intake_v0549', 1000, 3 );
	}
}
}

// Register the order API independently from the plugin class bootstrap. A
// legacy duplicate can define the class before this file is loaded, but it must
// never be able to suppress the REST namespace used by Order Studio.
if ( ! function_exists( 'aip_register_order_api_routes' ) ) {
function aip_register_order_api_routes() {
	if ( class_exists( 'AIP_On_Model_Commerce_GitHub', false ) ) {
		AIP_On_Model_Commerce_GitHub::register_order_api();
	}
}
add_action( 'rest_api_init', 'aip_register_order_api_routes' );
}

// The intake form only prepares checkout. The paid WooCommerce order is the
// customer-facing confirmation, so never send a second pre-payment receipt.
if ( ! function_exists( 'aip_reii_skip_intake_autoresponder_v0553' ) ) {
function aip_reii_skip_intake_autoresponder_v0553( $skip_mail, $contact_form ) {
	if ( is_object( $contact_form ) && is_callable( array( $contact_form, 'title' ) ) && 'On-Model Content Order Form' === $contact_form->title() ) {
		return true;
	}
	return $skip_mail;
}
}

// A paid REii order sends the processing receipt, then the completed delivery
// email. Suppress WooCommerce's optional on-hold stage message for this service
// without affecting refunds, account/security mail, other products, or admin
// order notifications.
if ( ! function_exists( 'aip_reii_disable_on_hold_customer_email_v0553' ) ) {
function aip_reii_disable_on_hold_customer_email_v0553( $enabled, $order ) {
	return aip_reii_is_reii_order_v0551( $order ) ? false : $enabled;
}
add_filter( 'woocommerce_email_enabled_customer_on_hold_order', 'aip_reii_disable_on_hold_customer_email_v0553', PHP_INT_MAX, 2 );
}

// Keep client delivery available even when WordPress.com preloads the plugin
// class from an orphaned directory. In that state the class guard below must
// avoid a fatal redeclaration, but returning before ::init() previously left
// the completed-order email, passwordless library, and admin delivery controls
// unregistered. WordPress de-duplicates identical callbacks, so this is also
// safe when the class is defined normally by this file.
if ( ! function_exists( 'aip_reii_register_delivery_surface_v0553' ) ) {
function aip_reii_register_delivery_surface_v0553() {
	$class = 'AIP_On_Model_Commerce_GitHub';
	if ( ! class_exists( $class, false ) ) {
		return;
	}

	$actions = array(
		array( 'woocommerce_email_before_order_table', 'email_delivery_links', 20, 4 ),
		array( 'woocommerce_email_after_order_table', 'email_create_another_prompt', 20, 4 ),
		array( 'woocommerce_email_after_order_table', 'email_order_confirmation_message', 15, 4 ),
		array( 'woocommerce_email_order_details', 'track_email_before', 1, 4 ),
		array( 'woocommerce_email_footer', 'track_email_after', 999, 1 ),
		array( 'template_redirect', 'passwordless_delivery_request', 1, 1 ),
		array( 'add_meta_boxes', 'add_admin_order_meta_boxes', 10, 2 ),
		array( 'admin_enqueue_scripts', 'admin_order_assets', 10, 1 ),
		array( 'wp_ajax_aip_admin_update_order_status', 'ajax_update_order_status', 10, 1 ),
		array( 'wp_ajax_aip_admin_deliver_order', 'ajax_deliver_order', 10, 1 ),
	);
	foreach ( $actions as $hook ) {
		if ( is_callable( array( $class, $hook[1] ) ) ) {
			add_action( $hook[0], array( $class, $hook[1] ), $hook[2], $hook[3] );
		}
	}

	$filters = array(
		array( 'woocommerce_email_subject_customer_processing_order', 'custom_processing_email_subject', 10, 2 ),
		array( 'woocommerce_email_heading_customer_processing_order', 'custom_processing_email_heading', 10, 2 ),
		array( 'woocommerce_email_subject_customer_completed_order', 'custom_completed_email_subject', 10, 2 ),
		array( 'woocommerce_email_heading_customer_completed_order', 'custom_completed_email_heading', 10, 2 ),
		array( 'woocommerce_get_order_item_totals', 'filter_completed_email_order_item_totals', 20, 3 ),
		array( 'woocommerce_order_formatted_line_subtotal', 'filter_completed_email_line_subtotal', 20, 3 ),
		array( 'woocommerce_email_order_items_table', 'filter_completed_email_items_table', 20, 2 ),
		array( 'woocommerce_email_styles', 'filter_completed_email_styles', 20, 2 ),
		array( 'wc_get_template', 'filter_completed_email_templates', 20, 5 ),
		array( 'wpcf7_skip_mail', 'skip_intake_email', PHP_INT_MAX, 2 ),
		array( 'gettext', 'customize_email_gettext', 20, 3 ),
	);
	foreach ( $filters as $hook ) {
		if ( is_callable( array( $class, $hook[1] ) ) ) {
			add_filter( $hook[0], array( $class, $hook[1] ), $hook[2], $hook[3] );
		}
	}
}
add_action( 'plugins_loaded', 'aip_reii_register_delivery_surface_v0553', PHP_INT_MAX );
}

// The GitHub-enabled build intentionally uses a new permanent directory to
// escape legacy WordPress.com folders that cannot be overwritten. If an older
// copy is still active during the one-time migration, deactivate that file and
// let this copy take over on the next request instead of triggering a duplicate
// class fatal error.
if ( class_exists( 'AIP_On_Model_Commerce_GitHub', false ) ) {
	return;
}

final class AIP_On_Model_Commerce_GitHub {
	const VERSION     = '0.5.72';
	const PRODUCT_SKU = 'on-model-content-order';
	const FORM_TITLE  = 'On-Model Content Order Form';
	const BASE_PRICE  = '10';
	const GITHUB_REPOSITORY = 'whoisleon/on-model-commerce';
	const UPDATE_CACHE_KEY  = 'aip_on_model_github_release';

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_order_statuses' ) );
		add_filter( 'wc_order_statuses', array( __CLASS__, 'order_status_labels' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'fast_checkout_styles' ) );
		add_filter( 'body_class', array( __CLASS__, 'fast_checkout_body_class' ) );
		add_filter( 'woocommerce_default_address_fields', array( __CLASS__, 'optional_address_fields' ) );
		add_filter( 'woocommerce_billing_fields', array( __CLASS__, 'optional_billing_fields' ) );
		add_filter( 'woocommerce_checkout_fields', array( __CLASS__, 'remove_service_billing_fields' ), 100 );
		add_action( 'admin_init', array( __CLASS__, 'ensure_service_product' ) );
		add_action( 'admin_notices', array( __CLASS__, 'service_product_notice' ) );
		add_filter( 'woocommerce_checkout_get_value', array( __CLASS__, 'prefill_checkout_value' ), 10, 2 );
		add_filter( 'woocommerce_get_item_data', array( __CLASS__, 'cart_item_details' ), 10, 2 );
		add_action( 'woocommerce_before_calculate_totals', array( __CLASS__, 'apply_addon_price' ), 20 );
		add_action( 'woocommerce_checkout_create_order_line_item', array( __CLASS__, 'copy_intake_to_order_item' ), 10, 4 );
		add_filter( 'woocommerce_order_item_get_formatted_meta_data', array( __CLASS__, 'filter_order_item_display_meta' ), 10, 2 );
		add_filter( 'woocommerce_order_item_name', array( __CLASS__, 'add_item_thumbnail_to_confirmation' ), 10, 3 );
		add_action( 'woocommerce_checkout_create_order', array( __CLASS__, 'apply_intake_to_order' ), 10, 2 );
		add_action( 'woocommerce_email_before_order_table', array( __CLASS__, 'email_delivery_links' ), 20, 4 );
		add_action( 'woocommerce_email_after_order_table', array( __CLASS__, 'email_create_another_prompt' ), 20, 4 );
		add_action( 'woocommerce_email_after_order_table', array( __CLASS__, 'email_order_confirmation_message' ), 15, 4 );
		add_action( 'woocommerce_email_order_details', array( __CLASS__, 'track_email_before' ), 1, 4 );
		add_action( 'woocommerce_email_footer', array( __CLASS__, 'track_email_after' ), 999, 1 );
		add_filter( 'woocommerce_email_subject_customer_processing_order', array( __CLASS__, 'custom_processing_email_subject' ), 10, 2 );
		add_filter( 'woocommerce_email_heading_customer_processing_order', array( __CLASS__, 'custom_processing_email_heading' ), 10, 2 );
		add_filter( 'woocommerce_email_subject_customer_completed_order', array( __CLASS__, 'custom_completed_email_subject' ), 10, 2 );
		add_filter( 'woocommerce_email_heading_customer_completed_order', array( __CLASS__, 'custom_completed_email_heading' ), 10, 2 );
		add_filter( 'woocommerce_get_order_item_totals', array( __CLASS__, 'filter_completed_email_order_item_totals' ), 20, 3 );
		add_filter( 'woocommerce_order_formatted_line_subtotal', array( __CLASS__, 'filter_completed_email_line_subtotal' ), 20, 3 );
		add_filter( 'woocommerce_email_order_items_table', array( __CLASS__, 'filter_completed_email_items_table' ), 20, 2 );
		add_filter( 'woocommerce_email_styles', array( __CLASS__, 'filter_completed_email_styles' ), 20, 2 );
		add_filter( 'wc_get_template', array( __CLASS__, 'filter_completed_email_templates' ), 20, 5 );
		add_filter( 'gettext', array( __CLASS__, 'customize_email_gettext' ), 20, 3 );
		add_action( 'template_redirect', array( __CLASS__, 'passwordless_delivery_request' ), 1 );
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_admin_order_meta_boxes' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'admin_order_assets' ) );
		add_action( 'wp_ajax_aip_admin_update_order_status', array( __CLASS__, 'ajax_update_order_status' ) );
		add_action( 'wp_ajax_aip_admin_deliver_order', array( __CLASS__, 'ajax_deliver_order' ) );
		add_filter( 'pre_set_site_transient_update_plugins', array( __CLASS__, 'github_update_transient' ) );
		add_filter( 'update_plugins_github.com', array( __CLASS__, 'github_native_update' ), 10, 4 );
		add_filter( 'plugins_api', array( __CLASS__, 'github_plugin_information' ), 20, 3 );
		add_action( 'load-update-core.php', array( __CLASS__, 'clear_github_cache_for_forced_check' ) );
		add_action( 'upgrader_process_complete', array( __CLASS__, 'clear_github_update_cache' ), 10, 2 );
	}

	public static function clear_github_cache_for_forced_check() {
		if ( current_user_can( 'update_plugins' ) && isset( $_GET['force-check'] ) ) {
			delete_site_transient( self::UPDATE_CACHE_KEY );
		}
	}

	private static function github_release( $force = false ) {
		$cached = get_site_transient( self::UPDATE_CACHE_KEY );
		if ( ! $force && 'none' === $cached ) {
			return false;
		}
		if ( ! $force && is_array( $cached ) ) {
			return $cached;
		}

		$response = wp_remote_get(
			'https://api.github.com/repos/' . self::GITHUB_REPOSITORY . '/releases/latest',
			array(
				'headers' => array(
					'Accept'     => 'application/vnd.github+json',
					'User-Agent' => 'Tech-by-Leon-On-Model-Commerce/' . self::VERSION,
				),
				'timeout' => 10,
			)
		);
		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return self::github_release_from_readme();
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		$tag  = is_array( $data ) ? (string) ( $data['tag_name'] ?? '' ) : '';
		if ( ! preg_match( '/^v(\d+\.\d+\.\d+(?:[-+][0-9A-Za-z.-]+)?)$/', $tag, $matches ) ) {
			return self::github_release_from_readme();
		}

		$package = '';
		foreach ( (array) ( $data['assets'] ?? array() ) as $asset ) {
			if ( 'on-model-commerce.zip' === ( $asset['name'] ?? '' ) ) {
				$package = esc_url_raw( (string) ( $asset['browser_download_url'] ?? '' ) );
				break;
			}
		}
		if ( ! $package ) {
			return self::github_release_from_readme();
		}

		$release = array(
			'version'      => $matches[1],
			'package'      => $package,
			'url'          => esc_url_raw( (string) ( $data['html_url'] ?? 'https://github.com/' . self::GITHUB_REPOSITORY ) ),
			'body'         => wp_kses_post( (string) ( $data['body'] ?? '' ) ),
			'published_at' => sanitize_text_field( (string) ( $data['published_at'] ?? '' ) ),
		);
		set_site_transient( self::UPDATE_CACHE_KEY, $release, 6 * HOUR_IN_SECONDS );
		return $release;
	}

	private static function github_release_from_readme() {
		$response = wp_remote_get(
			'https://raw.githubusercontent.com/' . self::GITHUB_REPOSITORY . '/main/readme.txt',
			array(
				'headers' => array( 'User-Agent' => 'Tech-by-Leon-On-Model-Commerce/' . self::VERSION ),
				'timeout' => 10,
			)
		);
		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			set_site_transient( self::UPDATE_CACHE_KEY, 'none', 5 * MINUTE_IN_SECONDS );
			return false;
		}

		$readme = wp_remote_retrieve_body( $response );
		if ( ! preg_match( '/^Stable tag:\s*(\d+\.\d+\.\d+(?:[-+][0-9A-Za-z.-]+)?)\s*$/mi', $readme, $matches ) ) {
			set_site_transient( self::UPDATE_CACHE_KEY, 'none', 5 * MINUTE_IN_SECONDS );
			return false;
		}

		$version = $matches[1];
		$release = array(
			'version'      => $version,
			'package'      => 'https://github.com/' . self::GITHUB_REPOSITORY . '/releases/download/v' . $version . '/on-model-commerce.zip',
			'url'          => 'https://github.com/' . self::GITHUB_REPOSITORY . '/releases/tag/v' . $version,
			'body'         => 'See the GitHub release for changes.',
			'published_at' => gmdate( DATE_ATOM ),
		);
		set_site_transient( self::UPDATE_CACHE_KEY, $release, 15 * MINUTE_IN_SECONDS );
		return $release;
	}

	public static function github_update_transient( $transient ) {
		if ( ! is_object( $transient ) || empty( $transient->checked ) ) {
			return $transient;
		}
		$release = self::github_release( true );
		if ( ! $release || ! version_compare( self::VERSION, $release['version'], '<' ) ) {
			return $transient;
		}
		$plugin_file = plugin_basename( __FILE__ );
		$transient->response[ $plugin_file ] = (object) array(
			'slug'        => 'on-model-commerce-github',
			'plugin'      => $plugin_file,
			'new_version' => $release['version'],
			'url'         => $release['url'],
			'package'     => $release['package'],
			'tested'      => get_bloginfo( 'version' ),
		);
		return $transient;
	}

	public static function github_native_update( $update, $plugin_data, $plugin_file, $locales ) {
		$release = self::github_release( true );
		if ( ! $release || ! version_compare( self::VERSION, $release['version'], '<' ) ) {
			return false;
		}

		return array(
			'id'           => 'https://github.com/' . self::GITHUB_REPOSITORY,
			'slug'         => 'on-model-commerce-github',
			'version'      => $release['version'],
			'url'          => $release['url'],
			'package'      => $release['package'],
			'tested'       => get_bloginfo( 'version' ),
			'requires_php' => '7.4',
			'autoupdate'   => false,
		);
	}

	public static function github_plugin_information( $result, $action, $args ) {
		if ( 'plugin_information' !== $action || empty( $args->slug ) || 'on-model-commerce-github' !== $args->slug ) {
			return $result;
		}
		$release = self::github_release();
		if ( ! $release ) {
			return $result;
		}
		return (object) array(
			'name'          => 'REii Commerce',
			'slug'          => 'on-model-commerce-github',
			'version'       => $release['version'],
			'author'        => '<a href="https://techbyleon.com">Tech by Leon</a>',
			'homepage'      => $release['url'],
			'download_link' => $release['package'],
			'last_updated'  => $release['published_at'],
			'sections'      => array(
				'description' => 'Customer-facing WooCommerce project dashboard, production workflow, and private client delivery library.',
				'changelog'   => $release['body'] ?: 'See the GitHub release for changes.',
			),
		);
	}

	public static function github_plugin_action_links( $links, $plugin_file ) {
		if ( plugin_basename( __FILE__ ) !== $plugin_file ) {
			return $links;
		}
		if ( ! self::can_manage_github_updates() ) {
			return $links;
		}

		$url = wp_nonce_url(
			admin_url( 'admin-post.php?action=aip_github_update' ),
			'aip_github_update'
		);
		array_unshift(
			$links,
			'<a href="' . esc_url( $url ) . '"><strong>' . esc_html__( 'Update from GitHub', 'on-model-commerce' ) . '</strong></a>'
		);
		return $links;
	}

	public static function github_manual_update() {
		if ( ! self::can_manage_github_updates() ) {
			wp_die(
				esc_html__( 'You are not allowed to update plugins.', 'on-model-commerce' ),
				esc_html__( 'Plugin update denied', 'on-model-commerce' ),
				array( 'response' => 403 )
			);
		}
		check_admin_referer( 'aip_github_update' );

		delete_site_transient( self::UPDATE_CACHE_KEY );
		$release = self::github_release( true );
		if ( ! $release ) {
			self::github_manual_update_redirect( 'unavailable' );
		}
		if ( ! version_compare( self::VERSION, $release['version'], '<' ) ) {
			self::github_manual_update_redirect( 'current' );
		}

		$plugin_file = plugin_basename( __FILE__ );
		$transient   = get_site_transient( 'update_plugins' );
		if ( ! is_object( $transient ) ) {
			$transient = new stdClass();
		}
		if ( ! isset( $transient->response ) || ! is_array( $transient->response ) ) {
			$transient->response = array();
		}
		if ( ! isset( $transient->checked ) || ! is_array( $transient->checked ) ) {
			$transient->checked = array();
		}

		$transient->response[ $plugin_file ] = (object) array(
			'id'           => 'https://github.com/' . self::GITHUB_REPOSITORY,
			'slug'         => 'on-model-commerce-github',
			'plugin'       => $plugin_file,
			'new_version'  => $release['version'],
			'url'          => $release['url'],
			'package'      => $release['package'],
			'tested'       => get_bloginfo( 'version' ),
			'requires_php' => '7.4',
		);
		$transient->checked[ $plugin_file ] = self::VERSION;
		$transient->last_checked            = time();
		set_site_transient( 'update_plugins', $transient );

		$upgrade_url = add_query_arg(
			array(
				'action'   => 'upgrade-plugin',
				'plugin'   => $plugin_file,
				'_wpnonce' => wp_create_nonce( 'upgrade-plugin_' . $plugin_file ),
			),
			self_admin_url( 'update.php' )
		);
		wp_safe_redirect( $upgrade_url );
		exit;
	}

	private static function github_manual_update_redirect( $status ) {
		wp_safe_redirect(
			add_query_arg(
				'aip_github_update',
				sanitize_key( $status ),
				self_admin_url( 'plugins.php' )
			)
		);
		exit;
	}

	private static function can_manage_github_updates() {
		return current_user_can( 'update_plugins' ) || current_user_can( 'install_plugins' );
	}

	public static function github_manual_update_notice() {
		if ( empty( $_GET['aip_github_update'] ) || ! self::can_manage_github_updates() ) {
			return;
		}
		$status = sanitize_key( wp_unslash( $_GET['aip_github_update'] ) );
		if ( 'current' === $status ) {
			$message = __( 'REii Commerce is already on the latest GitHub release.', 'on-model-commerce' );
			$class   = 'notice notice-success is-dismissible';
		} elseif ( 'unavailable' === $status ) {
			$message = __( 'The latest GitHub release could not be reached. Please try again shortly.', 'on-model-commerce' );
			$class   = 'notice notice-error is-dismissible';
		} else {
			return;
		}
		echo '<div class="' . esc_attr( $class ) . '"><p>' . esc_html( $message ) . '</p></div>';
	}

	public static function clear_github_update_cache( $upgrader, $options ) {
		if ( isset( $options['action'], $options['type'] ) && 'update' === $options['action'] && 'plugin' === $options['type'] ) {
			delete_site_transient( self::UPDATE_CACHE_KEY );
		}
	}

	public static function register_order_api() {
		$permission = function() {
			return current_user_can( 'manage_woocommerce' );
		};
		register_rest_route(
			'aip/v1',
			'/orders',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'api_orders' ),
				'permission_callback' => $permission,
			)
		);
		register_rest_route(
			'aip/v1',
			'/orders/(?P<id>\d+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'api_order' ),
				'permission_callback' => $permission,
			)
		);
		register_rest_route(
			'aip/v1',
			'/orders/(?P<id>\d+)/status',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'api_order_status' ),
				'permission_callback' => $permission,
			)
		);
		register_rest_route(
			'aip/v1',
			'/orders/(?P<id>\d+)/deliver',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'api_order_deliver' ),
				'permission_callback' => $permission,
			)
		);
	}

	private static function api_order_object( $order ) {
		$items          = array();
		$reference      = (string) $order->get_meta( '_aip_intake_reference' );
		$notes          = (string) $order->get_meta( '_aip_intake_notes' );
		$method         = (string) $order->get_meta( '_aip_intake_method' );
		$fallback_files = array();
		$uploaded_files = $order->get_meta( '_aip_uploaded_files' );
		$uploaded_files = is_array( $uploaded_files ) ? $uploaded_files : array();
		foreach ( $order->get_items() as $item ) {
			$meta = array();
			foreach ( $item->get_formatted_meta_data( '' ) as $entry ) {
				$key   = wp_strip_all_tags( $entry->display_key );
				$value = wp_strip_all_tags( $entry->display_value );
				$meta[] = array( 'key' => $key, 'value' => $value );
				if ( 'Amazon link / ASIN' === $key ) {
					$reference = $value;
				} elseif ( 'Customer instructions' === $key ) {
					$notes = $value;
				} elseif ( 'Product source' === $key ) {
					$method = $value;
				} elseif ( in_array( $key, array( 'Uploaded file', 'Uploaded files' ), true ) ) {
					$fallback_files = array_merge( $fallback_files, preg_split( '/\s*,\s*/', $value ) );
				}
			}
			$items[] = array(
				'id'       => $item->get_id(),
				'name'     => $item->get_name(),
				'quantity' => $item->get_quantity(),
				'meta'     => $meta,
			);
		}
		if ( empty( $uploaded_files ) && ! empty( $fallback_files ) ) {
			foreach ( array_slice( array_filter( array_unique( $fallback_files ) ), 0, 4 ) as $file_name ) {
				$uploaded_files[] = array(
					'name' => sanitize_file_name( $file_name ),
					'url'  => '',
					'type' => '',
					'size' => 0,
				);
			}
		}
		$submitted_at       = $order->get_meta( '_aip_intake_submitted_at' );
		$created_at         = $order->get_date_created()
			? ( function_exists( 'wp_timezone' ) && method_exists( $order->get_date_created(), 'setTimezone' )
				? $order->get_date_created()->setTimezone( wp_timezone() )->format( DATE_ATOM )
				: $order->get_date_created()->date( DATE_ATOM ) )
			: null;
		$deliverables       = $order->get_meta( '_aip_deliverables' );
		$deliverables       = is_array( $deliverables ) ? $deliverables : array();
		$delivery_brief     = $order->get_meta( '_aip_delivery_brief' );
		if ( is_array( $delivery_brief ) && ! empty( $delivery_brief ) ) {
			$deliverables['brief'] = $delivery_brief;
		}
		$client_submitted_at = isset( $deliverables['delivered_at'] ) ? $deliverables['delivered_at'] : null;
		$has_generated_media = ! empty( $deliverables['images'] ) || ! empty( $deliverables['videos'] );
		$workflow_status     = $client_submitted_at
			? 'completed'
			: ( $has_generated_media ? 'content-review' : $order->get_status() );
		$workflow_label      = 'content-review' === $workflow_status
			? 'Ready for review'
			: wc_get_order_status_name( $workflow_status );
		$download_stats      = $order->get_meta( '_aip_download_stats' );
		$download_stats      = is_array( $download_stats ) ? $download_stats : array();
		if ( $client_submitted_at && false === strpos( $client_submitted_at, 'T' ) ) {
			$client_submitted_at = get_date_from_gmt( $client_submitted_at, DATE_ATOM );
		}
		return array(
			'id'            => $order->get_id(),
			'number'        => $order->get_order_number(),
			'status'        => $order->get_status(),
			'status_label'  => wc_get_order_status_name( $order->get_status() ),
			'workflow_status' => $workflow_status,
			'workflow_status_label' => $workflow_label,
			'created_at'    => $created_at,
			'submitted_at'  => $submitted_at ?: $created_at,
			'client_submitted_at' => $client_submitted_at,
			'download_stats' => $download_stats,
			'customer_name' => trim( $order->get_formatted_billing_full_name() ) ?: 'Customer',
			'email'         => $order->get_billing_email(),
			'total'         => html_entity_decode( wp_strip_all_tags( $order->get_formatted_order_total() ), ENT_QUOTES, 'UTF-8' ),
			'reference'     => $reference,
			'method'        => $method,
			'notes'         => $notes,
			'addon'         => (string) $order->get_meta( '_aip_intake_addon' ),
			'amazon_storefront' => 'yes' === $order->get_meta( '_aip_amazon_storefront' ) || 'amazon-storefront' === $order->get_meta( '_aip_intake_addon' ),
			'uploaded_files' => array_slice( $uploaded_files, 0, 4 ),
			'items'         => $items,
			'deliverables'  => $deliverables,
			'edit_url'      => $order->get_edit_order_url(),
		);
	}

	private static function api_find_order( $request ) {
		$order = wc_get_order( absint( $request['id'] ) );
		return $order ?: new WP_Error( 'aip_order_not_found', 'Order not found.', array( 'status' => 404 ) );
	}

	public static function api_orders( $request ) {
		$raw_status = sanitize_key( $request->get_param( 'status' ) ?: 'all' );
		$filter_review = 'content-review' === $raw_status;
		if ( 'all' === $raw_status || $filter_review ) {
			$status_query = array_keys( wc_get_order_statuses() );
		} elseif ( 'processing' === $raw_status ) {
			$status_query = array( 'processing', 'content-queued', 'content-creating' );
		} else {
			$status_query = $raw_status;
		}
		$result = wc_get_orders(
			array(
				'status'   => $status_query,
				'limit'    => min( 100, max( 1, absint( $request->get_param( 'per_page' ) ?: 30 ) ) ),
				'page'     => max( 1, absint( $request->get_param( 'page' ) ?: 1 ) ),
				'paginate' => true,
				'orderby'  => 'date',
				'order'    => 'DESC',
			)
		);
		$orders = array_map( array( __CLASS__, 'api_order_object' ), $result->orders );
		if ( $filter_review ) {
			$orders = array_values(
				array_filter(
					$orders,
					function( $order ) {
						return isset( $order['workflow_status'] ) && 'content-review' === $order['workflow_status'];
					}
				)
			);
		}
		return rest_ensure_response(
			array(
				'orders' => $orders,
				'total'  => $filter_review ? count( $orders ) : (int) $result->total,
				'pages'  => $filter_review ? ( empty( $orders ) ? 0 : 1 ) : (int) $result->max_num_pages,
			)
		);
	}

	public static function api_order( $request ) {
		$order = self::api_find_order( $request );
		return is_wp_error( $order ) ? $order : rest_ensure_response( self::api_order_object( $order ) );
	}

	public static function api_order_status( $request ) {
		$order = self::api_find_order( $request );
		if ( is_wp_error( $order ) ) {
			return $order;
		}
		$status = sanitize_key( $request->get_param( 'status' ) );
		if ( ! array_key_exists( 'wc-' . $status, wc_get_order_statuses() ) ) {
			return new WP_Error( 'aip_invalid_status', 'Invalid order status.', array( 'status' => 400 ) );
		}
		$order->update_status( $status, sanitize_textarea_field( $request->get_param( 'note' ) ?: '' ), true );
		return rest_ensure_response( self::api_order_object( $order ) );
	}

	public static function api_order_deliver( $request ) {
		$order = self::api_find_order( $request );
		if ( is_wp_error( $order ) ) {
			return $order;
		}
		$json   = $request->get_json_params() ?: array();
		$images = array_values( array_filter( array_map( 'esc_url_raw', (array) ( $json['image_urls'] ?? $request->get_param( 'image_urls' ) ) ) ) );
		$videos = array_values( array_filter( array_map( 'esc_url_raw', (array) ( $json['video_urls'] ?? $request->get_param( 'video_urls' ) ) ) ) );

		// If media URLs were not passed directly in payload, fallback to existing saved deliverables
		if ( empty( $images ) && empty( $videos ) ) {
			$saved = $order->get_meta( '_aip_deliverables' );
			if ( is_array( $saved ) ) {
				$images = array_values( array_filter( array_map( 'esc_url_raw', (array) ( $saved['images'] ?? [] ) ) ) );
				$videos = array_values( array_filter( array_map( 'esc_url_raw', (array) ( $saved['videos'] ?? [] ) ) ) );
			}
		}

		if ( empty( $images ) && empty( $videos ) ) {
			return new WP_Error( 'aip_missing_media', 'An image or video deliverable is required.', array( 'status' => 400 ) );
		}

		$raw_status    = ! empty( $json['status'] ) ? $json['status'] : $request->get_param( 'status' );
		$target_status = sanitize_key( $raw_status ?: 'completed' );
		$is_submission = 'completed' === $target_status;
		$deliverables  = array(
			'images'       => $images,
			'videos'       => $videos,
			'generated_at' => current_time( DATE_ATOM ),
		);
		if ( $is_submission ) {
			$deliverables['delivered_at'] = current_time( DATE_ATOM );
			$order->update_meta_data( '_aip_delivery_token', wp_generate_password( 48, false, false ) );
		}
		$order->update_meta_data( '_aip_deliverables', $deliverables );
		$raw_brief = isset( $json['brief'] ) && is_array( $json['brief'] ) ? $json['brief'] : array();
		$brief     = array();
		foreach ( array( 'model_profile', 'scene', 'lighting', 'format', 'transition', 'video_filter', 'pose', 'duration_seconds', 'reference' ) as $brief_key ) {
			if ( isset( $raw_brief[ $brief_key ] ) && is_scalar( $raw_brief[ $brief_key ] ) ) {
				$brief[ $brief_key ] = sanitize_text_field( (string) $raw_brief[ $brief_key ] );
			}
		}
		if ( $brief ) {
			$order->update_meta_data( '_aip_delivery_brief', $brief );
		}

		// Deliverables are order-specific. Keep them on the order instead of
		// rewriting the shared service product's download list, which can break
		// prior customers and is not required by the portal preview/download UI.
		$order->save();

		// A transition to completed triggers WooCommerce's customer email. Saving
		// a generated preview as content-review does not notify the customer.
		$status_note = $is_submission
			? 'Submitted deliverables attached to customer downloads and sent to client.'
			: 'Generated deliverables attached and ready for staff review.';
		$previous_status = $order->get_status();
		$order->update_status( $target_status, $status_note, true );
		if ( $is_submission && $previous_status === $target_status ) {
			$emails = WC()->mailer()->get_emails();
			if ( isset( $emails['WC_Email_Customer_Completed_Order'] ) ) {
				$emails['WC_Email_Customer_Completed_Order']->trigger( $order->get_id(), $order );
			}
		}

		return rest_ensure_response( self::api_order_object( $order ) );
	}

	public static function delivery_preview( $order ) {
		$delivery = $order->get_meta( '_aip_deliverables' );
		if ( ! is_array( $delivery ) || ( empty( $delivery['images'] ) && empty( $delivery['videos'] ) ) ) {
			return;
		}
		?>
		<section class="aip-delivery-preview">
			<h2>Your content preview</h2>
			<p>Review and download your finished REii AI influencer UGC video below.</p>
			<div class="aip-delivery-grid">
				<?php foreach ( (array) $delivery['images'] as $url ) : ?>
					<a href="<?php echo esc_url( $url ); ?>" download><img src="<?php echo esc_url( $url ); ?>" alt="Generated product-video preview"></a>
				<?php endforeach; ?>
				<?php foreach ( (array) $delivery['videos'] as $url ) : ?>
					<video controls playsinline preload="metadata" src="<?php echo esc_url( $url ); ?>"></video>
				<?php endforeach; ?>
			</div>
		</section>
		<?php
	}

	public static function email_delivery_links( $order, $sent_to_admin, $plain_text, $email ) {
		if ( $sent_to_admin || ! $order instanceof WC_Order || ! $email instanceof WC_Email ) {
			return;
		}
		if ( 'customer_completed_order' !== $email->id ) {
			return;
		}
		self::$current_rendering_email = $email->id;

		$delivery = $order->get_meta( '_aip_deliverables' );
		if (
			! is_array( $delivery )
			|| empty( $delivery['delivered_at'] )
			|| ( empty( $delivery['images'] ) && empty( $delivery['videos'] ) )
		) {
			return;
		}

		$token = $order->get_meta( '_aip_delivery_token' );
		if ( ! $token ) {
			return;
		}
		$delivery_url = add_query_arg(
			array(
				'aip_order_delivery' => $order->get_id(),
				'aip_token'          => $token,
			),
			home_url( '/' )
		);

		if ( $plain_text ) {
			echo "\nYOUR REII CONTENT IS READY\n";
			echo "Open your private REii library to view and download the AI influencer UGC created for your product (no password required):\n";
			echo esc_url_raw( $delivery_url ) . "\n\n";
			return;
		}
		?>
		<div class="aip-email-delivery-card" style="margin:20px 0 28px;padding:24px;border:1px solid #d8cef5;border-radius:12px;background:#fbfaff;">
			<small style="color:#6846e6;font-size:10px;font-weight:800;letter-spacing:1.2px;text-transform:uppercase;display:block;margin-bottom:8px;">Delivered &middot; Private Content Library</small>
			<h2 style="margin:0 0 8px;font-size:20px;line-height:1.3;color:#1d1827;">Your content is ready</h2>
			<p style="margin:0 0 18px;font-size:14px;line-height:1.5;color:#554c60;">Open your private REii library to view and download the AI influencer UGC created for your product. No password is required.</p>
			<p style="margin:10px 0 0;">
				<a href="<?php echo esc_url( $delivery_url ); ?>" style="display:inline-block;background:#6846e6;color:#ffffff;text-decoration:none;padding:13px 22px;border-radius:8px;font-weight:700;font-size:14px;text-align:center;">View and download your content</a>
			</p>
		</div>
		<?php
	}

	public static function email_create_another_prompt( $order, $sent_to_admin, $plain_text, $email ) {
		if ( $sent_to_admin || ! $order instanceof WC_Order || ! $email instanceof WC_Email ) {
			return;
		}
		if ( 'customer_completed_order' !== $email->id ) {
			return;
		}

		$reorder_url = home_url( '/style-by-reii/#submit-project' );

		if ( $plain_text ) {
			echo "\nWANT TO CREATE ANOTHER VIDEO?\n";
			echo "Reimagine another product or get a fresh AI influencer UGC video in just a few clicks:\n";
			echo esc_url_raw( $reorder_url ) . "\n\n";
			return;
		}
		?>
		<div class="aip-email-reorder-card" style="margin:28px 0 16px;padding:24px;border:1px solid #ded7e5;border-radius:12px;background:#f9f8fc;">
			<small style="color:#6846e6;font-size:10px;font-weight:800;letter-spacing:1.2px;text-transform:uppercase;display:block;margin-bottom:8px;">Create with REii</small>
			<h2 style="margin:0 0 8px;font-size:20px;line-height:1.3;color:#1d1827;">Want to create another video?</h2>
			<p style="margin:0 0 18px;font-size:14px;line-height:1.5;color:#554c60;">Reimagine another product or order a fresh AI influencer UGC variation in just a few clicks.</p>
			<p style="margin:10px 0 0;">
				<a href="<?php echo esc_url( $reorder_url ); ?>" style="display:inline-block;background:#1d1827;color:#ffffff;text-decoration:none;padding:12px 20px;border-radius:8px;font-weight:700;font-size:14px;text-align:center;">Create another video</a>
			</p>
		</div>
		<?php
	}

	private static function delivery_order_from_request() {
		$order_id = isset( $_GET['aip_order_delivery'] ) ? absint( $_GET['aip_order_delivery'] ) : 0;
		$token    = isset( $_GET['aip_token'] ) ? sanitize_text_field( wp_unslash( $_GET['aip_token'] ) ) : '';
		$order    = $order_id ? wc_get_order( $order_id ) : false;
		if ( ! $order || ! $token ) {
			return false;
		}
		$saved_token = (string) $order->get_meta( '_aip_delivery_token' );
		$delivery    = $order->get_meta( '_aip_deliverables' );
		if ( ! $saved_token || ! hash_equals( $saved_token, $token ) || ! is_array( $delivery ) || empty( $delivery['delivered_at'] ) ) {
			return false;
		}
		return $order;
	}

	private static function delivery_files( $order ) {
		$delivery  = $order->get_meta( '_aip_deliverables' );
		$files     = array();
		$img_count = count( (array) ( $delivery['images'] ?? array() ) );
		$vid_count = count( (array) ( $delivery['videos'] ?? array() ) );

		foreach ( (array) ( $delivery['images'] ?? array() ) as $index => $url ) {
			if ( $url ) {
				$label = $img_count > 1 ? sprintf( 'Product Preview %d', $index + 1 ) : 'Product Preview';
				$files[ 'image-' . $index ] = array( 'label' => $label, 'url' => esc_url_raw( $url ), 'type' => 'image' );
			}
		}
		foreach ( (array) ( $delivery['videos'] ?? array() ) as $index => $url ) {
			if ( $url ) {
				$label = $vid_count > 1 ? sprintf( 'REii AI Influencer UGC Video %d', $index + 1 ) : 'REii AI Influencer UGC Video';
				$files[ 'video-' . $index ] = array( 'label' => $label, 'url' => esc_url_raw( $url ), 'type' => 'video' );
			}
		}
		return $files;
	}

	private static function tracked_file_url( $order, $file_key, $mode = 'download' ) {
		return add_query_arg(
			array(
				'aip_order_delivery' => $order->get_id(),
				'aip_token'          => $order->get_meta( '_aip_delivery_token' ),
				'aip_file'           => $file_key,
				'aip_mode'           => $mode,
			),
			home_url( '/' )
		);
	}

	private static function delivery_orders_for_customer( $seed_order ) {
		$email = sanitize_email( $seed_order->get_billing_email() );
		if ( ! $email ) {
			return array( $seed_order );
		}
		$orders = wc_get_orders(
			array(
				'billing_email' => $email,
				'status'        => array( 'completed' ),
				'limit'         => -1,
				'orderby'       => 'date',
				'order'         => 'DESC',
			)
		);
		$available = array();
		foreach ( $orders as $order ) {
			$delivery = $order->get_meta( '_aip_deliverables' );
			if ( ! is_array( $delivery ) || empty( $delivery['delivered_at'] ) || ( empty( $delivery['images'] ) && empty( $delivery['videos'] ) ) ) {
				continue;
			}
			if ( ! $order->get_meta( '_aip_delivery_token' ) ) {
				$order->update_meta_data( '_aip_delivery_token', wp_generate_password( 48, false, false ) );
				$order->save();
			}
			$available[] = $order;
		}
		return $available;
	}

	private static function delivery_order_details( $order ) {
		$details = array(
			'package'               => '',
			'source'                => '',
			'reference'             => '',
			'instructions'          => '',
			'uploaded_files'        => '',
			'uploaded_file_objects' => array(),
			'production'            => array(),
		);
		$package_names = array();
		foreach ( $order->get_items() as $item ) {
			$package_names[] = $item->get_name();
			foreach ( $item->get_meta_data() as $meta ) {
				$key   = isset( $meta->key ) ? (string) $meta->key : '';
				$value = isset( $meta->value ) && is_scalar( $meta->value ) ? trim( (string) $meta->value ) : '';
				if ( ! $value ) {
					continue;
				}
				if ( 'Product source' === $key ) {
					$details['source'] = $value;
				} elseif ( 'Amazon link / ASIN' === $key ) {
					$details['reference'] = $value;
				} elseif ( 'Customer instructions' === $key ) {
					$details['instructions'] = $value;
				} elseif ( in_array( $key, array( 'Uploaded file', 'Uploaded files' ), true ) ) {
					$details['uploaded_files'] = $value;
				}
			}
		}

		$uploaded_objs = $order->get_meta( '_aip_uploaded_files' );
		$uploaded_objs = is_array( $uploaded_objs ) ? $uploaded_objs : array();
		if ( empty( $uploaded_objs ) && ! empty( $details['uploaded_files'] ) ) {
			$raw_names = preg_split( '/\s*,\s*/', $details['uploaded_files'] );
			foreach ( $raw_names as $fname ) {
				$fname = trim( $fname );
				if ( ! $fname ) {
					continue;
				}
				$uploaded_objs[] = array(
					'name' => $fname,
					'url'  => ( strpos( $fname, 'http' ) === 0 || strpos( $fname, 'data:image' ) === 0 ) ? $fname : '',
					'type' => '',
					'size' => 0,
				);
			}
		}
		$details['uploaded_file_objects'] = $uploaded_objs;
		$details['package'] = implode( ', ', array_filter( array_unique( $package_names ) ) );
		if ( ! $details['instructions'] ) {
			$details['instructions'] = trim( (string) $order->get_customer_note() );
		}
		if ( ! $details['source'] ) {
			$details['source'] = $details['reference'] ? 'Amazon link / ASIN' : ( ( ! empty( $details['uploaded_file_objects'] ) || $details['uploaded_files'] ) ? 'Uploaded product files' : 'Product submission' );
		}
		$brief = $order->get_meta( '_aip_delivery_brief' );
		if ( is_array( $brief ) ) {
			$labels = array(
				'model_profile' => 'Model profile',
				'scene'         => 'Scene & environment',
				'lighting'      => 'Time of day / lighting',
				'format'        => 'Format',
				'transition'    => 'Transition style',
				'video_filter'  => 'Video aesthetic',
				'pose'          => 'Pose direction',
			);
			foreach ( $labels as $key => $label ) {
				if ( ! empty( $brief[ $key ] ) && is_scalar( $brief[ $key ] ) ) {
					$value = ucwords( str_replace( array( '_', '-' ), ' ', sanitize_text_field( (string) $brief[ $key ] ) ) );
					$details['production'][] = array( 'label' => $label, 'value' => $value );
				}
			}
		}
		return $details;
	}

	private static function record_download( $order, $file_key ) {
		$stats = $order->get_meta( '_aip_download_stats' );
		$stats = is_array( $stats ) ? $stats : array();
		$now   = current_time( DATE_ATOM );
		$entry = isset( $stats[ $file_key ] ) && is_array( $stats[ $file_key ] ) ? $stats[ $file_key ] : array();
		$entry['count']               = absint( $entry['count'] ?? 0 ) + 1;
		$entry['first_downloaded_at'] = $entry['first_downloaded_at'] ?? $now;
		$entry['last_downloaded_at']  = $now;
		$stats[ $file_key ]           = $entry;
		$order->update_meta_data( '_aip_download_stats', $stats );
		$order->save();
	}

	private static function stream_delivery_file( $order, $file_key, $mode ) {
		$files = self::delivery_files( $order );
		if ( ! isset( $files[ $file_key ] ) ) {
			status_header( 404 );
			exit;
		}
		$file          = $files[ $file_key ];
		$attachment_id = attachment_url_to_postid( $file['url'] );
		$path          = $attachment_id ? get_attached_file( $attachment_id ) : '';
		if ( ! $path || ! is_readable( $path ) ) {
			status_header( 404 );
			exit;
		}
		if ( 'download' === $mode ) {
			self::record_download( $order, $file_key );
		}
		$filename = sanitize_file_name( basename( $path ) );
		$filetype = wp_check_filetype( $filename );
		nocache_headers();
		header( 'X-Robots-Tag: noindex, nofollow', true );
		header( 'X-Content-Type-Options: nosniff', true );
		header( 'Content-Type: ' . ( $filetype['type'] ?: 'application/octet-stream' ) );
		header( 'Content-Length: ' . filesize( $path ) );
		header( 'Content-Disposition: ' . ( 'download' === $mode ? 'attachment' : 'inline' ) . '; filename="' . $filename . '"' );
		while ( ob_get_level() ) {
			ob_end_clean();
		}
		readfile( $path );
		exit;
	}

	public static function passwordless_delivery_request() {
		if ( empty( $_GET['aip_order_delivery'] ) || empty( $_GET['aip_token'] ) ) {
			return;
		}
		$order = self::delivery_order_from_request();
		if ( ! $order ) {
			status_header( 404 );
			nocache_headers();
			echo '<h1>Delivery link unavailable</h1><p>This link is invalid or the order has not been submitted yet.</p>';
			exit;
		}
		$file_key = isset( $_GET['aip_file'] ) ? sanitize_key( wp_unslash( $_GET['aip_file'] ) ) : '';
		$mode     = isset( $_GET['aip_mode'] ) && 'preview' === sanitize_key( wp_unslash( $_GET['aip_mode'] ) ) ? 'preview' : 'download';
		if ( $file_key ) {
			self::stream_delivery_file( $order, $file_key, $mode );
		}

		$customer_orders = self::delivery_orders_for_customer( $order );
		$total_videos    = 0;
		foreach ( $customer_orders as $library_order ) {
			foreach ( self::delivery_files( $library_order ) as $library_file ) {
				if ( 'video' === $library_file['type'] ) {
					++$total_videos;
				}
			}
		}
		status_header( 200 );
		nocache_headers();
		header( 'X-Robots-Tag: noindex, nofollow', true );
		?>
		<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Your REii Content Library</title>
		<style>:root{--ink:#211b28;--muted:#756d7d;--line:#e8e3eb;--page:#f4f2f6;--purple:#6846e6;--purple-dark:#5634d1;--purple-soft:#f2eeff}*{box-sizing:border-box}body{background:var(--page);color:var(--ink);font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;margin:0;padding:34px 18px;-webkit-font-smoothing:antialiased}.wrap{margin:0 auto;max-width:1080px}.head{background:#1b1622;border-radius:20px;color:#fff;margin-bottom:22px;padding:34px 38px}.head small{color:#bfa8ff;display:block;font-size:10px;font-weight:800;letter-spacing:1.8px;margin-bottom:7px;text-transform:uppercase}.head-row{align-items:end;display:flex;gap:24px;justify-content:space-between}.head h1{font-size:34px;font-weight:800;letter-spacing:-.8px;margin:0 0 5px}.head p{color:#cdc6d5;font-size:14px;margin:0}.library-count{color:#d9d2e2;font-size:12px;font-weight:700;white-space:nowrap}.content{display:grid;gap:20px}.order{background:#fff;border:1px solid var(--line);border-radius:18px;box-shadow:0 8px 30px rgba(32,23,42,.05);overflow:hidden}.order-head{align-items:center;border-bottom:1px solid var(--line);display:grid;gap:15px;grid-template-columns:auto 1fr auto;padding:18px 22px}.order-thumb{border-radius:10px;height:68px;object-fit:cover;width:58px}.order-kicker{color:var(--purple);font-size:9px;font-weight:800;letter-spacing:1.3px;margin:0 0 4px;text-transform:uppercase}.order-head h2{font-size:21px;letter-spacing:-.4px;margin:0 0 4px}.order-meta{color:var(--muted);font-size:12px}.delivered-pill{background:#edf9f1;border:1px solid #cdebd7;border-radius:999px;color:#24733f;font-size:10px;font-weight:800;padding:7px 10px;text-transform:uppercase}.order-body{display:grid;gap:26px;grid-template-columns:minmax(0,1fr) 290px;padding:24px}.brief-title{font-size:15px;margin:0 0 15px}.detail-grid{display:grid;gap:15px 20px;grid-template-columns:repeat(2,minmax(0,1fr));margin:0}.detail{border-bottom:1px solid #f0ecf2;padding-bottom:12px}.detail.full{grid-column:1/-1}.detail dt{color:#8a818f;font-size:9px;font-weight:800;letter-spacing:1px;margin-bottom:5px;text-transform:uppercase}.detail dd{color:#2c2532;font-size:13px;font-weight:650;line-height:1.45;margin:0;overflow-wrap:anywhere}.detail dd.request{font-weight:500}.video-stack{align-self:start;display:grid;gap:14px}.video-card{background:#fff;border:1px solid var(--line);border-radius:14px;box-shadow:0 4px 16px rgba(0,0,0,.04);display:flex;flex-direction:column;overflow:hidden}.video-card video{aspect-ratio:9/16;background:#000;display:block;height:auto;max-height:none;object-fit:contain;width:100%}.video-actions{background:#fff;border:0;border-top:1px solid var(--line);padding:14px}.video-actions strong{display:block;font-size:13px;margin-bottom:10px}.button{background:var(--purple);border-radius:9px;color:#fff;display:block;font-size:13px;font-weight:750;padding:11px 14px;text-align:center;text-decoration:none}.button:hover{background:var(--purple-dark)}.upsell{align-items:center;background:var(--purple-soft);border-top:1px solid #dfd5ff;display:grid;gap:20px;grid-template-columns:1fr auto;padding:20px 24px}.upsell small{color:var(--purple);display:block;font-size:9px;font-weight:850;letter-spacing:1.2px;margin-bottom:5px;text-transform:uppercase}.upsell h3{font-size:16px;margin:0 0 4px}.upsell p{color:#665d70;font-size:12px;line-height:1.45;margin:0}.upsell-actions{display:flex;gap:9px}.upsell-link{border:1px solid #cfc2fa;border-radius:8px;color:#5237be;font-size:12px;font-weight:750;padding:10px 13px;text-decoration:none;white-space:nowrap}.upsell-link.primary{background:var(--purple);border-color:var(--purple);color:#fff}.note{color:#7c7384;font-size:12px;margin:4px 0 0;text-align:center}.aip-intake-gallery{display:grid;gap:10px;grid-template-columns:repeat(auto-fill,minmax(84px,1fr));margin-top:8px}.aip-intake-thumb{background:#fff;border:1px solid #e3dcee;border-radius:10px;display:block;overflow:hidden;text-align:center;text-decoration:none!important;transition:transform .15s ease,box-shadow .15s ease}.aip-intake-thumb:hover{box-shadow:0 4px 14px rgba(104,70,230,.15);transform:translateY(-1px)}.aip-intake-thumb img{aspect-ratio:1;background:#fff;display:block;object-fit:cover;width:100%}.aip-intake-thumb-icon{align-items:center;background:#f3eeff;color:#6846e6;display:flex;font-size:22px;height:84px;justify-content:center}.aip-intake-thumb span{color:#342a3e;display:block;font-size:10px;font-weight:700;overflow:hidden;padding:5px 4px;text-overflow:ellipsis;white-space:nowrap}@media(max-width:760px){body{padding:16px 10px}.head{border-radius:15px;padding:26px 22px}.head-row{align-items:start;flex-direction:column;gap:14px}.head h1{font-size:28px}.order-head{grid-template-columns:auto 1fr;padding:15px}.delivered-pill{display:none}.order-body{grid-template-columns:1fr;padding:18px}.detail-grid{grid-template-columns:1fr}.detail.full{grid-column:auto}.video-card video{aspect-ratio:9/16;height:auto;max-height:none;object-fit:contain;width:100%}.upsell{grid-template-columns:1fr;padding:18px}.upsell-actions{flex-direction:column}.upsell-link{text-align:center}}</style></head><body><main class="wrap"><header class="head"><small>TECH BY LEON</small><div class="head-row"><div><h1>Your video library</h1><p>Every finished product video, request brief, and download in one place.</p></div><span class="library-count"><?php echo esc_html( count( $customer_orders ) ); ?> completed order<?php echo 1 === count( $customer_orders ) ? '' : 's'; ?> · <?php echo esc_html( $total_videos ); ?> video<?php echo 1 === $total_videos ? '' : 's'; ?></span></div></header><section class="content">
		<style>
		.head{display:none}.reii-library-head{background:#1b1622;border-radius:20px;color:#fff;margin-bottom:22px;padding:34px 38px}.reii-library-head small{color:#bfa8ff;display:block;font-size:10px;font-weight:800;letter-spacing:1.8px;margin-bottom:7px;text-transform:uppercase}.reii-library-head-row{align-items:end;display:flex;gap:24px;justify-content:space-between}.reii-library-head h1{font-size:34px;font-weight:800;letter-spacing:-.8px;margin:0 0 5px}.reii-library-head p{color:#cdc6d5;font-size:14px;margin:0}.upsell{display:none}.delivery-reorder-wrap{display:flex;justify-content:center;margin:18px 0 10px}.delivery-reorder-button{background:var(--purple);border-radius:10px;color:#fff;display:inline-flex;align-items:center;justify-content:center;font-size:14px;font-weight:750;padding:14px 34px;text-align:center;text-decoration:none;transition:background .15s ease,transform .15s ease;white-space:nowrap}.delivery-reorder-button:hover{background:var(--purple-dark);transform:translateY(-1px)}@media(max-width:760px){.reii-library-head{border-radius:15px;padding:26px 22px}.reii-library-head-row{align-items:start;flex-direction:column;gap:14px}.reii-library-head h1{font-size:28px}.delivery-reorder-wrap{margin:14px 0 8px}.delivery-reorder-button{display:flex;width:100%;padding:13px 20px}}
		</style>
		<header class="reii-library-head"><small>REIMAGINE · REii AI INFLUENCER</small><div class="reii-library-head-row"><div><h1>Your REii content library</h1><p>Every finished AI influencer UGC video, creative brief, and download in one private place.</p></div><span class="library-count"><?php echo esc_html( count( $customer_orders ) ); ?> completed order<?php echo 1 === count( $customer_orders ) ? '' : 's'; ?> · <?php echo esc_html( $total_videos ); ?> video<?php echo 1 === $total_videos ? '' : 's'; ?></span></div></header>
		<?php foreach ( $customer_orders as $customer_order ) :
			$files = self::delivery_files( $customer_order );
			$details = self::delivery_order_details( $customer_order );
			$first_img_key = false;
			foreach ( $files as $k => $f ) {
				if ( 'image' === $f['type'] ) { $first_img_key = $k; break; }
			}
			$poster_url = $first_img_key ? self::tracked_file_url( $customer_order, $first_img_key, 'preview' ) : '';
			$variation_url = add_query_arg( array( 'aip_offer' => 'new-version', 'source_order' => $customer_order->get_id() ), home_url( '/style-by-reii/' ) ) . '#submit-project';
			$new_product_url = add_query_arg( array( 'aip_offer' => 'new-product', 'source_order' => $customer_order->get_id() ), home_url( '/style-by-reii/' ) ) . '#submit-project';
		?>
		<article class="order"><header class="order-head"><?php if ( $poster_url ) : ?><img class="order-thumb" src="<?php echo esc_url( $poster_url ); ?>" alt="Order thumbnail"><?php endif; ?><div><p class="order-kicker">Completed content</p><h2>Order #<?php echo esc_html( $customer_order->get_order_number() ); ?></h2><span class="order-meta"><?php echo esc_html( wc_format_datetime( $customer_order->get_date_created() ) ); ?> · <?php echo wp_kses_post( $customer_order->get_formatted_order_total() ); ?></span></div><span class="delivered-pill">Delivered</span></header><div class="order-body"><section><h3 class="brief-title">What you requested</h3><dl class="detail-grid"><div class="detail"><dt>Package</dt><dd><?php echo esc_html( $details['package'] ?: 'On-Model Content Package' ); ?></dd></div><div class="detail"><dt>Product source</dt><dd><?php echo esc_html( $details['source'] ); ?></dd></div><?php if ( $details['reference'] ) : $ref_asin = self::extract_asin( $details['reference'] ); $ref_label = $ref_asin ? 'ASIN: ' . $ref_asin : self::format_reference_for_display( $details['reference'] ); $amazon_url = $ref_asin ? "https://www.amazon.com/dp/{$ref_asin}" : ( preg_match( '/^https?:\/\//i', $details['reference'] ) ? $details['reference'] : '' ); $product_img = $ref_asin ? "https://images-na.ssl-images-amazon.com/images/P/{$ref_asin}.01.MAIN._AC_SY300_.jpg" : ( ! empty( $details['uploaded_file_objects'] ) ? ( $details['uploaded_file_objects'][0]['url'] ?? '' ) : '' ); ?><div class="detail full"><dt>Amazon link / ASIN</dt><dd style="display:flex; align-items:center; gap:14px; margin-top:6px;"><?php if ( $product_img ) : ?><a href="<?php echo esc_url( $amazon_url ?: '#' ); ?>" <?php if ( $amazon_url ) echo 'target="_blank" rel="noopener"'; ?> style="display:block; flex-shrink:0;"><img src="<?php echo esc_url( $product_img ); ?>" alt="Product thumbnail" style="width:58px; height:70px; object-fit:contain; border-radius:8px; border:1px solid #e3dcee; background:#ffffff; display:block;" onload="if(this.naturalWidth<=1&&this.naturalHeight<=1){this.style.display='none';if(this.parentElement)this.parentElement.style.display='none';}" onerror="this.style.display='none';if(this.parentElement)this.parentElement.style.display='none';"></a><?php endif; ?><div><strong style="font-size:14px; color:#211b28; display:block; font-family:monospace,sans-serif; font-weight:750;"><?php echo esc_html( $ref_label ); ?></strong><?php if ( $amazon_url ) : ?><a href="<?php echo esc_url( $amazon_url ); ?>" target="_blank" rel="noopener" style="color:#6846e6; font-size:12px; font-weight:750; text-decoration:none; display:inline-block; margin-top:3px;">View on Amazon ↗</a><?php endif; ?></div></dd></div><?php endif; ?><?php if ( ! empty( $details['uploaded_file_objects'] ) || $details['uploaded_files'] ) : ?><div class="detail full"><dt>Uploaded product files</dt><dd><?php if ( ! empty( $details['uploaded_file_objects'] ) ) : ?><div class="aip-intake-gallery"><?php foreach ( $details['uploaded_file_objects'] as $u_file ) : $f_url = is_array( $u_file ) ? ( $u_file['url'] ?? '' ) : ( is_string( $u_file ) ? $u_file : '' ); $f_name = is_array( $u_file ) ? ( $u_file['name'] ?? 'Uploaded file' ) : (string) $u_file; $is_img = $f_url && ( preg_match( '/\.(jpg|jpeg|png|webp|gif|svg)$/i', $f_url ) || strpos( $f_url, 'data:image' ) === 0 ); ?><a href="<?php echo esc_url( $f_url ?: '#' ); ?>" <?php if ( $f_url ) echo 'target="_blank" rel="noopener"'; ?> class="aip-intake-thumb" title="<?php echo esc_attr( $f_name ); ?>"><?php if ( $is_img ) : ?><img src="<?php echo esc_url( $f_url ); ?>" alt="<?php echo esc_attr( $f_name ); ?>"><?php else : ?><div class="aip-intake-thumb-icon">📄</div><?php endif; ?><span><?php echo esc_html( $f_name ); ?></span></a><?php endforeach; ?></div><?php else : ?><?php echo esc_html( $details['uploaded_files'] ); ?><?php endif; ?></dd></div><?php endif; ?><div class="detail full"><dt>Your instructions</dt><dd class="request"><?php echo esc_html( $details['instructions'] ?: 'No additional instructions were provided.' ); ?></dd></div><?php foreach ( $details['production'] as $production_detail ) : ?><div class="detail"><dt><?php echo esc_html( $production_detail['label'] ); ?></dt><dd><?php echo esc_html( $production_detail['value'] ); ?></dd></div><?php endforeach; ?></dl></section><aside class="video-stack"><?php foreach ( $files as $key => $file ) : if ( 'video' !== $file['type'] ) continue; ?><section class="video-card"><video controls playsinline preload="metadata"<?php if ( $poster_url ) echo ' poster="' . esc_url( $poster_url ) . '"'; ?> src="<?php echo esc_url( self::tracked_file_url( $customer_order, $key, 'preview' ) ); ?>"></video><div class="video-actions"><strong><?php echo esc_html( $file['label'] ); ?></strong><a class="button" href="<?php echo esc_url( self::tracked_file_url( $customer_order, $key ) ); ?>">Download HD video</a></div></section><?php endforeach; ?></aside></div><footer class="upsell"><div><small>Make more from this product</small><h3>Turn this order into your next piece of content</h3><p>Request another hook, scene, or video cut—or start fresh with a new product.</p></div><div class="upsell-actions"><a class="upsell-link" href="<?php echo esc_url( $variation_url ); ?>">Create another version</a><a class="upsell-link primary" href="<?php echo esc_url( $new_product_url ); ?>">Start a new product</a></div></footer></article><?php endforeach; ?>
		<div class="delivery-reorder-wrap">
			<a class="delivery-reorder-button" href="<?php echo esc_url( home_url( '/style-by-reii/#submit-project' ) ); ?>">Create another video</a>
		</div>
		<p class="note">This private link stays available whenever you need to download your finished REii content again.</p></section></main></body></html>
		<?php
		exit;
	}

	public static function service_product() {
		$product_id = absint( get_option( 'aip_on_model_product_id' ) );
		$product    = $product_id ? wc_get_product( $product_id ) : false;

		if ( ! $product ) {
			$product_id = wc_get_product_id_by_sku( self::PRODUCT_SKU );
			$product    = $product_id ? wc_get_product( $product_id ) : false;
			if ( $product ) {
				update_option( 'aip_on_model_product_id', $product->get_id(), false );
			}
		}

		return $product;
	}

	public static function ensure_service_product() {
		if ( ! class_exists( 'WC_Product_Simple' ) ) {
			return;
		}

		$product = self::service_product();
		if ( ! $product ) {
			$product = new WC_Product_Simple();
			$product->set_sku( self::PRODUCT_SKU );
		}
		$product->set_name( 'REii AI-Generated UGC Video' );
		$product->set_slug( 'style-by-reii-shoppable-video-feature' );
		$product->set_status( 'publish' );
		$product->set_catalog_visibility( 'hidden' );
		$product->set_virtual( true );
		$product->set_sold_individually( true );
		$product->set_regular_price( self::BASE_PRICE );
		$product->set_price( self::BASE_PRICE );
		$product->set_description( 'One 10-second vertical video featuring your product, transparently created as AI influencer UGC by REii and delivered as a social-ready HD file.' );
		$product_id = $product->save();

		if ( $product_id ) {
			update_option( 'aip_on_model_product_id', $product_id, false );
		}
	}

	public static function service_product_notice() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$product = self::service_product();
		if ( ! $product || $product->is_purchasable() ) {
			return;
		}

		$url = $product ? get_edit_post_link( $product->get_id() ) : admin_url( 'edit.php?post_type=product' );
		?>
		<div class="notice notice-warning"><p><strong>REii checkout needs attention.</strong> Review the hidden AI influencer UGC service product to enable the intake-to-checkout flow. <a href="<?php echo esc_url( $url ); ?>">Configure service product</a>.</p></div>
		<?php
	}

	public static function checkout_bridge_script() {
		if ( ! is_page( array( 'style-by-reii', 'on-model-content' ) ) ) {
			return;
		}

		$product = self::service_product();
		$config  = array(
			'checkoutUrl'         => aip_reii_same_origin_checkout_url_v0557( false ),
			'embeddedCheckoutUrl' => aip_reii_same_origin_checkout_url_v0557(),
			'ready'               => (bool) ( $product && $product->is_purchasable() ),
			'notReady'            => 'Checkout is being configured. Your product details were saved, but payment is not available yet.',
		);

		wp_enqueue_script( 'jquery' );
		wp_add_inline_script( 'jquery', 'window.aipCommerceConfig=' . wp_json_encode( $config ) . ';', 'after' );
		wp_add_inline_script(
			'jquery',
			"(function(){function clearPortalFiles(portal){if(!portal)return;var forms=portal.querySelectorAll('form.wpcf7-form, form');forms.forEach(function(form){try{form.reset();}catch(e){};form.classList.remove('submitting','sent','failed','invalid','spam');form.setAttribute('data-status','init');var responseOutput=form.querySelector('.wpcf7-response-output');if(responseOutput)responseOutput.textContent='';var submitBtn=form.querySelector('input[type=\"submit\"], button[type=\"submit\"]');if(submitBtn)submitBtn.disabled=false;});var inputs=portal.querySelectorAll('input[name^=\"product-file-\"]');inputs.forEach(function(inp){inp.value='';try{inp.files=(new DataTransfer()).files;}catch(e){}inp.dispatchEvent(new Event('change',{bubbles:true}));});var refInput=portal.querySelector('input[name=\"product-reference\"]');if(refInput)refInput.value='';var emailInput=portal.querySelector('input[name=\"your-email\"]');if(emailInput)emailInput.value='';var notesInput=portal.querySelector('textarea[name=\"your-message\"]');if(notesInput)notesInput.value='';var previewList=portal.querySelector('.aip-drop-preview-list');if(previewList){previewList.innerHTML='';previewList.hidden=true;}var dropzone=portal.querySelector('.aip-dropzone');if(dropzone)dropzone.classList.remove('has-file');var dropFooter=portal.querySelector('.aip-drop-footer');if(dropFooter)dropFooter.hidden=true;}function closeDrawer(drawer,portal){if(!drawer){return;}drawer.classList.remove('is-open');document.body.classList.remove('aip-drawer-open');window.setTimeout(function(){drawer.remove();},240);}function openDrawer(cfg,portal){var old=document.querySelector('.aip-checkout-drawer');if(old){old.remove();}var submittedEmail=(portal.querySelector('input[name=\"your-email\"]')||{}).value||'';var drawer=document.createElement('div');drawer.className='aip-checkout-drawer';drawer.setAttribute('role','dialog');drawer.setAttribute('aria-modal','true');drawer.setAttribute('aria-label','Secure checkout');drawer.innerHTML='<button class=\"aip-checkout-backdrop\" type=\"button\" aria-label=\"Close checkout\"></button><section class=\"aip-checkout-panel\"><header><div><small>STEP 2 OF 3</small><strong>Complete your order</strong></div><button class=\"aip-checkout-close\" type=\"button\" aria-label=\"Close checkout\">&times;</button></header><div class=\"aip-checkout-loading\"><span></span>Loading secure payment...</div><iframe title=\"Secure checkout\" allow=\"payment *\" src=\"'+String(cfg.embeddedCheckoutUrl||cfg.checkoutUrl)+'\"></iframe></section>';document.body.appendChild(drawer);document.body.classList.add('aip-drawer-open');window.requestAnimationFrame(function(){drawer.classList.add('is-open');});function updateResponse(){portal.querySelectorAll('.wpcf7-response-output,.screen-reader-response').forEach(function(response){response.textContent='Your product is saved. Complete payment to place your order.';});}updateResponse();window.setTimeout(updateResponse,50);window.setTimeout(updateResponse,500);var frame=drawer.querySelector('iframe');function sendEmail(){if(submittedEmail&&frame.contentWindow){frame.contentWindow.postMessage({type:'aipCheckoutEmail',email:submittedEmail},window.location.origin);}}frame.addEventListener('load',function(){drawer.classList.add('is-loaded');window.setTimeout(sendEmail,100);window.setTimeout(sendEmail,700);try{if(frame.contentWindow.location.href.indexOf('/order-received/')!==-1){drawer.classList.add('is-complete');drawer.querySelector('header small').textContent='STEP 3 OF 3';drawer.querySelector('header strong').textContent='Order confirmed';clearPortalFiles(portal);}}catch(ignore){}});window.addEventListener('message',function(event){if(event.origin===window.location.origin&&event.source===frame.contentWindow&&event.data&&event.data.type==='aipCheckoutReady'){sendEmail();}});drawer.querySelectorAll('.aip-checkout-close,.aip-checkout-backdrop').forEach(function(button){button.addEventListener('click',function(){closeDrawer(drawer,portal);});});document.addEventListener('keydown',function escape(event){if(event.key==='Escape'&&document.body.contains(drawer)){closeDrawer(drawer,portal);document.removeEventListener('keydown',escape);}});}document.addEventListener('wpcf7mailsent',function(event){var cfg=window.aipCommerceConfig||{};var portal=event.target&&event.target.closest('.aip-portal');if(!portal){return;}var error=portal.querySelector('.aip-form-error');if(!cfg.ready){if(error){error.textContent=cfg.notReady||'Checkout is not available yet.';}return;}openDrawer(cfg,portal);});})();",
			'after'
		);
	}

	public static function cart_has_service_product() {
		$product = self::service_product();
		if ( ! $product || ! function_exists( 'WC' ) || ! WC()->cart ) {
			return false;
		}
		foreach ( WC()->cart->get_cart() as $cart_item ) {
			if ( isset( $cart_item['product_id'] ) && $product->get_id() === (int) $cart_item['product_id'] ) {
				return true;
			}
		}
		return false;
	}

	public static function fast_checkout_body_class( $classes ) {
		if ( is_checkout() ) {
			$is_complete = aip_reii_is_reii_order_v0551( aip_reii_order_received_order_v0551() );
			if ( isset( $_GET['aip_embedded'] ) || $is_complete ) {
				$classes[] = 'aip-embedded-checkout';
				if ( $is_complete ) {
					$classes[] = 'aip-checkout-complete';
				}
				show_admin_bar( false );
			}
		}
		return $classes;
	}

	public static function optional_address_fields( $fields ) {
		if ( ! self::cart_has_service_product() ) {
			return $fields;
		}
		foreach ( array( 'first_name', 'last_name', 'company', 'address_1', 'address_2', 'city', 'state', 'postcode' ) as $key ) {
			if ( isset( $fields[ $key ] ) ) {
				$fields[ $key ]['required'] = false;
			}
		}
		return $fields;
	}

	public static function optional_billing_fields( $fields ) {
		if ( ! self::cart_has_service_product() ) {
			return $fields;
		}
		foreach ( array( 'billing_first_name', 'billing_last_name', 'billing_company', 'billing_address_1', 'billing_address_2', 'billing_city', 'billing_state', 'billing_postcode', 'billing_phone' ) as $key ) {
			if ( isset( $fields[ $key ] ) ) {
				$fields[ $key ]['required'] = false;
			}
		}
		return $fields;
	}

	/**
	 * The intake form already collects the customer's email and the service product
	 * is virtual, so an address form adds friction without adding order data. Keep
	 * the email as a hidden checkout field for payment gateways and order emails.
	 */
	public static function remove_service_billing_fields( $fields ) {
		if ( ! self::cart_has_service_product() || empty( $fields['billing'] ) ) {
			return $fields;
		}

		$email   = isset( $fields['billing']['billing_email'] ) ? $fields['billing']['billing_email'] : array();
		$country = isset( $fields['billing']['billing_country'] ) ? $fields['billing']['billing_country'] : array();
		$email['type']     = 'hidden';
		$email['required'] = false;
		$email['label']    = '';
		$country['type']     = 'hidden';
		$country['required'] = false;
		$country['label']    = '';
		$country['default']  = aip_reii_default_billing_country();

		$fields['billing'] = array(
			'billing_email'   => $email,
			'billing_country' => $country,
		);

		return $fields;
	}

	public static function fast_checkout_styles() {
		if ( ! is_page( array( 'style-by-reii', 'on-model-content' ) ) && ! is_checkout() ) {
			return;
		}
		$css = '
		body.aip-drawer-open{overflow:hidden}.aip-checkout-drawer{inset:0;opacity:0;pointer-events:none;position:fixed;transition:opacity .22s ease;z-index:999999}.aip-checkout-drawer.is-open{opacity:1;pointer-events:auto}.aip-checkout-backdrop{backdrop-filter:blur(7px);background:rgba(20,15,27,.58);border:0;inset:0;position:absolute;width:100%}.aip-checkout-panel{background:#f8f7fb;box-shadow:-24px 0 80px rgba(22,14,31,.22);height:100%;max-width:760px;position:absolute;right:0;top:0;transform:translateX(102%);transition:transform .28s ease;width:min(94vw,760px)}.aip-checkout-drawer.is-open .aip-checkout-panel{transform:translateX(0)}.aip-checkout-panel header{align-items:center;background:#fff;border-bottom:1px solid #e7e2ec;display:flex;height:76px;justify-content:space-between;padding:0 25px}.aip-checkout-panel header small,.aip-checkout-panel header strong{display:block}.aip-checkout-panel header small{color:#7656df;font-size:9px;font-weight:800;letter-spacing:1.4px}.aip-checkout-panel header strong{color:#231d29;font-size:21px;letter-spacing:-.5px;margin-top:2px}.aip-checkout-close{align-items:center;background:#f2eff6;border:0;border-radius:50%;color:#43394b;cursor:pointer;display:flex;font-size:25px;height:38px;justify-content:center;line-height:1;width:38px}.aip-checkout-panel iframe{background:#f8f7fb;border:0;height:calc(100% - 76px);opacity:0;position:relative;transition:opacity .2s ease;width:100%;z-index:2}.aip-checkout-drawer.is-loaded iframe{opacity:1}.aip-checkout-loading{align-items:center;color:#6f6578;display:flex;font-size:12px;gap:11px;left:50%;position:absolute;top:50%;transform:translate(-50%,-50%)}.aip-checkout-loading span{animation:aip-spin .8s linear infinite;border:2px solid #d9d0e5;border-radius:50%;border-top-color:#6846e6;height:22px;width:22px}@keyframes aip-spin{to{transform:rotate(360deg)}}
		body.woocommerce-checkout.aip-embedded-checkout #wpadminbar,body.woocommerce-checkout.aip-embedded-checkout #masthead,body.woocommerce-checkout.aip-embedded-checkout #colophon,body.woocommerce-checkout.aip-embedded-checkout .post-title-wrapper,#wpadminbar{display:none!important}html{margin-top:0!important;padding-top:0!important}body.woocommerce-checkout.aip-embedded-checkout{background:#f8f7fb!important;margin-top:0!important}body.woocommerce-checkout.aip-embedded-checkout .main-container,body.woocommerce-checkout.aip-embedded-checkout .page-body{background:#f8f7fb!important;padding:0!important}body.woocommerce-checkout.aip-embedded-checkout .row-parent{margin:0 auto!important;max-width:580px!important;padding:16px 18px 30px!important}body.woocommerce-checkout.aip-embedded-checkout .woocommerce-billing-fields,body.woocommerce-checkout.aip-embedded-checkout .woocommerce-billing-fields__field-wrapper,body.woocommerce-checkout.aip-embedded-checkout .woocommerce-shipping-fields,body.woocommerce-checkout.aip-embedded-checkout .woocommerce-additional-fields,body.woocommerce-checkout.aip-embedded-checkout #customer_details,body.woocommerce-checkout.aip-embedded-checkout .woocommerce-checkout #order_review_heading{display:none!important}body.woocommerce-checkout.aip-embedded-checkout .wp-block-woocommerce-checkout{background:transparent!important;border:0!important;box-shadow:none!important;margin:0!important;max-width:none!important;padding:0!important}body.woocommerce-checkout.aip-embedded-checkout .wp-block-woocommerce-checkout,body.woocommerce-checkout.aip-embedded-checkout .wc-block-checkout,body.woocommerce-checkout.aip-embedded-checkout form.woocommerce-checkout,body.woocommerce-checkout.aip-embedded-checkout .wc-block-components-sidebar-layout{display:flex!important;flex-direction:column!important}body.woocommerce-checkout.aip-embedded-checkout .wc-block-components-main,body.woocommerce-checkout.aip-embedded-checkout .wc-block-checkout__main{display:contents!important}body.woocommerce-checkout.aip-embedded-checkout .wc-block-components-sidebar{float:none!important;margin:0!important;padding:0!important;width:100%!important}body.woocommerce-checkout.aip-embedded-checkout .wc-block-checkout__billing-fields,body.woocommerce-checkout.aip-embedded-checkout .wc-block-checkout__billing-fields .wc-block-components-checkout-step__container{display:none!important}body.woocommerce-checkout.aip-embedded-checkout .wc-block-checkout.is-medium>.wc-block-components-sidebar,body.woocommerce-checkout.aip-embedded-checkout .wc-block-checkout.is-small>.wc-block-components-sidebar{display:none!important}body.woocommerce-checkout.aip-embedded-checkout .wc-block-express-payment,body.woocommerce-checkout.aip-embedded-checkout .wc-block-components-express-payment,body.woocommerce-checkout.aip-embedded-checkout .wc-block-checkout__express-payment,body.woocommerce-checkout.aip-embedded-checkout .wc-block-components-express-payment-continue-rule{order:1!important}body.woocommerce-checkout.aip-embedded-checkout .wc-block-checkout__contact-fields,body.woocommerce-checkout.aip-embedded-checkout .wc-block-components-checkout-step--contact{order:2!important}body.woocommerce-checkout.aip-embedded-checkout .wc-block-checkout__payment-method,body.woocommerce-checkout.aip-embedded-checkout .wc-block-components-checkout-step--payment,body.woocommerce-checkout.aip-embedded-checkout #payment,body.woocommerce-checkout.aip-embedded-checkout .payment_methods{order:3!important;margin-bottom:20px!important}body.woocommerce-checkout.aip-embedded-checkout .wc-block-components-sidebar,body.woocommerce-checkout.aip-embedded-checkout .wc-block-checkout__sidebar,body.woocommerce-checkout.aip-embedded-checkout #order_review,body.woocommerce-checkout.aip-embedded-checkout .woocommerce-checkout-review-order{order:4!important;background:#fff!important;border:1px solid #e4dfe8!important;border-radius:14px!important;margin:0 0 20px!important;padding:16px!important;width:100%!important}body.woocommerce-checkout.aip-embedded-checkout .wc-block-components-sidebar *,body.woocommerce-checkout.aip-embedded-checkout .wc-block-checkout__sidebar *,body.woocommerce-checkout.aip-embedded-checkout #order_review *,body.woocommerce-checkout.aip-embedded-checkout .wc-block-components-product-details,body.woocommerce-checkout.aip-embedded-checkout .wc-block-components-product-metadata,body.woocommerce-checkout.aip-embedded-checkout .wc-item-meta,body.woocommerce-checkout.aip-embedded-checkout .wc-item-meta li{overflow-wrap:anywhere!important;word-break:break-word!important;max-width:100%!important}body.woocommerce-checkout.aip-embedded-checkout .woocommerce-form-coupon-toggle,body.woocommerce-checkout.aip-embedded-checkout .wc-block-components-totals-coupon,body.woocommerce-checkout.aip-embedded-checkout .wc-block-checkout__coupon-form,body.woocommerce-checkout.aip-embedded-checkout .wc-block-components-totals-coupon-link,body.woocommerce-checkout.aip-embedded-checkout form.checkout_coupon,body.woocommerce-checkout.aip-embedded-checkout .checkout_coupon,body.woocommerce-checkout.aip-embedded-checkout .wc-block-components-panel.wc-block-checkout__coupon-form{order:5!important;margin-top:8px!important;margin-bottom:18px!important}body.woocommerce-checkout.aip-embedded-checkout .wc-block-checkout__additional-fields,body.woocommerce-checkout.aip-embedded-checkout .wc-block-checkout__terms{order:6!important}body.woocommerce-checkout.aip-embedded-checkout .wc-block-checkout__actions,body.woocommerce-checkout.aip-embedded-checkout .wc-block-components-checkout-place-order-button,body.woocommerce-checkout.aip-embedded-checkout .place-order{order:7!important}body.woocommerce-checkout.aip-embedded-checkout .wc-block-components-checkout-step{padding-left:0!important}body.woocommerce-checkout.aip-embedded-checkout .wc-block-components-checkout-step__container{margin-left:0!important}body.woocommerce-checkout.aip-embedded-checkout .wc-block-components-checkout-step__heading{margin-bottom:13px!important}body.woocommerce-checkout.aip-embedded-checkout .wc-block-components-title{font-size:18px!important}body.woocommerce-checkout.aip-embedded-checkout .wc-block-components-checkout-step__description{font-size:11px!important}body.woocommerce-checkout.aip-embedded-checkout .wc-block-components-button{min-height:56px!important}body.woocommerce-checkout.aip-embedded-checkout .woocommerce{display:flex!important;flex-direction:column!important}body.woocommerce-checkout.aip-embedded-checkout form.checkout.woocommerce-checkout,body.woocommerce-checkout.aip-embedded-checkout #order_review,body.woocommerce-checkout.aip-embedded-checkout #payment{display:contents!important}body.woocommerce-checkout.aip-embedded-checkout #wc-stripe-express-checkout-element{order:10!important}body.woocommerce-checkout.aip-embedded-checkout #wc-stripe-express-checkout__order-attribution-inputs{display:none!important}body.woocommerce-checkout.aip-embedded-checkout #wc-stripe-express-checkout-button-separator{order:20!important}body.woocommerce-checkout.aip-embedded-checkout .payment_methods{margin:0 0 20px!important;order:30!important}body.woocommerce-checkout.aip-embedded-checkout .shop_table.woocommerce-checkout-review-order-table{background:#fff!important;border:1px solid #e4dfe8!important;border-radius:14px!important;box-shadow:none!important;margin:0 0 18px!important;order:40!important;overflow:hidden!important;padding:0!important;width:100%!important}body.woocommerce-checkout.aip-embedded-checkout .woocommerce-form-coupon-toggle{margin:0 0 10px!important;order:50!important}body.woocommerce-checkout.aip-embedded-checkout form.checkout_coupon{margin:0 0 18px!important;order:51!important}body.woocommerce-checkout.aip-embedded-checkout .place-order{margin-top:0!important;order:60!important}@media(max-width:600px){.aip-checkout-panel{max-width:none;width:100%}.aip-checkout-panel header{height:68px;padding:0 18px}.aip-checkout-panel iframe{height:calc(100% - 68px)}body.woocommerce-checkout.aip-embedded-checkout .row-parent{padding:18px 14px 34px!important}}
			.aip-checkout-reference,.aip-checkout-upload-preview{border-top:1px solid #eee9f2;margin-top:12px;padding-top:12px}.aip-checkout-reference{color:#5a5162;display:flex;flex-wrap:wrap;gap:6px}.aip-checkout-reference span{color:#756d7d}.aip-checkout-reference strong{color:#211b28}.aip-checkout-upload-preview>strong{color:#5a5162;display:block;font-size:10px!important;letter-spacing:.02em;margin-bottom:9px}.aip-checkout-upload-grid{display:grid;gap:8px;grid-template-columns:repeat(3,minmax(0,1fr));max-width:330px}.aip-checkout-upload-card{background:#faf8fd;border:1px solid #e2dce8;border-radius:9px;color:#4b4253!important;display:block;overflow:hidden;text-decoration:none!important}.aip-checkout-upload-card img,.aip-checkout-upload-file{aspect-ratio:4/3;background:#eee8fa;display:block;object-fit:cover;width:100%}.aip-checkout-upload-file{align-items:center;color:#6846e6;display:flex;font-size:20px;justify-content:center}.aip-checkout-upload-card span{display:block;font-size:8px!important;font-weight:700;overflow:hidden;padding:6px;text-overflow:ellipsis;white-space:nowrap}.aip-checkout-upload-card:hover{border-color:#886cf0;box-shadow:0 4px 14px rgba(104,70,230,.12)}
			';
			$css .= aip_reii_checkout_theme_css_v0551();
			wp_register_style( 'aip-fast-checkout', false, array(), self::VERSION );
		wp_enqueue_style( 'aip-fast-checkout' );
		wp_add_inline_style( 'aip-fast-checkout', $css );

		if ( is_checkout() ) {
			wp_enqueue_script( 'jquery' );
			$intake          = WC()->session ? WC()->session->get( 'aip_intake' ) : array();
			$files           = ! empty( $intake['files'] ) && is_array( $intake['files'] ) ? array_slice( $intake['files'], 0, 4 ) : array();
			$checkout_asin   = ! empty( $intake['reference'] ) ? self::extract_asin( $intake['reference'] ) : '';
			$reference_label = $checkout_asin ? 'ASIN: ' . $checkout_asin : '';
			wp_add_inline_script( 'jquery', 'window.aipCheckoutFiles=' . wp_json_encode( $files ) . ';', 'after' );
			wp_add_inline_script( 'jquery', 'window.aipCheckoutReference=' . wp_json_encode( $reference_label ) . ';', 'after' );
			wp_add_inline_script( 'jquery', 'window.aipCheckoutEmail=' . wp_json_encode( ! empty( $intake['email'] ) ? sanitize_email( $intake['email'] ) : '' ) . ';', 'after' );
			wp_add_inline_script(
				'jquery',
				"(function(){if(window.self!==window.top){document.documentElement.style.setProperty('margin-top','0px','important');var bar=document.getElementById('wpadminbar');if(bar)bar.style.setProperty('display','none','important');}var pending='';function applyEmail(attempt){var input=document.querySelector('input[name=\"contact_email\"],input#email');if(!input){if(attempt<40){window.setTimeout(function(){applyEmail(attempt+1);},150);}return;}if(pending&&input.value!==pending){var setter=Object.getOwnPropertyDescriptor(window.HTMLInputElement.prototype,'value').set;setter.call(input,pending);input.dispatchEvent(new Event('input',{bubbles:true}));input.dispatchEvent(new Event('change',{bubbles:true}));}}function addUploadPreview(){var files=Array.isArray(window.aipCheckoutFiles)?window.aipCheckoutFiles:[];if(!files.length)return;var hosts=document.querySelectorAll('.woocommerce-checkout-review-order-table .product-name,.wc-block-components-order-summary-item__description');hosts.forEach(function(host){if(host.querySelector('.aip-checkout-upload-preview'))return;var wrap=document.createElement('div');wrap.className='aip-checkout-upload-preview';var title=document.createElement('strong');title.textContent='Your uploaded files ('+files.length+')';wrap.appendChild(title);var grid=document.createElement('div');grid.className='aip-checkout-upload-grid';files.forEach(function(file,index){var link=document.createElement('a');link.className='aip-checkout-upload-card';link.href=String(file.url||'#');if(file.url){link.target='_blank';link.rel='noopener';}var isImage=String(file.type||'').indexOf('image/')===0||/\\.(jpe?g|png|webp)$/i.test(String(file.name||''));if(isImage&&file.url){var image=document.createElement('img');image.src=file.url;image.alt=String(file.name||('Upload '+(index+1)));link.appendChild(image);}else{var icon=document.createElement('div');icon.className='aip-checkout-upload-file';icon.textContent='↥';link.appendChild(icon);}var name=document.createElement('span');name.textContent=String(file.name||('Upload '+(index+1)));link.appendChild(name);grid.appendChild(link);});wrap.appendChild(grid);host.appendChild(wrap);});}function revealCheckoutError(){var error=document.querySelector('.wc-block-components-validation-error,.wc-block-components-notice-banner.is-error,[role=\"alert\"]');if(error){error.scrollIntoView({behavior:'smooth',block:'center'});}}document.addEventListener('click',function(event){if(event.target.closest&&event.target.closest('.wc-block-components-checkout-place-order-button')){window.setTimeout(revealCheckoutError,700);window.setTimeout(revealCheckoutError,1800);}});window.addEventListener('message',function(event){if(event.origin!==window.location.origin||!event.data||event.data.type!=='aipCheckoutEmail'){return;}pending=String(event.data.email||'').trim();if(!/^[^@\\s]+@[^@\\s]+\\.[^@\\s]+$/.test(pending)){pending='';return;}applyEmail(0);});var observer=new MutationObserver(addUploadPreview);observer.observe(document.documentElement,{childList:true,subtree:true});addUploadPreview();window.setTimeout(addUploadPreview,500);window.setTimeout(addUploadPreview,1500);if(window.parent!==window){window.parent.postMessage({type:'aipCheckoutReady'},window.location.origin);}})();",
				'after'
			);
			wp_add_inline_script(
				'jquery',
				"(function(){var email=String(window.aipCheckoutEmail||'').trim();if(!/^[^@\\s]+@[^@\\s]+\\.[^@\\s]+$/.test(email))return;function seed(attempt){var input=document.querySelector('input[name=\"contact_email\"],input#email,input[name=\"billing_email\"]');if(!input){if(attempt<50)window.setTimeout(function(){seed(attempt+1);},150);return;}if(input.value!==email){var setter=Object.getOwnPropertyDescriptor(window.HTMLInputElement.prototype,'value').set;setter.call(input,email);input.dispatchEvent(new Event('input',{bubbles:true}));input.dispatchEvent(new Event('change',{bubbles:true}));}}seed(0);})();",
				'after'
			);
			wp_add_inline_script(
				'jquery',
				"(function(){function addReference(){var reference=String(window.aipCheckoutReference||'').trim();if(!reference)return;var hosts=document.querySelectorAll('.woocommerce-checkout-review-order-table .cart_item .product-name,.wc-block-components-order-summary-item__description');hosts.forEach(function(host){if(host.querySelector('.aip-checkout-reference')||host.textContent.indexOf(reference)!==-1)return;var row=document.createElement('div');row.className='aip-checkout-reference';var label=document.createElement('span');label.textContent='Product';var value=document.createElement('strong');value.textContent=reference;row.appendChild(label);row.appendChild(value);host.appendChild(row);});}var observer=new MutationObserver(addReference);observer.observe(document.documentElement,{childList:true,subtree:true});addReference();window.setTimeout(addReference,500);window.setTimeout(addReference,1500);})();",
				'after'
			);
		}
	}

	public static function capture_intake( $contact_form, &$abort, $submission ) {
		if ( ! is_object( $contact_form ) || self::FORM_TITLE !== $contact_form->title() ) {
			return;
		}

		$product = self::service_product();
		if ( ! $product || ! $product->is_purchasable() ) {
			return;
		}

		if ( ! $submission && class_exists( 'WPCF7_Submission' ) ) {
			$submission = WPCF7_Submission::get_instance();
		}
		if ( ! $submission ) {
			return;
		}

		$data     = $submission->get_posted_data();
		$uploaded = $submission->uploaded_files();
		$email    = sanitize_email( isset( $data['your-email'] ) ? $data['your-email'] : '' );
		$method   = sanitize_text_field( isset( $data['source-method'] ) ? $data['source-method'] : '' );
		$reference = sanitize_text_field( isset( $data['product-reference'] ) ? $data['product-reference'] : '' );
		$notes     = sanitize_textarea_field( isset( $data['creative-notes'] ) ? $data['creative-notes'] : '' );
		$addon     = isset( $_POST['aip-addon'] ) ? sanitize_key( wp_unslash( $_POST['aip-addon'] ) ) : '';
		$addon     = array_key_exists( $addon, self::addon_catalog() ) ? $addon : '';
		$source_order = isset( $_POST['aip-source-order'] ) ? absint( $_POST['aip-source-order'] ) : 0;
		$file_names = array();
		$files_data = array();

		for ( $index = 1; $index <= 4; $index++ ) {
			$key = 'product-file-' . $index;
			if ( empty( $uploaded[ $key ] ) ) {
				continue;
			}
			$files = is_array( $uploaded[ $key ] ) ? $uploaded[ $key ] : array( $uploaded[ $key ] );
			foreach ( $files as $file ) {
				if ( $file ) {
					$persisted = self::persist_uploaded_file( $file );
					if ( $persisted ) {
						$files_data[] = $persisted;
						$file_names[] = $persisted['name'];
					} else {
						$file_names[] = sanitize_file_name( basename( $file ) );
					}
				}
			}
		}

		$intake = array(
			'email'        => $email,
			'method'       => $method,
			'reference'    => $reference,
			'notes'        => $notes,
			'addon'        => $addon,
			'source_order' => $source_order,
			'file_names'   => array_slice( array_values( array_unique( $file_names ) ), 0, 4 ),
			'files'        => array_slice( $files_data, 0, 4 ),
			'submitted_at' => current_time( DATE_ATOM ),
		);
		$is_upload = 'Upload product files' === $method;
		if ( ( $is_upload && empty( $intake['files'] ) ) || ( ! $is_upload && empty( $reference ) ) ) {
			$abort = true;
			if ( method_exists( $submission, 'set_response' ) ) {
				$submission->set_response( $is_upload ? 'Please upload at least one product file.' : 'Please paste an Amazon link or ASIN.' );
			}
			return;
		}

		if ( function_exists( 'wc_load_cart' ) && ! WC()->cart ) {
			wc_load_cart();
		}
		if ( ! WC()->cart || ! WC()->session ) {
			return;
		}

		foreach ( WC()->cart->get_cart() as $cart_key => $cart_item ) {
			if ( isset( $cart_item['product_id'] ) && $product->get_id() === (int) $cart_item['product_id'] ) {
				WC()->cart->remove_cart_item( $cart_key );
			}
		}

		$added = WC()->cart->add_to_cart(
			$product->get_id(),
			1,
			0,
			array(),
			array(
				'aip_intake' => $intake,
				'aip_key'    => wp_generate_uuid4(),
			)
		);

		if ( $added ) {
			WC()->session->set( 'aip_intake', $intake );
			/* Checkout Blocks reads contact data from the session customer, not
			 * woocommerce_checkout_get_value(). Keep the submitted email authoritative
			 * without saving it back to a logged-in WordPress user account. */
			if ( $email && WC()->customer ) {
				WC()->customer->set_billing_email( $email );
				if ( ! WC()->customer->get_billing_country( 'edit' ) ) {
					WC()->customer->set_billing_country( aip_reii_default_billing_country() );
				}
			}
			WC()->session->set_customer_session_cookie( true );
		}
	}

	public static function skip_intake_email( $skip_mail, $contact_form ) {
		if ( is_object( $contact_form ) && self::FORM_TITLE === $contact_form->title() ) {
			return true;
		}
		return $skip_mail;
	}

	public static function prefill_checkout_value( $value, $input ) {
		if ( ! WC()->session ) {
			return $value;
		}
		if ( 'billing_country' === $input && empty( $value ) ) {
			return aip_reii_default_billing_country();
		}
		if ( 'billing_email' !== $input ) {
			return $value;
		}
		$intake = WC()->session->get( 'aip_intake' );
		return ! empty( $intake['email'] ) ? $intake['email'] : $value;
	}

	private static function persist_uploaded_file( $file ) {
		if ( ! $file || ! is_readable( $file ) ) {
			return null;
		}
		$uploads = wp_upload_dir();
		if ( ! empty( $uploads['error'] ) ) {
			return null;
		}
		$relative_dir = 'aip-order-intake/' . gmdate( 'Y/m' );
		$target_dir   = trailingslashit( $uploads['basedir'] ) . $relative_dir;
		if ( ! wp_mkdir_p( $target_dir ) ) {
			return null;
		}
		$source_name = sanitize_file_name( basename( $file ) );
		$file_name   = wp_unique_filename( $target_dir, substr( wp_generate_uuid4(), 0, 8 ) . '-' . $source_name );
		$target      = trailingslashit( $target_dir ) . $file_name;
		if ( ! copy( $file, $target ) ) {
			return null;
		}
		$file_type = wp_check_filetype( $file_name );
		return array(
			'name' => $source_name,
			'url'  => trailingslashit( $uploads['baseurl'] ) . $relative_dir . '/' . rawurlencode( $file_name ),
			'type' => isset( $file_type['type'] ) ? $file_type['type'] : '',
			'size' => (int) filesize( $target ),
		);
	}

	public static function cart_item_details( $details, $cart_item ) {
		if ( empty( $cart_item['aip_intake'] ) ) {
			return $details;
		}
		return array_merge( $details, self::get_display_details( $cart_item['aip_intake'] ) );
	}

	private static function addon_catalog() {
		return array(
			'amazon-storefront'  => array( 'label' => 'Post to REii’s Amazon Storefront', 'price' => 10 ),
			'extra-environment'  => array( 'label' => 'Extra environment', 'price' => 15 ),
			'another-version'    => array( 'label' => 'Another version', 'price' => 15 ),
			'20-second-story'    => array( 'label' => '20-second story', 'price' => 10 ),
			'alternate-lighting' => array( 'label' => 'Alternate lighting', 'price' => 10 ),
			'priority-delivery'  => array( 'label' => 'Priority delivery', 'price' => 10 ),
		);
	}

	public static function apply_addon_price( $cart ) {
		if ( ! $cart || ( is_admin() && ! wp_doing_ajax() ) ) {
			return;
		}
		$catalog = self::addon_catalog();
		foreach ( $cart->get_cart() as $cart_item ) {
			if ( empty( $cart_item['aip_intake'] ) || empty( $cart_item['data'] ) ) {
				continue;
			}
			$addon = sanitize_key( $cart_item['aip_intake']['addon'] ?? '' );
			$extra = isset( $catalog[ $addon ] ) ? (float) $catalog[ $addon ]['price'] : 0;
			$cart_item['data']->set_price( (float) self::BASE_PRICE + $extra );
		}
	}

	public static function extract_asin( $ref ) {
		if ( empty( $ref ) ) {
			return '';
		}
		if ( preg_match( '/(?:^|[^A-Z0-9])(B0[A-Z0-9]{8})(?=$|[^A-Z0-9])/i', (string) $ref, $matches ) ) {
			return strtoupper( $matches[1] );
		}
		return '';
	}

	public static function format_reference_for_display( $ref ) {
		if ( empty( $ref ) ) {
			return '';
		}
		$ref  = trim( $ref );
		$asin = self::extract_asin( $ref );
		if ( $asin ) {
			return 'ASIN: ' . $asin;
		}
		if ( function_exists( 'mb_strimwidth' ) ) {
			return mb_strimwidth( $ref, 0, 45, '…' );
		}
		return ( strlen( $ref ) > 45 ) ? substr( $ref, 0, 42 ) . '...' : $ref;
	}

	public static function filter_order_item_display_meta( $formatted_meta, $item ) {
		foreach ( $formatted_meta as $key => $meta ) {
			if ( isset( $meta->key ) && 'Amazon link / ASIN' === $meta->key ) {
				$asin = self::extract_asin( $meta->value );
				$formatted_meta[ $key ]->display_value = $asin ? $asin : self::format_reference_for_display( $meta->value );
			}
		}
		return $formatted_meta;
	}

	public static function custom_completed_email_subject( $subject, $order ) {
		$order_num = ( $order && is_callable( array( $order, 'get_order_number' ) ) ) ? $order->get_order_number() : '';
		return 'Your REii content is ready!' . ( $order_num ? ' (Order #' . $order_num . ')' : '' );
	}

	public static function custom_processing_email_subject( $subject, $order ) {
		$order_num = ( $order && is_callable( array( $order, 'get_order_number' ) ) ) ? $order->get_order_number() : '';
		return 'Your REii video order is confirmed' . ( $order_num ? ' (Order #' . $order_num . ')' : '' );
	}

	public static function custom_processing_email_heading( $heading, $order ) {
		return 'Your product is ready to be reimagined.';
	}

	public static function email_order_confirmation_message( $order, $sent_to_admin, $plain_text, $email ) {
		if ( $sent_to_admin || ! $order instanceof WC_Order || ! $email instanceof WC_Email || 'customer_processing_order' !== $email->id ) {
			return;
		}
		if ( $plain_text ) {
			echo "\nWHAT HAPPENS NEXT\nREii will creatively direct, render, and quality-check your AI influencer UGC video. We’ll email your private content-library link when it is ready.\n\n";
			return;
		}
		?>
		<div style="margin:28px 0 20px;padding:22px;border:1px solid #ded7e5;border-radius:10px;">
			<h2 style="margin:0 0 8px;">What happens next</h2>
			<p style="margin:0;">REii will creatively direct, render, and quality-check your AI influencer UGC video. We’ll email your private content-library link when it is ready.</p>
		</div>
		<?php
	}

	private static $current_rendering_email = null;

	public static function track_email_before( $order, $sent_to_admin = false, $plain_text = false, $email = null ) {
		if ( $email instanceof WC_Email ) {
			self::$current_rendering_email = $email->id;
		}
	}

	public static function track_email_after( $email = null ) {
		self::$current_rendering_email = null;
	}

	private static function is_completed_email_rendering( $order = null ) {
		if ( 'customer_completed_order' === self::$current_rendering_email ) {
			return true;
		}
		if ( $order instanceof WC_Order && 'completed' === $order->get_status() ) {
			$delivery = $order->get_meta( '_aip_deliverables' );
			if ( is_array( $delivery ) && ! empty( $delivery['delivered_at'] ) && ( ! empty( $delivery['images'] ) || ! empty( $delivery['videos'] ) ) ) {
				return true;
			}
		}
		return false;
	}

	public static function filter_completed_email_order_item_totals( $total_rows, $order, $tax_display = '' ) {
		if ( self::is_completed_email_rendering( $order ) ) {
			return array();
		}
		return $total_rows;
	}

	public static function filter_completed_email_line_subtotal( $subtotal, $item, $order = null ) {
		if ( self::is_completed_email_rendering( $order ) ) {
			return '';
		}
		return $subtotal;
	}

	public static function filter_completed_email_items_table( $table_html, $order ) {
		if ( self::is_completed_email_rendering( $order ) ) {
			$table_html = preg_replace( '/<td class="td"[^>]*>\s*<\/td>/i', '', $table_html );
		}
		return $table_html;
	}

	public static function filter_completed_email_templates( $located, $template_name, $args, $template_path, $default_path ) {
		$email = isset( $args['email'] ) ? $args['email'] : null;
		$email_id = ( $email instanceof WC_Email ) ? $email->id : ( is_string( self::$current_rendering_email ) ? self::$current_rendering_email : '' );
		if ( 'customer_completed_order' === $email_id && ( 'emails/email-addresses.php' === $template_name || 'emails/plain/email-addresses.php' === $template_name ) ) {
			$empty = __DIR__ . '/includes/empty-template.php';
			if ( file_exists( $empty ) ) {
				return $empty;
			}
		}
		return $located;
	}

	public static function filter_completed_email_styles( $css, $email = null ) {
		$email_id = ( $email instanceof WC_Email ) ? $email->id : ( is_string( self::$current_rendering_email ) ? self::$current_rendering_email : '' );
		if ( 'customer_completed_order' === $email_id ) {
			$css .= '
			table.td th:last-child,
			table.td td:last-child {
				display: none !important;
				width: 0 !important;
				max-width: 0 !important;
				padding: 0 !important;
				font-size: 0 !important;
				line-height: 0 !important;
				border: 0 !important;
				visibility: hidden !important;
			}
			#addresses {
				display: none !important;
			}
			';
		}
		return $css;
	}

	public static function custom_completed_email_heading( $heading, $order ) {
		return 'Your REii content is ready!';
	}

	public static function customize_email_gettext( $translated_text, $text, $domain ) {
		if ( 'woocommerce' === $domain ) {
			if ( 'Thank you. Your order has been received.' === $text ) {
				return 'Your order is confirmed. REii is ready to create your video.';
			}
			if ( 'Order received' === $text ) {
				return 'Order confirmed';
			}
			if ( 'Good things are heading your way!' === $text || 'Your order is complete' === $text ) {
				return 'Your REii content is ready!';
			}
			if ( 'We have finished processing your order.' === $text ) {
				return 'Your REii AI influencer UGC video is complete and ready in your private content library.';
			}
			if ( "Here's a reminder of what you've ordered:" === $text ) {
				return 'Here is a summary of your completed order:';
			}
			if ( 'Your order from %s is on its way!' === $text ) {
				return 'Your REii content from %s is ready!';
			}
		}
		return $translated_text;
	}

	public static function add_item_thumbnail_to_confirmation( $item_name, $item, $is_visible ) {
		$thumbs = array();
		$order_id = is_callable( array( $item, 'get_order_id' ) ) ? absint( $item->get_order_id() ) : 0;
		$order  = $order_id && function_exists( 'wc_get_order' ) ? wc_get_order( $order_id ) : false;
		if ( ! $order && is_callable( array( $item, 'get_order' ) ) ) {
			$order = $item->get_order();
		}
		$product = is_callable( array( $item, 'get_product' ) ) ? $item->get_product() : false;
		if ( $product && 'on-model-content-order' === $product->get_sku() ) {
			$item_name = 'REii AI-Generated UGC Video';
		}

		if ( $order ) {
			$uploaded = $order->get_meta( '_aip_uploaded_files' );
			if ( is_array( $uploaded ) ) {
				foreach ( $uploaded as $file ) {
					$url = is_array( $file ) ? ( $file['url'] ?? '' ) : ( is_string( $file ) ? $file : '' );
					if ( $url && ( strpos( $url, 'data:image' ) === 0 || preg_match( '/\.(jpg|jpeg|png|webp|gif|svg)$/i', $url ) ) ) {
						$thumbs[] = $url;
					}
				}
			}
		}

		$ref  = $item->get_meta( '_aip_raw_reference' ) ?: $item->get_meta( 'Amazon link / ASIN' );
		$asin = self::extract_asin( $ref );
		if ( $asin ) {
			$amazon_thumb = "https://images-na.ssl-images-amazon.com/images/P/{$asin}.01.MAIN._AC_SY300_.jpg";
			if ( empty( $thumbs ) ) {
				$thumbs[] = $amazon_thumb;
			}
		}

		if ( ! empty( $thumbs ) ) {
			$thumb_html = '<div class="aip-confirmation-uploads" style="margin:12px 0 8px;"><strong style="display:block; font-size:11px; margin-bottom:7px;">Your uploaded product images</strong><div class="aip-confirmation-thumbs" style="display:flex; flex-wrap:wrap; gap:8px; align-items:center;">';
			foreach ( array_slice( $thumbs, 0, 4 ) as $t_url ) {
				$thumb_html .= '<a href="' . esc_url( $t_url ) . '" target="_blank" rel="noopener" aria-label="Open uploaded product image"><img src="' . esc_url( $t_url ) . '" alt="Uploaded product image" style="width:60px; height:72px; object-fit:cover; border-radius:8px; border:1px solid #e2dafb; box-shadow:0 2px 8px rgba(0,0,0,0.06); background:#fff;" onerror="this.closest(\'a\').style.display=\'none\'"></a>';
			}
			$thumb_html .= '</div></div>';
			return $item_name . $thumb_html;
		}

		return $item_name;
	}

	public static function get_display_details( $intake ) {
		$details = array();
		if ( ! empty( $intake['method'] ) ) {
			$details[] = array( 'key' => 'Product source', 'value' => wc_clean( $intake['method'] ) );
		}
		if ( ! empty( $intake['reference'] ) ) {
			$display_ref = self::format_reference_for_display( $intake['reference'] );
			$details[]   = array( 'key' => 'Amazon link / ASIN', 'value' => wc_clean( $display_ref ) );
		}
		if ( ! empty( $intake['file_names'] ) && is_array( $intake['file_names'] ) ) {
			$details[] = array( 'key' => 'Uploaded files', 'value' => wc_clean( implode( ', ', $intake['file_names'] ) ) );
		} elseif ( ! empty( $intake['file_name'] ) ) {
			$details[] = array( 'key' => 'Uploaded file', 'value' => wc_clean( $intake['file_name'] ) );
		}
		$catalog = self::addon_catalog();
		$addon   = sanitize_key( $intake['addon'] ?? '' );
		if ( isset( $catalog[ $addon ] ) ) {
			$details[] = array( 'key' => 'Video add-on', 'value' => wc_clean( $catalog[ $addon ]['label'] . ' (+$' . $catalog[ $addon ]['price'] . ')' ) );
		}
		return $details;
	}

	public static function copy_intake_to_order_item( $item, $cart_item_key, $values, $order ) {
		if ( empty( $values['aip_intake'] ) ) {
			return;
		}
		$intake = $values['aip_intake'];
		if ( ! empty( $intake['method'] ) ) {
			$item->add_meta_data( 'Product source', $intake['method'], true );
		}
		if ( ! empty( $intake['reference'] ) ) {
			$display_ref = self::format_reference_for_display( $intake['reference'] );
			$item->add_meta_data( 'Amazon link / ASIN', $display_ref, true );
			$item->add_meta_data( '_aip_raw_reference', $intake['reference'], true );
		}
		if ( ! empty( $intake['notes'] ) ) {
			$item->add_meta_data( 'Customer instructions', $intake['notes'], true );
		}
		$catalog = self::addon_catalog();
		$addon   = sanitize_key( $intake['addon'] ?? '' );
		if ( isset( $catalog[ $addon ] ) ) {
			$item->add_meta_data( 'Video add-on', $catalog[ $addon ]['label'] . ' (+$' . $catalog[ $addon ]['price'] . ')', true );
		}
		if ( ! empty( $intake['source_order'] ) ) {
			$item->add_meta_data( 'Source order', '#' . absint( $intake['source_order'] ), true );
		}
		if ( ! empty( $intake['file_names'] ) && is_array( $intake['file_names'] ) ) {
			$item->add_meta_data( 'Uploaded files', implode( ', ', array_map( 'wc_clean', $intake['file_names'] ) ), true );
		} elseif ( ! empty( $intake['file_name'] ) ) {
			$item->add_meta_data( 'Uploaded file', $intake['file_name'], true );
		}
	}

	public static function apply_intake_to_order( $order, $data ) {
		if ( ! WC()->session ) {
			return;
		}
		$intake = WC()->session->get( 'aip_intake' );
		if ( empty( $intake ) ) {
			return;
		}
		$email = ! empty( $intake['email'] ) ? sanitize_email( $intake['email'] ) : '';
		if ( $email && is_email( $email ) ) {
			// Never let a billing email remembered from a previous WooCommerce
			// session replace the address submitted for this REii order.
			$order->set_billing_email( $email );
		}
		$order->update_meta_data( '_aip_intake_email', $email );
		$order->update_meta_data( '_aip_intake_submitted_at', isset( $intake['submitted_at'] ) ? $intake['submitted_at'] : current_time( DATE_ATOM ) );
		if ( ! empty( $intake['files'] ) && is_array( $intake['files'] ) ) {
			$order->update_meta_data( '_aip_uploaded_files', array_slice( $intake['files'], 0, 4 ) );
		}
	}

	public static function register_order_statuses() {
		$statuses = array(
			'wc-content-queued'   => 'Queued for production',
			'wc-content-creating' => 'Creating your content',
			'wc-content-review'   => 'Ready for review',
		);

		foreach ( $statuses as $slug => $label ) {
			register_post_status(
				$slug,
				array(
					'label'                     => $label,
					'public'                    => true,
					'exclude_from_search'       => false,
					'show_in_admin_all_list'    => true,
					'show_in_admin_status_list' => true,
					/* translators: %s is the number of orders with this status. */
					'label_count'               => _n_noop( "$label <span class=\"count\">(%s)</span>", "$label <span class=\"count\">(%s)</span>" ),
				)
			);
		}
	}

	public static function order_status_labels( $statuses ) {
		$renamed = array();
		foreach ( $statuses as $key => $label ) {
			$renamed[ $key ] = $label;
			if ( 'wc-pending' === $key ) {
				$renamed[ $key ] = 'Payment pending';
			} elseif ( 'wc-processing' === $key ) {
				$renamed[ $key ] = 'Order received';
				$renamed['wc-content-queued'] = 'Queued for production';
				$renamed['wc-content-creating'] = 'Creating your content';
				$renamed['wc-content-review'] = 'Ready for review';
			} elseif ( 'wc-completed' === $key ) {
				$renamed[ $key ] = 'Files ready';
			}
		}
		return $renamed;
	}

	public static function account_menu( $items ) {
		$menu = array();
		if ( isset( $items['dashboard'] ) ) {
			$menu['dashboard'] = 'Overview';
		}
		if ( isset( $items['orders'] ) ) {
			$menu['orders'] = 'Projects';
		}
		if ( isset( $items['downloads'] ) ) {
			$menu['downloads'] = 'Downloads';
		}
		if ( isset( $items['payment-methods'] ) ) {
			$menu['payment-methods'] = 'Payment methods';
		}
		if ( isset( $items['edit-account'] ) ) {
			$menu['edit-account'] = 'Account details';
		}
		if ( isset( $items['customer-logout'] ) ) {
			$menu['customer-logout'] = 'Log out';
		}
		return $menu;
	}

	public static function replace_account_dashboard() {
		if ( ! function_exists( 'is_account_page' ) || ! is_account_page() ) {
			return;
		}
		remove_action( 'woocommerce_account_dashboard', 'woocommerce_account_dashboard' );
		add_action( 'woocommerce_account_dashboard', array( __CLASS__, 'dashboard' ) );
	}

	public static function dashboard() {
		$customer_id = get_current_user_id();
		$orders      = wc_get_orders(
			array(
				'customer_id' => $customer_id,
				'limit'       => 3,
				'orderby'     => 'date',
				'order'       => 'DESC',
			)
		);
		?>
		<section class="aip-project-dashboard">
			<div class="aip-project-welcome">
				<p class="aip-project-kicker">YOUR REii PROJECTS</p>
				<h2>Your ideas, reimagined.</h2>
				<p>Track each AI influencer UGC order, follow its progress, and download your finished REii content.</p>
				<a class="aip-project-button" href="<?php echo esc_url( home_url( '/style-by-reii/#submit-project' ) ); ?>">Reimagine another product &rarr;</a>
			</div>

			<div class="aip-project-summary">
				<div><strong><?php echo esc_html( count( $orders ) ); ?></strong><span>Recent projects</span></div>
				<div><strong><?php echo esc_html( count( wc_get_customer_available_downloads( $customer_id ) ) ); ?></strong><span>Files available</span></div>
			</div>

			<div class="aip-project-list">
				<div class="aip-project-list-heading"><h3>Recent projects</h3><a href="<?php echo esc_url( wc_get_account_endpoint_url( 'orders' ) ); ?>">View all</a></div>
				<?php if ( empty( $orders ) ) : ?>
					<div class="aip-project-empty"><strong>No projects yet.</strong><p>Start with an Amazon link, ASIN, or a few clear product photos.</p></div>
				<?php else : ?>
					<?php foreach ( $orders as $order ) : ?>
						<a class="aip-project-row" href="<?php echo esc_url( $order->get_view_order_url() ); ?>">
							<span><strong>Project #<?php echo esc_html( $order->get_order_number() ); ?></strong><small><?php echo esc_html( wc_format_datetime( $order->get_date_created() ) ); ?></small></span>
							<em><?php echo esc_html( wc_get_order_status_name( $order->get_status() ) ); ?></em>
						</a>
					<?php endforeach; ?>
				<?php endif; ?>
			</div>
		</section>
		<?php
	}

	public static function account_styles() {
		if ( ! function_exists( 'is_account_page' ) || ! is_account_page() ) {
			return;
		}
		$css = '
		.woocommerce-account #masthead,.woocommerce-account .footer-content-block,.woocommerce-account #colophon{display:none!important}.woocommerce-account .woocommerce{max-width:1120px;margin:0 auto;padding:42px 24px 80px}.woocommerce-MyAccount-navigation{background:#19151f;border-radius:16px;padding:16px!important}.woocommerce-MyAccount-navigation ul{margin:0!important}.woocommerce-account .woocommerce-MyAccount-navigation ul li a{border-radius:9px;color:#d8d1de!important;display:block;padding:11px 13px!important;text-decoration:none!important}.woocommerce-account .woocommerce-MyAccount-navigation ul li a *{color:#d8d1de!important}.woocommerce-account .woocommerce-MyAccount-navigation ul li.is-active a,.woocommerce-account .woocommerce-MyAccount-navigation ul li a:hover{background:#6846e6!important;color:#fff!important}.woocommerce-account .woocommerce-MyAccount-navigation ul li.is-active a *,.woocommerce-account .woocommerce-MyAccount-navigation ul li a:hover *{color:#fff!important}.woocommerce-MyAccount-content{padding-left:42px}.aip-project-dashboard{color:#211d25}.aip-project-welcome{background:#f3efff;border:1px solid #e4dcff;border-radius:18px;padding:32px}.aip-project-kicker{color:#6846e6;font-size:10px;font-weight:800;letter-spacing:1.7px;margin:0 0 8px}.aip-project-welcome h2{font-size:38px;letter-spacing:-1.6px;margin:0}.aip-project-welcome>p:not(.aip-project-kicker){color:#6f6677;line-height:1.65;max-width:580px}.aip-project-button{background:#6846e6;border-radius:9px;color:#fff!important;display:inline-block;font-size:13px;font-weight:750;margin-top:8px;padding:13px 18px;text-decoration:none!important}.aip-project-summary{display:grid;gap:14px;grid-template-columns:1fr 1fr;margin:18px 0}.aip-project-summary>div{background:#fff;border:1px solid #e8e3ec;border-radius:14px;padding:22px}.aip-project-summary strong,.aip-project-summary span{display:block}.aip-project-summary strong{font-size:30px}.aip-project-summary span{color:#766e7e;font-size:11px}.aip-project-list{background:#fff;border:1px solid #e8e3ec;border-radius:16px;padding:24px}.aip-project-list-heading{align-items:center;display:flex;justify-content:space-between}.aip-project-list-heading h3{margin:0}.aip-project-list-heading a{color:#6846e6;font-size:12px;font-weight:700}.aip-project-empty{color:#756c7d;padding:34px 0 16px;text-align:center}.aip-project-empty strong{color:#28222e}.aip-project-empty p{font-size:12px}.aip-project-row{align-items:center;border-top:1px solid #eee9f1;color:#28222e!important;display:flex;justify-content:space-between;padding:17px 0;text-decoration:none!important}.aip-project-row:first-of-type{margin-top:14px}.aip-project-row strong,.aip-project-row small{display:block}.aip-project-row small{color:#81798a;font-size:10px;margin-top:4px}.aip-project-row em{background:#eee9ff;border-radius:20px;color:#6040d4;font-size:10px;font-style:normal;font-weight:750;padding:7px 10px}.aip-delivery-preview{background:#fff;border:1px solid #e8e3ec;border-radius:16px;margin-top:24px;padding:24px}.aip-delivery-preview h2{margin:0 0 6px}.aip-delivery-preview>p{color:#766e7e;font-size:12px}.aip-delivery-grid{display:grid;gap:16px;grid-template-columns:repeat(2,minmax(0,1fr));margin-top:18px}.aip-delivery-grid img,.aip-delivery-grid video{aspect-ratio:9/16;background:#17131d;border-radius:12px;display:block;height:auto;object-fit:cover;width:100%}@media(max-width:780px){.woocommerce-MyAccount-content{padding-left:0;padding-top:24px}.aip-project-welcome h2{font-size:30px}.aip-project-summary,.aip-delivery-grid{grid-template-columns:1fr}}
		';
		if ( ! is_wc_endpoint_url() ) {
			$css .= '.woocommerce-account .woocommerce-MyAccount-content>p{display:none!important}';
		}
		wp_register_style( 'aip-on-model-commerce', false, array(), self::VERSION );
		wp_enqueue_style( 'aip-on-model-commerce' );
		wp_add_inline_style( 'aip-on-model-commerce', $css );
	}

	public static function add_admin_order_meta_boxes( $post_type, $post_or_order = null ) {
		$screen  = function_exists( 'wc_get_page_screen_id' ) ? wc_get_page_screen_id( 'shop-order' ) : 'shop_order';
		$screens = array_unique( array_filter( array( $screen, 'shop_order', 'woocommerce_page_wc-orders' ) ) );

		foreach ( $screens as $s ) {
			add_meta_box(
				'aip_on_model_order_production',
				'✨ On-Model Content Production & Deliverables',
				array( __CLASS__, 'render_admin_order_meta_box' ),
				$s,
				'normal',
				'high'
			);

			add_meta_box(
				'aip_on_model_order_actions',
				'⚡ On-Model Production Control',
				array( __CLASS__, 'render_admin_order_actions_meta_box' ),
				$s,
				'side',
				'high'
			);
		}
	}

	private static function get_admin_order( $post_or_order_object = null ) {
		if ( $post_or_order_object instanceof WC_Order ) {
			return $post_or_order_object;
		}
		if ( is_object( $post_or_order_object ) && isset( $post_or_order_object->ID ) ) {
			return wc_get_order( $post_or_order_object->ID );
		}
		$order_id = 0;
		if ( isset( $_GET['id'] ) ) {
			$order_id = absint( $_GET['id'] );
		} elseif ( isset( $_GET['post'] ) ) {
			$order_id = absint( $_GET['post'] );
		}
		return $order_id ? wc_get_order( $order_id ) : false;
	}

	public static function render_admin_order_meta_box( $post_or_order_object = null ) {
		$order = self::get_admin_order( $post_or_order_object );
		if ( ! $order ) {
			echo '<p>Order details unavailable.</p>';
			return;
		}

		$order_id      = $order->get_id();
		$order_number  = $order->get_order_number();
		$status        = $order->get_status();
		$status_label  = wc_get_order_status_name( $status );
		$intake_email  = $order->get_meta( '_aip_intake_email' ) ?: $order->get_billing_email();
		$submitted_at  = $order->get_meta( '_aip_intake_submitted_at' );

		$reference = '';
		$notes     = '';
		$method    = '';
		foreach ( $order->get_items() as $item ) {
			foreach ( $item->get_formatted_meta_data( '' ) as $entry ) {
				$key   = wp_strip_all_tags( $entry->display_key );
				$value = wp_strip_all_tags( $entry->display_value );
				if ( 'Amazon link / ASIN' === $key ) {
					$reference = $value;
				} elseif ( 'Customer instructions' === $key ) {
					$notes = $value;
				} elseif ( 'Product source' === $key ) {
					$method = $value;
				}
			}
		}

		$raw_ref = '';
		foreach ( $order->get_items() as $item ) {
			$raw = $item->get_meta( '_aip_raw_reference' );
			if ( $raw ) {
				$raw_ref = $raw;
				break;
			}
		}
		$asin = self::extract_asin( $raw_ref ?: $reference );

		$uploaded_files = $order->get_meta( '_aip_uploaded_files' );
		$uploaded_files = is_array( $uploaded_files ) ? $uploaded_files : array();

		$deliverables = $order->get_meta( '_aip_deliverables' );
		$deliverables = is_array( $deliverables ) ? $deliverables : array();
		$images       = array_values( array_filter( (array) ( $deliverables['images'] ?? array() ) ) );
		$videos       = array_values( array_filter( (array) ( $deliverables['videos'] ?? array() ) ) );
		$generated_at = $deliverables['generated_at'] ?? '';
		$delivered_at = $deliverables['delivered_at'] ?? '';
		$download_stats = $order->get_meta( '_aip_download_stats' );
		$download_stats = is_array( $download_stats ) ? $download_stats : array();

		$token = $order->get_meta( '_aip_delivery_token' );
		$delivery_url = $token ? add_query_arg( array( 'aip_order_delivery' => $order_id, 'aip_token' => $token ), home_url( '/' ) ) : '';

		?>
		<div class="aip-admin-order-wrap">
			<div class="aip-admin-header-bar">
				<div>
					<small class="aip-admin-tag">ON-MODEL CONTENT STUDIO</small>
					<h3 class="aip-admin-title">Production Dashboard — Order #<?php echo esc_html( $order_number ); ?></h3>
				</div>
				<div class="aip-admin-status-wrap">
					<span class="aip-admin-badge aip-badge-<?php echo esc_attr( $status ); ?>"><?php echo esc_html( $status_label ); ?></span>
				</div>
			</div>

			<div class="aip-admin-grid">
				<div class="aip-admin-card">
					<div class="aip-card-header">
						<h4>📦 Product Intake & Reference Assets</h4>
					</div>
					<div class="aip-card-body">
						<?php if ( $asin ) : ?>
							<div class="aip-asin-preview">
								<img src="https://images-na.ssl-images-amazon.com/images/P/<?php echo esc_attr( $asin ); ?>.01.MAIN._AC_SY300_.jpg" alt="Amazon Product" class="aip-asin-img" onerror="this.style.display='none'">
								<div class="aip-asin-info">
									<strong>Amazon ASIN: <code><?php echo esc_html( $asin ); ?></code></strong>
									<br><a href="https://www.amazon.com/dp/<?php echo esc_attr( $asin ); ?>" target="_blank" rel="noopener" class="button button-small" style="margin-top:6px;">Open Amazon Listing ↗</a>
								</div>
							</div>
						<?php elseif ( $reference ) : ?>
							<p><strong>Product Reference:</strong> <?php echo esc_html( $reference ); ?></p>
						<?php endif; ?>

						<?php if ( $method ) : ?>
							<p><strong>Source Method:</strong> <?php echo esc_html( $method ); ?></p>
						<?php endif; ?>

						<?php if ( ! empty( $uploaded_files ) ) : ?>
							<div class="aip-subhead">Customer Uploaded Reference Photos (<?php echo count( $uploaded_files ); ?>)</div>
							<div class="aip-thumbs-grid">
								<?php foreach ( $uploaded_files as $file ) :
									$url  = is_array( $file ) ? ( $file['url'] ?? '' ) : ( is_string( $file ) ? $file : '' );
									$name = is_array( $file ) ? ( $file['name'] ?? 'File' ) : 'File';
									$is_img = preg_match( '/\.(jpg|jpeg|png|webp|gif)$/i', $url ) || strpos( $url, 'data:image' ) === 0;
								?>
									<a href="<?php echo esc_url( $url ?: '#' ); ?>" target="_blank" rel="noopener" class="aip-thumb-card">
										<?php if ( $is_img && $url ) : ?>
											<img src="<?php echo esc_url( $url ); ?>" alt="<?php echo esc_attr( $name ); ?>">
										<?php else : ?>
											<div class="aip-thumb-icon">📄</div>
										<?php endif; ?>
										<span><?php echo esc_html( $name ); ?></span>
									</a>
								<?php endforeach; ?>
							</div>
						<?php endif; ?>

						<?php if ( $notes ) : ?>
							<div class="aip-notes-box">
								<strong>Customer Creative Instructions:</strong>
								<p><?php echo nl2br( esc_html( $notes ) ); ?></p>
							</div>
						<?php endif; ?>
					</div>
				</div>

				<div class="aip-admin-card">
					<div class="aip-card-header">
						<h4>🎬 Generated Content Deliverables</h4>
						<?php if ( $generated_at ) : ?>
							<span class="aip-time-note">Generated <?php echo esc_html( date( 'M j, g:i a', strtotime( $generated_at ) ) ); ?></span>
						<?php endif; ?>
					</div>
					<div class="aip-card-body">
						<?php if ( empty( $images ) && empty( $videos ) ) : ?>
							<div class="aip-empty-deliverables">
								<span class="aip-empty-icon">⏳</span>
								<strong>No deliverables generated yet</strong>
								<p>Trigger the Order Studio pipeline to generate try-on images and videos.</p>
							</div>
						<?php else : ?>
							<div class="aip-deliverables-grid">
								<?php foreach ( $images as $idx => $url ) : ?>
									<div class="aip-media-card">
										<a href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener">
											<img src="<?php echo esc_url( $url ); ?>" alt="On-model photo <?php echo $idx + 1; ?>">
										</a>
										<div class="aip-media-footer">
											<span>Photo #<?php echo $idx + 1; ?></span>
											<a href="<?php echo esc_url( $url ); ?>" download class="button button-small">Download ↓</a>
										</div>
									</div>
								<?php endforeach; ?>
								<?php foreach ( $videos as $idx => $url ) : ?>
									<div class="aip-media-card">
										<video controls playsinline preload="metadata" src="<?php echo esc_url( $url ); ?>"></video>
										<div class="aip-media-footer">
											<span>Video #<?php echo $idx + 1; ?></span>
											<a href="<?php echo esc_url( $url ); ?>" download class="button button-small">Download ↓</a>
										</div>
									</div>
								<?php endforeach; ?>
							</div>

							<?php if ( ! empty( $download_stats ) ) : ?>
								<div class="aip-stats-box">
									<strong>📊 Customer Downloads Tracker:</strong>
									<ul>
										<?php foreach ( $download_stats as $file_key => $stat ) : ?>
											<li>
												<code><?php echo esc_html( $file_key ); ?></code> —
												Downloaded <strong><?php echo esc_html( $stat['count'] ?? 0 ); ?> time(s)</strong>
												<?php if ( ! empty( $stat['last_downloaded_at'] ) ) : ?>
													<small>(Last: <?php echo esc_html( date( 'M j, g:i a', strtotime( $stat['last_downloaded_at'] ) ) ); ?>)</small>
												<?php endif; ?>
											</li>
										<?php endforeach; ?>
									</ul>
								</div>
							<?php endif; ?>
						<?php endif; ?>

						<div class="aip-action-buttons">
							<?php if ( ! empty( $images ) || ! empty( $videos ) ) : ?>
								<button type="button" class="button button-primary button-large aip-btn-deliver-order" data-order-id="<?php echo esc_attr( $order_id ); ?>">
									🚀 Deliver Files to Client Now
								</button>
							<?php endif; ?>

							<?php if ( $delivery_url ) : ?>
								<button type="button" class="button button-secondary aip-btn-copy-delivery" data-url="<?php echo esc_url( $delivery_url ); ?>">
									📋 Copy Client Access Link
								</button>
								<a href="<?php echo esc_url( $delivery_url ); ?>" target="_blank" rel="noopener" class="button button-secondary">
									👁️ Preview Client Delivery Page
								</a>
							<?php endif; ?>
						</div>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	public static function render_admin_order_actions_meta_box( $post_or_order_object = null ) {
		$order = self::get_admin_order( $post_or_order_object );
		if ( ! $order ) {
			return;
		}

		$order_id     = $order->get_id();
		$status       = $order->get_status();
		$intake_email = $order->get_meta( '_aip_intake_email' ) ?: $order->get_billing_email();
		$token        = $order->get_meta( '_aip_delivery_token' );
		$delivery_url = $token ? add_query_arg( array( 'aip_order_delivery' => $order_id, 'aip_token' => $token ), home_url( '/' ) ) : '';

		?>
		<div class="aip-sidebar-actions-wrap">
			<p><strong>Quick Production Status Switcher:</strong></p>
			<div class="aip-sidebar-status-group">
				<button type="button" class="button widefat <?php echo 'content-queued' === $status ? 'button-primary' : ''; ?>" data-aip-status="content-queued" data-order-id="<?php echo esc_attr( $order_id ); ?>">1. Queued for production</button>
				<button type="button" class="button widefat <?php echo 'content-creating' === $status ? 'button-primary' : ''; ?>" data-aip-status="content-creating" data-order-id="<?php echo esc_attr( $order_id ); ?>">2. Creating content</button>
				<button type="button" class="button widefat <?php echo 'content-review' === $status ? 'button-primary' : ''; ?>" data-aip-status="content-review" data-order-id="<?php echo esc_attr( $order_id ); ?>">3. Ready for staff review</button>
				<button type="button" class="button widefat <?php echo 'completed' === $status ? 'button-primary' : ''; ?>" data-aip-status="completed" data-order-id="<?php echo esc_attr( $order_id ); ?>">4. Deliver & Mark Completed</button>
			</div>

			<hr style="margin:16px 0;">

			<p><strong>Client Contact Email:</strong><br><code><?php echo esc_html( $intake_email ); ?></code></p>

			<?php if ( $delivery_url ) : ?>
				<p><strong>Client Delivery Link:</strong></p>
				<input type="text" readonly value="<?php echo esc_url( $delivery_url ); ?>" class="widefat" style="font-size:11px; margin-bottom:8px;" onclick="this.select();">
				<button type="button" class="button button-small widefat aip-btn-copy-delivery" data-url="<?php echo esc_url( $delivery_url ); ?>">Copy Delivery Link</button>
			<?php endif; ?>
		</div>
		<?php
	}

	public static function ajax_update_order_status() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => 'Permission denied' ), 403 );
		}
		$order_id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
		$status   = isset( $_POST['status'] ) ? sanitize_key( $_POST['status'] ) : '';
		$order    = $order_id ? wc_get_order( $order_id ) : false;
		if ( ! $order || ! $status ) {
			wp_send_json_error( array( 'message' => 'Invalid parameters' ), 400 );
		}

		$order->update_status( $status, 'Status updated via On-Model Admin control panel', true );
		wp_send_json_success( array(
			'order_id'     => $order_id,
			'status'       => $status,
			'status_label' => wc_get_order_status_name( $status ),
		) );
	}

	public static function ajax_deliver_order() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => 'Permission denied' ), 403 );
		}
		$order_id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
		$order    = $order_id ? wc_get_order( $order_id ) : false;
		if ( ! $order ) {
			wp_send_json_error( array( 'message' => 'Order not found' ), 404 );
		}

		$saved  = $order->get_meta( '_aip_deliverables' );
		$images = array_values( array_filter( array_map( 'esc_url_raw', (array) ( $saved['images'] ?? [] ) ) ) );
		$videos = array_values( array_filter( array_map( 'esc_url_raw', (array) ( $saved['videos'] ?? [] ) ) ) );
		if ( empty( $images ) && empty( $videos ) ) {
			wp_send_json_error( array( 'message' => 'No deliverables available to send' ), 400 );
		}

		$token = $order->get_meta( '_aip_delivery_token' );
		if ( ! $token ) {
			$token = wp_generate_password( 48, false, false );
			$order->update_meta_data( '_aip_delivery_token', $token );
		}
		$deliverables = is_array( $saved ) ? $saved : array();
		$deliverables['delivered_at'] = current_time( DATE_ATOM );
		$order->update_meta_data( '_aip_deliverables', $deliverables );
		$order->save();

		$previous_status = $order->get_status();
		$order->update_status( 'completed', 'Submitted deliverables attached to customer downloads and sent to client.', true );
		if ( 'completed' === $previous_status ) {
			$emails = WC()->mailer()->get_emails();
			if ( isset( $emails['WC_Email_Customer_Completed_Order'] ) ) {
				$emails['WC_Email_Customer_Completed_Order']->trigger( $order->get_id(), $order );
			}
		}

		$delivery_url = add_query_arg( array( 'aip_order_delivery' => $order_id, 'aip_token' => $token ), home_url( '/' ) );
		wp_send_json_success( array(
			'order_id'     => $order_id,
			'delivery_url' => $delivery_url,
			'status'       => 'completed',
			'status_label' => wc_get_order_status_name( 'completed' ),
		) );
	}

	public static function admin_order_assets( $hook_suffix ) {
		$screen    = get_current_screen();
		$screen_id = $screen ? $screen->id : '';
		if ( false === strpos( $screen_id, 'shop_order' ) && false === strpos( $screen_id, 'wc-orders' ) && 'post.php' !== $hook_suffix ) {
			return;
		}

		$css = '
		.aip-admin-order-wrap{background:#fff;border:1px solid #e2dbec;border-radius:12px;box-shadow:0 6px 20px rgba(32,18,54,.05);margin-top:10px;overflow:hidden}.aip-admin-header-bar{align-items:center;background:linear-gradient(135deg,#1b1525 0%,#2e2242 100%);color:#fff;display:flex;justify-content:space-between;padding:18px 24px}.aip-admin-tag{color:#b79bff;font-size:10px;font-weight:800;letter-spacing:1.5px;text-transform:uppercase}.aip-admin-title{color:#fff;font-size:20px;font-weight:800;margin:4px 0 0}.aip-admin-badge{background:#6846e6;border-radius:20px;color:#fff;font-size:12px;font-weight:700;padding:6px 14px;text-transform:capitalize}.aip-badge-completed{background:#10b981!important}.aip-badge-content-review{background:#f59e0b!important}.aip-badge-content-creating{background:#3b82f6!important}.aip-badge-content-queued{background:#8b5cf6!important}.aip-admin-grid{display:grid;gap:20px;grid-template-columns:1fr 1fr;padding:24px}@media(max-width:1080px){.aip-admin-grid{grid-template-columns:1fr}}.aip-admin-card{background:#faf8fd;border:1px solid #e7e0f0;border-radius:10px;display:flex;flex-direction:column;overflow:hidden}.aip-card-header{align-items:center;background:#f1ecf9;border-bottom:1px solid #e4dcee;display:flex;justify-content:space-between;padding:12px 18px}.aip-card-header h4{color:#1e1829;font-size:15px;font-weight:750;margin:0}.aip-time-note{color:#766c82;font-size:11px}.aip-card-body{display:flex;flex:1;flex-direction:column;gap:14px;padding:18px}.aip-asin-preview{align-items:center;background:#fff;border:1px solid #e0d8eb;border-radius:8px;display:flex;gap:14px;padding:12px}.aip-asin-img{border-radius:6px;height:72px;object-fit:cover;width:60px}.aip-subhead{color:#5c5269;font-size:12px;font-weight:750;margin-top:6px}.aip-thumbs-grid{display:grid;gap:10px;grid-template-columns:repeat(auto-fill,minmax(90px,1fr))}.aip-thumb-card{background:#fff;border:1px solid #e2d9ee;border-radius:8px;color:#221b2d;display:block;overflow:hidden;text-align:center;text-decoration:none!important}.aip-thumb-card img{aspect-ratio:1;display:block;object-fit:cover;width:100%}.aip-thumb-card span{display:block;font-size:10px;font-weight:600;overflow:hidden;padding:5px 4px;text-overflow:ellipsis;white-space:nowrap}.aip-thumb-icon{align-items:center;background:#f3effc;display:flex;font-size:24px;height:70px;justify-content:center}.aip-notes-box{background:#fff;border:1px solid #e3dbeb;border-left:4px solid #6846e6;border-radius:6px;color:#352c42;font-size:12px;padding:12px 14px}.aip-notes-box p{margin:4px 0 0}.aip-empty-deliverables{background:#fff;border:2px dashed #e3dbed;border-radius:8px;padding:28px 16px;text-align:center}.aip-empty-icon{display:block;font-size:28px;margin-bottom:8px}.aip-deliverables-grid{display:grid;gap:14px;grid-template-columns:repeat(auto-fill,minmax(140px,1fr))}.aip-media-card{background:#fff;border:1px solid #e2d8ed;border-radius:8px;overflow:hidden}.aip-media-card img,.aip-media-card video{aspect-ratio:9/16;background:#140f1a;display:block;object-fit:cover;width:100%}.aip-media-footer{align-items:center;display:flex;justify-content:space-between;padding:8px}.aip-media-footer span{font-size:10px;font-weight:700}.aip-stats-box{background:#f3effc;border:1px solid #ded4ef;border-radius:6px;font-size:11px;padding:10px 14px}.aip-stats-box ul{margin:4px 0 0;padding-left:16px}.aip-action-buttons{display:flex;flex-wrap:wrap;gap:10px;margin-top:auto;padding-top:10px}.aip-sidebar-status-group{display:flex;flex-direction:column;gap:6px;margin-top:6px}.aip-sidebar-status-group button{font-size:11px!important;text-align:left!important}
		';

		wp_register_style( 'aip-admin-order-style', false, array(), self::VERSION );
		wp_enqueue_style( 'aip-admin-order-style' );
		wp_add_inline_style( 'aip-admin-order-style', $css );

		$js = "
		jQuery(document).ready(function($){
			$(document).on('click', '.aip-sidebar-status-group button, [data-aip-status]', function(e){
				e.preventDefault();
				var btn = $(this);
				var orderId = btn.data('order-id');
				var status = btn.data('aip-status');
				if(!orderId || !status) return;
				btn.prop('disabled', true);
				$.post(ajaxurl, {
					action: 'aip_admin_update_order_status',
					order_id: orderId,
					status: status
				}, function(res){
					btn.prop('disabled', false);
					if(res.success){
						window.location.reload();
					} else {
						alert(res.data && res.data.message ? res.data.message : 'Error updating status');
					}
				});
			});

			$(document).on('click', '.aip-btn-deliver-order', function(e){
				e.preventDefault();
				var btn = $(this);
				var orderId = btn.data('order-id');
				if(!orderId || !confirm('Deliver this content to client and send completion email now?')) return;
				btn.prop('disabled', true).text('Delivering...');
				$.post(ajaxurl, {
					action: 'aip_admin_deliver_order',
					order_id: orderId
				}, function(res){
					btn.prop('disabled', false);
					if(res.success){
						alert('Deliverables successfully sent to client!');
						window.location.reload();
					} else {
						alert(res.data && res.data.message ? res.data.message : 'Delivery failed');
					}
				});
			});

			$(document).on('click', '.aip-btn-copy-delivery', function(e){
				e.preventDefault();
				var url = $(this).data('url');
				if(!url) return;
				if(navigator.clipboard && navigator.clipboard.writeText){
					navigator.clipboard.writeText(url).then(function(){
						alert('Client delivery link copied to clipboard!');
					});
				} else {
					prompt('Copy client delivery URL:', url);
				}
			});
		});
		";

		wp_add_inline_script( 'jquery', $js, 'after' );
	}
}

AIP_On_Model_Commerce_GitHub::init();
