<?php
/**
 * Plugin Name: Style by REii Commerce
 * Description: WooCommerce ordering and private client delivery for Style by REii shoppable UGC videos.
 * Version: 0.5.27
 * Author: Tech by Leon
 * Requires Plugins: woocommerce
 * Update URI: https://github.com/whoisleon/on-model-commerce
 */

defined( 'ABSPATH' ) || exit;

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
		$message = __( 'Style by REii Commerce is already on the latest GitHub release.', 'on-model-commerce' );
		$class   = 'notice notice-success is-dismissible';
	} elseif ( 'unavailable' === $status ) {
		$message = __( 'The latest GitHub release could not be reached. Please try again shortly.', 'on-model-commerce' );
		$class   = 'notice notice-error is-dismissible';
	} elseif ( 'updated' === $status ) {
		$message = __( 'Style by REii Commerce was updated from the latest GitHub release.', 'on-model-commerce' );
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

// The GitHub-enabled build intentionally uses a new permanent directory to
// escape legacy WordPress.com folders that cannot be overwritten. If an older
// copy is still active during the one-time migration, deactivate that file and
// let this copy take over on the next request instead of triggering a duplicate
// class fatal error.
if ( class_exists( 'AIP_On_Model_Commerce_GitHub', false ) ) {
	return;
}

final class AIP_On_Model_Commerce_GitHub {
	const VERSION     = '0.5.27';
	const PRODUCT_SKU = 'on-model-content-order';
	const FORM_TITLE  = 'On-Model Content Order Form';
	const BASE_PRICE  = '20';
	const GITHUB_REPOSITORY = 'whoisleon/on-model-commerce';
	const UPDATE_CACHE_KEY  = 'aip_on_model_github_release';

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_order_statuses' ) );
		add_filter( 'wc_order_statuses', array( __CLASS__, 'order_status_labels' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'checkout_bridge_script' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'fast_checkout_styles' ) );
		add_filter( 'body_class', array( __CLASS__, 'fast_checkout_body_class' ) );
		add_filter( 'woocommerce_default_address_fields', array( __CLASS__, 'optional_address_fields' ) );
		add_filter( 'woocommerce_billing_fields', array( __CLASS__, 'optional_billing_fields' ) );
		add_filter( 'woocommerce_checkout_fields', array( __CLASS__, 'remove_service_billing_fields' ), 100 );
		add_action( 'admin_init', array( __CLASS__, 'ensure_service_product' ) );
		add_action( 'admin_notices', array( __CLASS__, 'service_product_notice' ) );
		add_action( 'wpcf7_before_send_mail', array( __CLASS__, 'capture_intake' ), 10, 3 );
		add_filter( 'wpcf7_skip_mail', array( __CLASS__, 'skip_intake_email' ), 10, 2 );
		add_filter( 'woocommerce_checkout_get_value', array( __CLASS__, 'prefill_checkout_value' ), 10, 2 );
		add_filter( 'woocommerce_get_item_data', array( __CLASS__, 'cart_item_details' ), 10, 2 );
		add_action( 'woocommerce_before_calculate_totals', array( __CLASS__, 'apply_addon_price' ), 20 );
		add_action( 'woocommerce_checkout_create_order_line_item', array( __CLASS__, 'copy_intake_to_order_item' ), 10, 4 );
		add_filter( 'woocommerce_order_item_get_formatted_meta_data', array( __CLASS__, 'filter_order_item_display_meta' ), 10, 2 );
		add_filter( 'woocommerce_order_item_name', array( __CLASS__, 'add_item_thumbnail_to_confirmation' ), 10, 3 );
		add_action( 'woocommerce_checkout_create_order', array( __CLASS__, 'apply_intake_to_order' ), 10, 2 );
		add_action( 'woocommerce_email_after_order_table', array( __CLASS__, 'email_delivery_links' ), 20, 4 );
		add_filter( 'woocommerce_email_subject_customer_completed_order', array( __CLASS__, 'custom_completed_email_subject' ), 10, 2 );
		add_filter( 'woocommerce_email_heading_customer_completed_order', array( __CLASS__, 'custom_completed_email_heading' ), 10, 2 );
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
			'name'          => 'Style by REii Commerce',
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
			$message = __( 'Style by REii Commerce is already on the latest GitHub release.', 'on-model-commerce' );
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
		$reference      = '';
		$notes          = '';
		$method         = '';
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
		$created_at         = $order->get_date_created() ? $order->get_date_created()->date( DATE_ATOM ) : null;
		$deliverables       = $order->get_meta( '_aip_deliverables' );
		$deliverables       = is_array( $deliverables ) ? $deliverables : array();
		$client_submitted_at = isset( $deliverables['delivered_at'] ) ? $deliverables['delivered_at'] : null;
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
		$raw_status = sanitize_key( $request->get_param( 'status' ) ?: 'processing' );
		if ( 'processing' === $raw_status ) {
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
		return rest_ensure_response(
			array(
				'orders' => array_map( array( __CLASS__, 'api_order_object' ), $result->orders ),
				'total'  => (int) $result->total,
				'pages'  => (int) $result->max_num_pages,
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
		foreach ( array( 'model_profile', 'scene', 'lighting', 'format', 'transition', 'video_filter', 'pose' ) as $brief_key ) {
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
			<p>Review and download the finished Style by REii shoppable video below.</p>
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
			echo "\nVIEW AND DOWNLOAD YOUR CONTENT\n" . esc_url_raw( $delivery_url ) . "\n\n";
			return;
		}
		?>
		<div style="margin:32px 0 24px;padding:24px;border:1px solid #ded7e5;border-radius:10px;">
			<h2 style="margin:0 0 8px;">Your content is ready</h2>
			<p style="margin:0 0 18px;">Open your private delivery page to view and download your finished Style by REii shoppable video. No password is required.</p>
			<p style="margin:10px 0;">
				<a href="<?php echo esc_url( $delivery_url ); ?>" style="display:inline-block;background:#6846e6;color:#ffffff;text-decoration:none;padding:12px 18px;border-radius:7px;font-weight:700;">View and download your content</a>
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
				$label = $vid_count > 1 ? sprintf( 'Shoppable UGC Video %d', $index + 1 ) : 'Shoppable UGC Video';
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
		<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Your Video Library · Tech by Leon</title>
		<style>:root{--ink:#211b28;--muted:#756d7d;--line:#e8e3eb;--page:#f4f2f6;--purple:#6846e6;--purple-dark:#5634d1;--purple-soft:#f2eeff}*{box-sizing:border-box}body{background:var(--page);color:var(--ink);font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;margin:0;padding:34px 18px;-webkit-font-smoothing:antialiased}.wrap{margin:0 auto;max-width:1080px}.head{background:#1b1622;border-radius:20px;color:#fff;margin-bottom:22px;padding:34px 38px}.head small{color:#bfa8ff;display:block;font-size:10px;font-weight:800;letter-spacing:1.8px;margin-bottom:7px;text-transform:uppercase}.head-row{align-items:end;display:flex;gap:24px;justify-content:space-between}.head h1{font-size:34px;font-weight:800;letter-spacing:-.8px;margin:0 0 5px}.head p{color:#cdc6d5;font-size:14px;margin:0}.library-count{color:#d9d2e2;font-size:12px;font-weight:700;white-space:nowrap}.content{display:grid;gap:20px}.order{background:#fff;border:1px solid var(--line);border-radius:18px;box-shadow:0 8px 30px rgba(32,23,42,.05);overflow:hidden}.order-head{align-items:center;border-bottom:1px solid var(--line);display:grid;gap:15px;grid-template-columns:auto 1fr auto;padding:18px 22px}.order-thumb{border-radius:10px;height:68px;object-fit:cover;width:58px}.order-kicker{color:var(--purple);font-size:9px;font-weight:800;letter-spacing:1.3px;margin:0 0 4px;text-transform:uppercase}.order-head h2{font-size:21px;letter-spacing:-.4px;margin:0 0 4px}.order-meta{color:var(--muted);font-size:12px}.delivered-pill{background:#edf9f1;border:1px solid #cdebd7;border-radius:999px;color:#24733f;font-size:10px;font-weight:800;padding:7px 10px;text-transform:uppercase}.order-body{display:grid;gap:26px;grid-template-columns:minmax(0,1fr) 290px;padding:24px}.brief-title{font-size:15px;margin:0 0 15px}.detail-grid{display:grid;gap:15px 20px;grid-template-columns:repeat(2,minmax(0,1fr));margin:0}.detail{border-bottom:1px solid #f0ecf2;padding-bottom:12px}.detail.full{grid-column:1/-1}.detail dt{color:#8a818f;font-size:9px;font-weight:800;letter-spacing:1px;margin-bottom:5px;text-transform:uppercase}.detail dd{color:#2c2532;font-size:13px;font-weight:650;line-height:1.45;margin:0;overflow-wrap:anywhere}.detail dd.request{font-weight:500}.video-stack{display:grid;gap:14px}.video-card{background:#17131d;border-radius:13px;overflow:hidden}.video-card video{aspect-ratio:9/16;background:#17131d;display:block;max-height:390px;object-fit:cover;width:100%}.video-actions{background:#fff;border:1px solid var(--line);border-top:0;padding:14px}.video-actions strong{display:block;font-size:13px;margin-bottom:10px}.button{background:var(--purple);border-radius:9px;color:#fff;display:block;font-size:13px;font-weight:750;padding:11px 14px;text-align:center;text-decoration:none}.button:hover{background:var(--purple-dark)}.upsell{align-items:center;background:var(--purple-soft);border-top:1px solid #dfd5ff;display:grid;gap:20px;grid-template-columns:1fr auto;padding:20px 24px}.upsell small{color:var(--purple);display:block;font-size:9px;font-weight:850;letter-spacing:1.2px;margin-bottom:5px;text-transform:uppercase}.upsell h3{font-size:16px;margin:0 0 4px}.upsell p{color:#665d70;font-size:12px;line-height:1.45;margin:0}.upsell-actions{display:flex;gap:9px}.upsell-link{border:1px solid #cfc2fa;border-radius:8px;color:#5237be;font-size:12px;font-weight:750;padding:10px 13px;text-decoration:none;white-space:nowrap}.upsell-link.primary{background:var(--purple);border-color:var(--purple);color:#fff}.note{color:#7c7384;font-size:12px;margin:4px 0 0;text-align:center}.aip-intake-gallery{display:grid;gap:10px;grid-template-columns:repeat(auto-fill,minmax(84px,1fr));margin-top:8px}.aip-intake-thumb{background:#fff;border:1px solid #e3dcee;border-radius:10px;display:block;overflow:hidden;text-align:center;text-decoration:none!important;transition:transform .15s ease,box-shadow .15s ease}.aip-intake-thumb:hover{box-shadow:0 4px 14px rgba(104,70,230,.15);transform:translateY(-1px)}.aip-intake-thumb img{aspect-ratio:1;background:#181320;display:block;object-fit:cover;width:100%}.aip-intake-thumb-icon{align-items:center;background:#f3eeff;color:#6846e6;display:flex;font-size:22px;height:84px;justify-content:center}.aip-intake-thumb span{color:#342a3e;display:block;font-size:10px;font-weight:700;overflow:hidden;padding:5px 4px;text-overflow:ellipsis;white-space:nowrap}@media(max-width:760px){body{padding:16px 10px}.head{border-radius:15px;padding:26px 22px}.head-row{align-items:start;flex-direction:column;gap:14px}.head h1{font-size:28px}.order-head{grid-template-columns:auto 1fr;padding:15px}.delivered-pill{display:none}.order-body{grid-template-columns:1fr;padding:18px}.detail-grid{grid-template-columns:1fr}.detail.full{grid-column:auto}.video-card video{max-height:520px}.upsell{grid-template-columns:1fr;padding:18px}.upsell-actions{flex-direction:column}.upsell-link{text-align:center}}</style></head><body><main class="wrap"><header class="head"><small>TECH BY LEON</small><div class="head-row"><div><h1>Your video library</h1><p>Every finished product video, request brief, and download in one place.</p></div><span class="library-count"><?php echo esc_html( count( $customer_orders ) ); ?> completed order<?php echo 1 === count( $customer_orders ) ? '' : 's'; ?> · <?php echo esc_html( $total_videos ); ?> video<?php echo 1 === $total_videos ? '' : 's'; ?></span></div></header><section class="content">
		<style>
		.head{display:none}.reii-library-head{background:#1b1622;border-radius:20px;color:#fff;margin-bottom:22px;padding:34px 38px}.reii-library-head small{color:#bfa8ff;display:block;font-size:10px;font-weight:800;letter-spacing:1.8px;margin-bottom:7px;text-transform:uppercase}.reii-library-head-row{align-items:end;display:flex;gap:24px;justify-content:space-between}.reii-library-head h1{font-size:34px;font-weight:800;letter-spacing:-.8px;margin:0 0 5px}.reii-library-head p{color:#cdc6d5;font-size:14px;margin:0}.upsell{display:none}.delivery-addons{background:#fff;border:1px solid var(--line);border-radius:18px;box-shadow:0 8px 30px rgba(32,23,42,.05);margin-top:2px;overflow:hidden}.delivery-addons-head{padding:25px 26px 18px}.delivery-addons-head small{color:var(--purple);display:block;font-size:9px;font-weight:850;letter-spacing:1.2px;margin-bottom:7px;text-transform:uppercase}.delivery-addons-head h2{font-size:25px;letter-spacing:-.5px;margin:0 0 6px}.delivery-addons-head p{color:var(--muted);font-size:13px;margin:0}.delivery-addon-list{border-top:1px solid var(--line)}.delivery-addon{align-items:center;border-bottom:1px solid var(--line);color:var(--ink);display:grid;gap:16px;grid-template-columns:1fr auto auto;min-height:76px;padding:12px 26px;text-decoration:none}.delivery-addon:last-child{border-bottom:0}.delivery-addon:hover{background:var(--purple-soft)}.delivery-addon strong,.delivery-addon span{display:block}.delivery-addon strong{font-size:14px}.delivery-addon span{color:var(--muted);font-size:11px;margin-top:3px}.delivery-addon b{color:var(--purple);font-size:13px}.delivery-addon i{align-items:center;border:1px solid var(--purple);border-radius:50%;color:var(--purple);display:flex;font-size:18px;font-style:normal;height:34px;justify-content:center;width:34px}@media(max-width:760px){.reii-library-head{border-radius:15px;padding:26px 22px}.reii-library-head-row{align-items:start;flex-direction:column;gap:14px}.reii-library-head h1{font-size:28px}.delivery-addon{grid-template-columns:1fr auto auto;padding:12px 18px}}
		</style>
		<header class="reii-library-head"><small>STYLE BY REii</small><div class="reii-library-head-row"><div><h1>Your shoppable video library</h1><p>Every finished UGC-style product video, request brief, and download in one private place.</p></div><span class="library-count"><?php echo esc_html( count( $customer_orders ) ); ?> completed order<?php echo 1 === count( $customer_orders ) ? '' : 's'; ?> · <?php echo esc_html( $total_videos ); ?> video<?php echo 1 === $total_videos ? '' : 's'; ?></span></div></header>
		<?php foreach ( $customer_orders as $customer_order ) :
			$files = self::delivery_files( $customer_order );
			$details = self::delivery_order_details( $customer_order );
			$first_img_key = false;
			foreach ( $files as $k => $f ) {
				if ( 'image' === $f['type'] ) { $first_img_key = $k; break; }
			}
			$poster_url = $first_img_key ? self::tracked_file_url( $customer_order, $first_img_key, 'preview' ) : '';
			$variation_url = add_query_arg( array( 'aip_offer' => 'new-version', 'source_order' => $customer_order->get_id() ), home_url( '/on-model-content/' ) ) . '#submit-project';
			$new_product_url = add_query_arg( array( 'aip_offer' => 'new-product', 'source_order' => $customer_order->get_id() ), home_url( '/on-model-content/' ) ) . '#submit-project';
		?>
		<article class="order"><header class="order-head"><?php if ( $poster_url ) : ?><img class="order-thumb" src="<?php echo esc_url( $poster_url ); ?>" alt="Order thumbnail"><?php endif; ?><div><p class="order-kicker">Completed content</p><h2>Order #<?php echo esc_html( $customer_order->get_order_number() ); ?></h2><span class="order-meta"><?php echo esc_html( wc_format_datetime( $customer_order->get_date_created() ) ); ?> · <?php echo wp_kses_post( $customer_order->get_formatted_order_total() ); ?></span></div><span class="delivered-pill">Delivered</span></header><div class="order-body"><section><h3 class="brief-title">What you requested</h3><dl class="detail-grid"><div class="detail"><dt>Package</dt><dd><?php echo esc_html( $details['package'] ?: 'On-Model Content Package' ); ?></dd></div><div class="detail"><dt>Product source</dt><dd><?php echo esc_html( $details['source'] ); ?></dd></div><?php if ( $details['reference'] ) : $ref_asin = self::extract_asin( $details['reference'] ); $ref_label = $ref_asin ? 'ASIN: ' . $ref_asin : self::format_reference_for_display( $details['reference'] ); $amazon_url = $ref_asin ? "https://www.amazon.com/dp/{$ref_asin}" : ( preg_match( '/^https?:\/\//i', $details['reference'] ) ? $details['reference'] : '' ); $product_img = $ref_asin ? "https://images-na.ssl-images-amazon.com/images/P/{$ref_asin}.01.MAIN._AC_SY300_.jpg" : ( ! empty( $details['uploaded_file_objects'] ) ? ( $details['uploaded_file_objects'][0]['url'] ?? '' ) : '' ); ?><div class="detail full"><dt>Amazon link / ASIN</dt><dd style="display:flex; align-items:center; gap:14px; margin-top:6px;"><?php if ( $product_img ) : ?><a href="<?php echo esc_url( $amazon_url ?: '#' ); ?>" <?php if ( $amazon_url ) echo 'target="_blank" rel="noopener"'; ?> style="display:block; flex-shrink:0;"><img src="<?php echo esc_url( $product_img ); ?>" alt="Product thumbnail" style="width:58px; height:70px; object-fit:cover; border-radius:8px; border:1px solid #e3dcee; background:#181320; display:block;" onerror="this.style.display='none'"></a><?php endif; ?><div><strong style="font-size:14px; color:#211b28; display:block; font-family:monospace,sans-serif; font-weight:750;"><?php echo esc_html( $ref_label ); ?></strong><?php if ( $amazon_url ) : ?><a href="<?php echo esc_url( $amazon_url ); ?>" target="_blank" rel="noopener" style="color:#6846e6; font-size:12px; font-weight:750; text-decoration:none; display:inline-block; margin-top:3px;">View on Amazon ↗</a><?php endif; ?></div></dd></div><?php endif; ?><?php if ( ! empty( $details['uploaded_file_objects'] ) || $details['uploaded_files'] ) : ?><div class="detail full"><dt>Uploaded product files</dt><dd><?php if ( ! empty( $details['uploaded_file_objects'] ) ) : ?><div class="aip-intake-gallery"><?php foreach ( $details['uploaded_file_objects'] as $u_file ) : $f_url = is_array( $u_file ) ? ( $u_file['url'] ?? '' ) : ( is_string( $u_file ) ? $u_file : '' ); $f_name = is_array( $u_file ) ? ( $u_file['name'] ?? 'Uploaded file' ) : (string) $u_file; $is_img = $f_url && ( preg_match( '/\.(jpg|jpeg|png|webp|gif|svg)$/i', $f_url ) || strpos( $f_url, 'data:image' ) === 0 ); ?><a href="<?php echo esc_url( $f_url ?: '#' ); ?>" <?php if ( $f_url ) echo 'target="_blank" rel="noopener"'; ?> class="aip-intake-thumb" title="<?php echo esc_attr( $f_name ); ?>"><?php if ( $is_img ) : ?><img src="<?php echo esc_url( $f_url ); ?>" alt="<?php echo esc_attr( $f_name ); ?>"><?php else : ?><div class="aip-intake-thumb-icon">📄</div><?php endif; ?><span><?php echo esc_html( $f_name ); ?></span></a><?php endforeach; ?></div><?php else : ?><?php echo esc_html( $details['uploaded_files'] ); ?><?php endif; ?></dd></div><?php endif; ?><div class="detail full"><dt>Your instructions</dt><dd class="request"><?php echo esc_html( $details['instructions'] ?: 'No additional instructions were provided.' ); ?></dd></div><?php foreach ( $details['production'] as $production_detail ) : ?><div class="detail"><dt><?php echo esc_html( $production_detail['label'] ); ?></dt><dd><?php echo esc_html( $production_detail['value'] ); ?></dd></div><?php endforeach; ?></dl></section><aside class="video-stack"><?php foreach ( $files as $key => $file ) : if ( 'video' !== $file['type'] ) continue; ?><section class="video-card"><video controls playsinline preload="metadata"<?php if ( $poster_url ) echo ' poster="' . esc_url( $poster_url ) . '"'; ?> src="<?php echo esc_url( self::tracked_file_url( $customer_order, $key, 'preview' ) ); ?>"></video><div class="video-actions"><strong><?php echo esc_html( $file['label'] ); ?></strong><a class="button" href="<?php echo esc_url( self::tracked_file_url( $customer_order, $key ) ); ?>">Download HD video</a></div></section><?php endforeach; ?></aside></div><footer class="upsell"><div><small>Make more from this product</small><h3>Turn this order into your next piece of content</h3><p>Request another hook, scene, or video cut—or start fresh with a new product.</p></div><div class="upsell-actions"><a class="upsell-link" href="<?php echo esc_url( $variation_url ); ?>">Create another version</a><a class="upsell-link primary" href="<?php echo esc_url( $new_product_url ); ?>">Start a new product</a></div></footer></article><?php endforeach; ?>
		<section class="delivery-addons"><header class="delivery-addons-head"><small>MAKE MORE FROM YOUR PRODUCT</small><h2>Customize your next feature</h2><p>Add another version without starting from scratch.</p></header><div class="delivery-addon-list">
		<?php
		$delivery_addons = self::addon_catalog();
		$delivery_addon_descriptions = array(
			'extra-environment'  => 'A new location, new feel. Same product.',
			'another-version'    => 'Another pose or cut with the same setup.',
			'20-second-story'    => 'Extend your feature to a 20-second vertical video.',
			'alternate-lighting' => 'Different lighting to match your brand.',
			'priority-delivery'  => 'Get the next version faster with priority turnaround.',
		);
		foreach ( $delivery_addons as $addon_slug => $addon ) :
			$addon_url = add_query_arg( array( 'aip_offer' => $addon_slug, 'source_order' => $order->get_id() ), home_url( '/on-model-content/' ) ) . '#submit-project';
		?>
		<a class="delivery-addon" href="<?php echo esc_url( $addon_url ); ?>"><span><strong><?php echo esc_html( $addon['label'] ); ?></strong><span><?php echo esc_html( $delivery_addon_descriptions[ $addon_slug ] ); ?></span></span><b>+$<?php echo esc_html( $addon['price'] ); ?></b><i aria-hidden="true">+</i></a>
		<?php endforeach; ?>
		</div></section>
		<p class="note">This private link stays available whenever you need to re-download a finished shoppable video.</p></section></main></body></html>
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
		$product->set_name( 'Style by REii Shoppable Video Feature' );
		$product->set_slug( 'style-by-reii-shoppable-video-feature' );
		$product->set_status( 'publish' );
		$product->set_catalog_visibility( 'hidden' );
		$product->set_virtual( true );
		$product->set_sold_individually( true );
		$product->set_regular_price( self::BASE_PRICE );
		$product->set_price( self::BASE_PRICE );
		$product->set_description( 'One 10-second vertical UGC-style product video, submitted to the Style by REii storefront and delivered as an HD social-ready file.' );
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
		<div class="notice notice-warning"><p><strong>Style by REii checkout needs attention.</strong> Review the hidden shoppable-video service product to enable the intake-to-checkout flow. <a href="<?php echo esc_url( $url ); ?>">Configure service product</a>.</p></div>
		<?php
	}

	public static function checkout_bridge_script() {
		if ( ! is_page( 'on-model-content' ) ) {
			return;
		}

		$product = self::service_product();
		$config  = array(
			'checkoutUrl'         => wc_get_checkout_url(),
			'embeddedCheckoutUrl' => add_query_arg( 'aip_embedded', '1', wc_get_checkout_url() ),
			'ready'               => (bool) ( $product && $product->is_purchasable() ),
			'notReady'            => 'Checkout is being configured. Your product details were saved, but payment is not available yet.',
		);

		wp_enqueue_script( 'jquery' );
		wp_add_inline_script( 'jquery', 'window.aipCommerceConfig=' . wp_json_encode( $config ) . ';', 'after' );
		wp_add_inline_script(
			'jquery',
			"(function(){function clearPortalFiles(portal){if(!portal)return;var forms=portal.querySelectorAll('form.wpcf7-form, form');forms.forEach(function(form){try{form.reset();}catch(e){}form.setAttribute('action','javascript:void(0);');form.classList.remove('submitting','sent','failed','invalid','spam');form.setAttribute('data-status','init');var responseOutput=form.querySelector('.wpcf7-response-output');if(responseOutput)responseOutput.textContent='';var submitBtn=form.querySelector('input[type=\"submit\"], button[type=\"submit\"]');if(submitBtn)submitBtn.disabled=false;});var inputs=portal.querySelectorAll('input[name^=\"product-file-\"]');inputs.forEach(function(inp){inp.value='';try{inp.files=(new DataTransfer()).files;}catch(e){}inp.dispatchEvent(new Event('change',{bubbles:true}));});var refInput=portal.querySelector('input[name=\"product-reference\"]');if(refInput)refInput.value='';var emailInput=portal.querySelector('input[name=\"your-email\"]');if(emailInput)emailInput.value='';var notesInput=portal.querySelector('textarea[name=\"your-message\"]');if(notesInput)notesInput.value='';try{portal.dispatchEvent(new CustomEvent('reset'));}catch(e){}var previewList=portal.querySelector('.aip-drop-preview-list');if(previewList){previewList.innerHTML='';previewList.hidden=true;}var dropzone=portal.querySelector('.aip-dropzone');if(dropzone)dropzone.classList.remove('has-file');var dropFooter=portal.querySelector('.aip-drop-footer');if(dropFooter)dropFooter.hidden=true;}function closeDrawer(drawer,portal){if(!drawer){return;}drawer.classList.remove('is-open');document.body.classList.remove('aip-drawer-open');if(portal){clearPortalFiles(portal);}window.setTimeout(function(){drawer.remove();},240);}function openDrawer(cfg,portal){var old=document.querySelector('.aip-checkout-drawer');if(old){old.remove();}var submittedEmail=(portal.querySelector('input[name=\"your-email\"]')||{}).value||'';var drawer=document.createElement('div');drawer.className='aip-checkout-drawer';drawer.setAttribute('role','dialog');drawer.setAttribute('aria-modal','true');drawer.setAttribute('aria-label','Secure checkout');drawer.innerHTML='<button class=\"aip-checkout-backdrop\" type=\"button\" aria-label=\"Close checkout\"></button><section class=\"aip-checkout-panel\"><header><div><small>STEP 2 OF 3</small><strong>Complete your order</strong></div><button class=\"aip-checkout-close\" type=\"button\" aria-label=\"Close checkout\">&times;</button></header><div class=\"aip-checkout-loading\"><span></span>Loading secure payment...</div><iframe title=\"Secure checkout\" allow=\"payment *\" src=\"'+String(cfg.embeddedCheckoutUrl||cfg.checkoutUrl)+'\"></iframe></section>';document.body.appendChild(drawer);document.body.classList.add('aip-drawer-open');window.requestAnimationFrame(function(){drawer.classList.add('is-open');});function updateResponse(){portal.querySelectorAll('.wpcf7-response-output,.screen-reader-response').forEach(function(response){response.textContent='Your product is saved. Complete payment to place your order.';});}updateResponse();window.setTimeout(updateResponse,50);window.setTimeout(updateResponse,500);var frame=drawer.querySelector('iframe');function sendEmail(){if(submittedEmail&&frame.contentWindow){frame.contentWindow.postMessage({type:'aipCheckoutEmail',email:submittedEmail},window.location.origin);}}frame.addEventListener('load',function(){drawer.classList.add('is-loaded');window.setTimeout(sendEmail,100);window.setTimeout(sendEmail,700);try{if(frame.contentWindow.location.href.indexOf('/order-received/')!==-1){drawer.classList.add('is-complete');drawer.querySelector('header small').textContent='STEP 3 OF 3';drawer.querySelector('header strong').textContent='Order confirmed';clearPortalFiles(portal);}}catch(ignore){}});window.addEventListener('message',function(event){if(event.origin===window.location.origin&&event.source===frame.contentWindow&&event.data&&event.data.type==='aipCheckoutReady'){sendEmail();}});drawer.querySelectorAll('.aip-checkout-close,.aip-checkout-backdrop').forEach(function(button){button.addEventListener('click',function(){closeDrawer(drawer,portal);});});document.addEventListener('keydown',function escape(event){if(event.key==='Escape'&&document.body.contains(drawer)){closeDrawer(drawer,portal);document.removeEventListener('keydown',escape);}});}document.addEventListener('submit',function(event){var form=event.target;if(form&&form.closest('.aip-portal')){form.setAttribute('action','javascript:void(0);');if(form.getAttribute('data-status')==='sent'){form.setAttribute('data-status','init');form.classList.remove('sent','submitting','failed','invalid');}}},true);document.addEventListener('wpcf7mailsent',function(event){var cfg=window.aipCommerceConfig||{};var portal=event.target&&event.target.closest('.aip-portal');if(!portal){return;}var error=portal.querySelector('.aip-form-error');if(!cfg.ready){if(error){error.textContent=cfg.notReady||'Checkout is not available yet.';}return;}openDrawer(cfg,portal);clearPortalFiles(portal);});})();",
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
			if ( isset( $_GET['aip_embedded'] ) || is_wc_endpoint_url( 'order-received' ) ) {
				$classes[] = 'aip-embedded-checkout';
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

		$email = isset( $fields['billing']['billing_email'] ) ? $fields['billing']['billing_email'] : array();
		$email['type']     = 'hidden';
		$email['required'] = false;
		$email['label']    = '';

		$fields['billing'] = array( 'billing_email' => $email );

		return $fields;
	}

	public static function fast_checkout_styles() {
		if ( ! is_page( 'on-model-content' ) && ! is_checkout() ) {
			return;
		}
		$css = '
		body.aip-drawer-open{overflow:hidden}.aip-checkout-drawer{inset:0;opacity:0;pointer-events:none;position:fixed;transition:opacity .22s ease;z-index:999999}.aip-checkout-drawer.is-open{opacity:1;pointer-events:auto}.aip-checkout-backdrop{backdrop-filter:blur(7px);background:rgba(20,15,27,.58);border:0;inset:0;position:absolute;width:100%}.aip-checkout-panel{background:#f8f7fb;box-shadow:-24px 0 80px rgba(22,14,31,.22);height:100%;max-width:760px;position:absolute;right:0;top:0;transform:translateX(102%);transition:transform .28s ease;width:min(94vw,760px)}.aip-checkout-drawer.is-open .aip-checkout-panel{transform:translateX(0)}.aip-checkout-panel header{align-items:center;background:#fff;border-bottom:1px solid #e7e2ec;display:flex;height:76px;justify-content:space-between;padding:0 25px}.aip-checkout-panel header small,.aip-checkout-panel header strong{display:block}.aip-checkout-panel header small{color:#7656df;font-size:9px;font-weight:800;letter-spacing:1.4px}.aip-checkout-panel header strong{color:#231d29;font-size:21px;letter-spacing:-.5px;margin-top:2px}.aip-checkout-close{align-items:center;background:#f2eff6;border:0;border-radius:50%;color:#43394b;cursor:pointer;display:flex;font-size:25px;height:38px;justify-content:center;line-height:1;width:38px}.aip-checkout-panel iframe{background:#f8f7fb;border:0;height:calc(100% - 76px);opacity:0;position:relative;transition:opacity .2s ease;width:100%;z-index:2}.aip-checkout-drawer.is-loaded iframe{opacity:1}.aip-checkout-loading{align-items:center;color:#6f6578;display:flex;font-size:12px;gap:11px;left:50%;position:absolute;top:50%;transform:translate(-50%,-50%)}.aip-checkout-loading span{animation:aip-spin .8s linear infinite;border:2px solid #d9d0e5;border-radius:50%;border-top-color:#6846e6;height:22px;width:22px}@keyframes aip-spin{to{transform:rotate(360deg)}}
		body.aip-embedded-checkout #wpadminbar,body.aip-embedded-checkout #masthead,body.aip-embedded-checkout #colophon,body.aip-embedded-checkout .post-title-wrapper,#wpadminbar{display:none!important}html{margin-top:0!important;padding-top:0!important}body.aip-embedded-checkout{background:#f8f7fb!important;margin-top:0!important}body.aip-embedded-checkout .main-container,body.aip-embedded-checkout .page-body{background:#f8f7fb!important;padding:0!important}body.aip-embedded-checkout .row-parent{margin:0 auto!important;max-width:580px!important;padding:16px 18px 30px!important}body.aip-embedded-checkout .woocommerce-billing-fields,body.aip-embedded-checkout .woocommerce-billing-fields__field-wrapper,body.aip-embedded-checkout .woocommerce-shipping-fields,body.aip-embedded-checkout .woocommerce-additional-fields,body.aip-embedded-checkout #customer_details,body.aip-embedded-checkout .woocommerce-checkout #order_review_heading{display:none!important}body.aip-embedded-checkout .wp-block-woocommerce-checkout{background:transparent!important;border:0!important;box-shadow:none!important;margin:0!important;max-width:none!important;padding:0!important}body.aip-embedded-checkout .wp-block-woocommerce-checkout,body.aip-embedded-checkout .wc-block-checkout,body.aip-embedded-checkout form.woocommerce-checkout,body.aip-embedded-checkout .wc-block-components-sidebar-layout{display:flex!important;flex-direction:column!important}body.aip-embedded-checkout .wc-block-components-main,body.aip-embedded-checkout .wc-block-checkout__main{display:contents!important}body.aip-embedded-checkout .wc-block-components-sidebar{float:none!important;margin:0!important;padding:0!important;width:100%!important}body.aip-embedded-checkout .wc-block-checkout__billing-fields,body.aip-embedded-checkout .wc-block-checkout__billing-fields .wc-block-components-checkout-step__container{display:none!important}body.aip-embedded-checkout .wc-block-checkout.is-medium>.wc-block-components-sidebar,body.aip-embedded-checkout .wc-block-checkout.is-small>.wc-block-components-sidebar{display:none!important}body.aip-embedded-checkout .wc-block-express-payment,body.aip-embedded-checkout .wc-block-components-express-payment,body.aip-embedded-checkout .wc-block-checkout__express-payment,body.aip-embedded-checkout .wc-block-components-express-payment-continue-rule{order:1!important}body.aip-embedded-checkout .wc-block-checkout__contact-fields,body.aip-embedded-checkout .wc-block-components-checkout-step--contact{order:2!important}body.aip-embedded-checkout .wc-block-checkout__payment-method,body.aip-embedded-checkout .wc-block-components-checkout-step--payment,body.aip-embedded-checkout #payment,body.aip-embedded-checkout .payment_methods{order:3!important;margin-bottom:20px!important}body.aip-embedded-checkout .wc-block-components-sidebar,body.aip-embedded-checkout .wc-block-checkout__sidebar,body.aip-embedded-checkout #order_review,body.aip-embedded-checkout .woocommerce-checkout-review-order{order:4!important;background:#fff!important;border:1px solid #e4dfe8!important;border-radius:14px!important;margin:0 0 20px!important;padding:16px!important;width:100%!important}body.aip-embedded-checkout .wc-block-components-sidebar *,body.aip-embedded-checkout .wc-block-checkout__sidebar *,body.aip-embedded-checkout #order_review *,body.aip-embedded-checkout .wc-block-components-product-details,body.aip-embedded-checkout .wc-block-components-product-metadata,body.aip-embedded-checkout .wc-item-meta,body.aip-embedded-checkout .wc-item-meta li{overflow-wrap:anywhere!important;word-break:break-word!important;max-width:100%!important}body.aip-embedded-checkout .woocommerce-form-coupon-toggle,body.aip-embedded-checkout .wc-block-components-totals-coupon,body.aip-embedded-checkout .wc-block-checkout__coupon-form,body.aip-embedded-checkout .wc-block-components-totals-coupon-link,body.aip-embedded-checkout form.checkout_coupon,body.aip-embedded-checkout .checkout_coupon,body.aip-embedded-checkout .wc-block-components-panel.wc-block-checkout__coupon-form{order:5!important;margin-top:8px!important;margin-bottom:18px!important}body.aip-embedded-checkout .wc-block-checkout__additional-fields,body.aip-embedded-checkout .wc-block-checkout__terms{order:6!important}body.aip-embedded-checkout .wc-block-checkout__actions,body.aip-embedded-checkout .wc-block-components-checkout-place-order-button,body.aip-embedded-checkout .place-order{order:7!important}body.aip-embedded-checkout .wc-block-components-checkout-step{padding-left:0!important}body.aip-embedded-checkout .wc-block-components-checkout-step__container{margin-left:0!important}body.aip-embedded-checkout .wc-block-components-checkout-step__heading{margin-bottom:13px!important}body.aip-embedded-checkout .wc-block-components-title{font-size:18px!important}body.aip-embedded-checkout .wc-block-components-checkout-step__description{font-size:11px!important}body.aip-embedded-checkout .wc-block-components-button{min-height:56px!important}body.aip-embedded-checkout .woocommerce{display:flex!important;flex-direction:column!important}body.aip-embedded-checkout form.checkout.woocommerce-checkout,body.aip-embedded-checkout #order_review,body.aip-embedded-checkout #payment{display:contents!important}body.aip-embedded-checkout #wc-stripe-express-checkout-element{order:10!important}body.aip-embedded-checkout #wc-stripe-express-checkout__order-attribution-inputs{display:none!important}body.aip-embedded-checkout #wc-stripe-express-checkout-button-separator{order:20!important}body.aip-embedded-checkout .payment_methods{margin:0 0 20px!important;order:30!important}body.aip-embedded-checkout .shop_table.woocommerce-checkout-review-order-table{background:#fff!important;border:1px solid #e4dfe8!important;border-radius:14px!important;box-shadow:none!important;margin:0 0 18px!important;order:40!important;overflow:hidden!important;padding:0!important;width:100%!important}body.aip-embedded-checkout .woocommerce-form-coupon-toggle{margin:0 0 10px!important;order:50!important}body.aip-embedded-checkout form.checkout_coupon{margin:0 0 18px!important;order:51!important}body.aip-embedded-checkout .place-order{margin-top:0!important;order:60!important}@media(max-width:600px){.aip-checkout-panel{max-width:none;width:100%}.aip-checkout-panel header{height:68px;padding:0 18px}.aip-checkout-panel iframe{height:calc(100% - 68px)}body.aip-embedded-checkout .row-parent{padding:18px 14px 34px!important}}
		.aip-checkout-upload-preview{border-top:1px solid #eee9f2;margin-top:12px;padding-top:12px}.aip-checkout-upload-preview>strong{color:#5a5162;display:block;font-size:10px!important;letter-spacing:.02em;margin-bottom:9px}.aip-checkout-upload-grid{display:grid;gap:8px;grid-template-columns:repeat(3,minmax(0,1fr));max-width:330px}.aip-checkout-upload-card{background:#faf8fd;border:1px solid #e2dce8;border-radius:9px;color:#4b4253!important;display:block;overflow:hidden;text-decoration:none!important}.aip-checkout-upload-card img,.aip-checkout-upload-file{aspect-ratio:4/3;background:#eee8fa;display:block;object-fit:cover;width:100%}.aip-checkout-upload-file{align-items:center;color:#6846e6;display:flex;font-size:20px;justify-content:center}.aip-checkout-upload-card span{display:block;font-size:8px!important;font-weight:700;overflow:hidden;padding:6px;text-overflow:ellipsis;white-space:nowrap}.aip-checkout-upload-card:hover{border-color:#886cf0;box-shadow:0 4px 14px rgba(104,70,230,.12)}
		';
		wp_register_style( 'aip-fast-checkout', false, array(), self::VERSION );
		wp_enqueue_style( 'aip-fast-checkout' );
		wp_add_inline_style( 'aip-fast-checkout', $css );

		if ( is_checkout() ) {
			wp_enqueue_script( 'jquery' );
			$intake = WC()->session ? WC()->session->get( 'aip_intake' ) : array();
			$files  = ! empty( $intake['files'] ) && is_array( $intake['files'] ) ? array_slice( $intake['files'], 0, 4 ) : array();
			wp_add_inline_script( 'jquery', 'window.aipCheckoutFiles=' . wp_json_encode( $files ) . ';', 'after' );
			wp_add_inline_script(
				'jquery',
				"(function(){if(window.self!==window.top){document.documentElement.style.setProperty('margin-top','0px','important');var bar=document.getElementById('wpadminbar');if(bar)bar.style.setProperty('display','none','important');}var pending='';function applyEmail(attempt){var input=document.querySelector('input[name=\"contact_email\"],input#email');if(!input){if(attempt<40){window.setTimeout(function(){applyEmail(attempt+1);},150);}return;}if(pending&&input.value!==pending){var setter=Object.getOwnPropertyDescriptor(window.HTMLInputElement.prototype,'value').set;setter.call(input,pending);input.dispatchEvent(new Event('input',{bubbles:true}));input.dispatchEvent(new Event('change',{bubbles:true}));}}function addUploadPreview(){var files=Array.isArray(window.aipCheckoutFiles)?window.aipCheckoutFiles:[];if(!files.length)return;var hosts=document.querySelectorAll('.woocommerce-checkout-review-order-table .product-name,.wc-block-components-order-summary-item__description');hosts.forEach(function(host){if(host.querySelector('.aip-checkout-upload-preview'))return;var wrap=document.createElement('div');wrap.className='aip-checkout-upload-preview';var title=document.createElement('strong');title.textContent='Your uploaded files ('+files.length+')';wrap.appendChild(title);var grid=document.createElement('div');grid.className='aip-checkout-upload-grid';files.forEach(function(file,index){var link=document.createElement('a');link.className='aip-checkout-upload-card';link.href=String(file.url||'#');if(file.url){link.target='_blank';link.rel='noopener';}var isImage=String(file.type||'').indexOf('image/')===0||/\\.(jpe?g|png|webp)$/i.test(String(file.name||''));if(isImage&&file.url){var image=document.createElement('img');image.src=file.url;image.alt=String(file.name||('Upload '+(index+1)));link.appendChild(image);}else{var icon=document.createElement('div');icon.className='aip-checkout-upload-file';icon.textContent='↥';link.appendChild(icon);}var name=document.createElement('span');name.textContent=String(file.name||('Upload '+(index+1)));link.appendChild(name);grid.appendChild(link);});wrap.appendChild(grid);host.appendChild(wrap);});}function revealCheckoutError(){var error=document.querySelector('.wc-block-components-validation-error,.wc-block-components-notice-banner.is-error,[role=\"alert\"]');if(error){error.scrollIntoView({behavior:'smooth',block:'center'});}}document.addEventListener('click',function(event){if(event.target.closest&&event.target.closest('.wc-block-components-checkout-place-order-button')){window.setTimeout(revealCheckoutError,700);window.setTimeout(revealCheckoutError,1800);}});window.addEventListener('message',function(event){if(event.origin!==window.location.origin||!event.data||event.data.type!=='aipCheckoutEmail'){return;}pending=String(event.data.email||'').trim();if(!/^[^@\\s]+@[^@\\s]+\\.[^@\\s]+$/.test(pending)){pending='';return;}applyEmail(0);});var observer=new MutationObserver(addUploadPreview);observer.observe(document.documentElement,{childList:true,subtree:true});addUploadPreview();window.setTimeout(addUploadPreview,500);window.setTimeout(addUploadPreview,1500);if(window.parent!==window){window.parent.postMessage({type:'aipCheckoutReady'},window.location.origin);}})();",
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
		if ( 'billing_email' !== $input || ! WC()->session ) {
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
		return 'Your content is ready!' . ( $order_num ? ' (Order #' . $order_num . ')' : '' );
	}

	public static function custom_completed_email_heading( $heading, $order ) {
		return 'Your content is ready!';
	}

	public static function customize_email_gettext( $translated_text, $text, $domain ) {
		if ( 'woocommerce' === $domain ) {
			if ( 'Good things are heading your way!' === $text || 'Your order is complete' === $text ) {
				return 'Your content is ready!';
			}
			if ( 'We have finished processing your order.' === $text ) {
				return 'Your Style by REii shoppable video is complete and ready for download.';
			}
			if ( "Here's a reminder of what you've ordered:" === $text ) {
				return 'Here is a summary of your completed order:';
			}
			if ( 'Your order from %s is on its way!' === $text ) {
				return 'Your content from %s is ready!';
			}
		}
		return $translated_text;
	}

	public static function add_item_thumbnail_to_confirmation( $item_name, $item, $is_visible ) {
		$thumbs = array();
		$order  = is_callable( array( $item, 'get_order' ) ) ? $item->get_order() : false;

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
			$thumb_html = '<div class="aip-confirmation-thumbs" style="display:flex; gap:8px; margin:10px 0 6px; align-items:center;">';
			foreach ( array_slice( $thumbs, 0, 4 ) as $t_url ) {
				$thumb_html .= '<img src="' . esc_url( $t_url ) . '" alt="Product preview" style="width:60px; height:72px; object-fit:cover; border-radius:8px; border:1px solid #e2dafb; box-shadow:0 2px 8px rgba(0,0,0,0.06); background:#fff;" onerror="this.style.display=\'none\'">';
			}
			$thumb_html .= '</div>';
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
			$details[] = array( 'key' => 'Feature add-on', 'value' => wc_clean( $catalog[ $addon ]['label'] . ' (+$' . $catalog[ $addon ]['price'] . ')' ) );
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
			$item->add_meta_data( 'Feature add-on', $catalog[ $addon ]['label'] . ' (+$' . $catalog[ $addon ]['price'] . ')', true );
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
		if ( ! $order->get_billing_email() && ! empty( $intake['email'] ) ) {
			$order->set_billing_email( $intake['email'] );
		}
		$order->update_meta_data( '_aip_intake_email', isset( $intake['email'] ) ? $intake['email'] : '' );
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
				<p class="aip-project-kicker">YOUR CONTENT PROJECTS</p>
				<h2>Everything in one place.</h2>
				<p>Track your order, review its progress, and download your finished content when it is ready.</p>
				<a class="aip-project-button" href="<?php echo esc_url( home_url( '/on-model-content/#submit-project' ) ); ?>">Start a new order &rarr;</a>
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
