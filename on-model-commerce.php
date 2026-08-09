<?php
/**
 * Plugin Name: On-Model Commerce
 * Description: Customer-facing WooCommerce project dashboard and production statuses for On-Model Content.
 * Version: 0.4.4
 * Author: Tech by Leon
 * Requires Plugins: woocommerce
 */

defined( 'ABSPATH' ) || exit;

final class AIP_On_Model_Commerce {
	const VERSION     = '0.4.4';
	const PRODUCT_SKU = 'on-model-content-order';
	const FORM_TITLE  = 'On-Model Content Order Form';

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_order_statuses' ) );
		add_action( 'wp', array( __CLASS__, 'replace_account_dashboard' ) );
		add_filter( 'wc_order_statuses', array( __CLASS__, 'order_status_labels' ) );
		add_filter( 'woocommerce_account_menu_items', array( __CLASS__, 'account_menu' ) );
		add_filter( 'woocommerce_account_dashboard', '__return_empty_string', 1 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'account_styles' ) );
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
		add_action( 'woocommerce_checkout_create_order_line_item', array( __CLASS__, 'copy_intake_to_order_item' ), 10, 4 );
		add_filter( 'woocommerce_order_item_get_formatted_meta_data', array( __CLASS__, 'filter_order_item_display_meta' ), 10, 2 );
		add_filter( 'woocommerce_order_item_name', array( __CLASS__, 'add_item_thumbnail_to_confirmation' ), 10, 3 );
		add_action( 'woocommerce_checkout_create_order', array( __CLASS__, 'apply_intake_to_order' ), 10, 2 );
		add_action( 'rest_api_init', array( __CLASS__, 'register_order_api' ) );
		add_action( 'woocommerce_order_details_after_order_table', array( __CLASS__, 'delivery_preview' ) );
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
		$submitted_at = $order->get_meta( '_aip_intake_submitted_at' );
		$created_at   = $order->get_date_created() ? $order->get_date_created()->date( DATE_ATOM ) : null;
		return array(
			'id'            => $order->get_id(),
			'number'        => $order->get_order_number(),
			'status'        => $order->get_status(),
			'status_label'  => wc_get_order_status_name( $order->get_status() ),
			'created_at'    => $created_at,
			'submitted_at'  => $submitted_at ?: $created_at,
			'customer_name' => trim( $order->get_formatted_billing_full_name() ) ?: 'Customer',
			'email'         => $order->get_billing_email(),
			'total'         => html_entity_decode( wp_strip_all_tags( $order->get_formatted_order_total() ), ENT_QUOTES, 'UTF-8' ),
			'reference'     => $reference,
			'method'        => $method,
			'notes'         => $notes,
			'uploaded_files' => array_slice( $uploaded_files, 0, 4 ),
			'items'         => $items,
			'deliverables'  => $order->get_meta( '_aip_deliverables' ),
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

		$order->update_meta_data( '_aip_deliverables', array( 'images' => $images, 'videos' => $videos, 'delivered_at' => current_time( 'mysql', true ) ) );

		// 1. Convert deliverables to WooCommerce Downloadable Files
		$downloadable_files = array();
		foreach ( $images as $idx => $url ) {
			$file_id = md5( 'image_' . $idx . '_' . $url );
			$downloadable_files[ $file_id ] = array(
				'id'   => $file_id,
				'name' => 'AI Try-On Image ' . ( $idx + 1 ),
				'file' => $url,
			);
		}
		foreach ( $videos as $idx => $url ) {
			$file_id = md5( 'video_' . $idx . '_' . $url );
			$downloadable_files[ $file_id ] = array(
				'id'   => $file_id,
				'name' => 'AI Try-On Video ' . ( $idx + 1 ),
				'file' => $url,
			);
		}

		// 2. Attach downloadable files to product & item & grant WooCommerce permissions
		foreach ( $order->get_items() as $item_id => $item ) {
			if ( is_a( $item, 'WC_Order_Item_Product' ) ) {
				$product = $item->get_product();
				$wc_downloads = array();
				foreach ( $downloadable_files as $file_info ) {
					$download = new WC_Product_Download();
					$download->set_id( $file_info['id'] );
					$download->set_name( $file_info['name'] );
					$download->set_file( $file_info['file'] );
					$wc_downloads[] = $download;
				}
				if ( $product ) {
					if ( ! $product->is_downloadable() ) {
						$product->set_downloadable( true );
					}
					$product->set_downloads( $wc_downloads );
					$product->save();
				}

				// Set downloads on the order line item itself
				$item->set_downloads( $wc_downloads );
				$item->save();

				// Grant WooCommerce Downloadable Product Permission explicitly
				foreach ( $downloadable_files as $file_info ) {
					if ( function_exists( 'wc_downloadable_add_permission' ) ) {
						wc_downloadable_add_permission(
							$file_info['id'],
							$product ? $product->get_id() : 0,
							$order,
							$item->get_quantity()
						);
					}
				}
			}
		}

		$order->save();

		// 3. Grant WooCommerce Downloadable Product Permissions to customer's account
		if ( method_exists( $order, 'grant_download_permissions' ) ) {
			$order->grant_download_permissions( true );
		}

		// 4. Update status to completed and trigger WooCommerce Customer Completed Order email
		$raw_status    = ! empty( $json['status'] ) ? $json['status'] : $request->get_param( 'status' );
		$target_status = sanitize_key( $raw_status ?: 'completed' );
		$order->update_status( $target_status, 'Submitted deliverables attached to customer downloads and sent to client.', true );

		if ( class_exists( 'WC_Emails' ) ) {
			$mailer = WC()->mailer();
			$mails  = $mailer->get_emails();
			if ( ! empty( $mails['WC_Email_Customer_Completed_Order'] ) ) {
				$mails['WC_Email_Customer_Completed_Order']->trigger( $order->get_id(), $order );
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
			<p>Review and download the finished image and video below.</p>
			<div class="aip-delivery-grid">
				<?php foreach ( (array) $delivery['images'] as $url ) : ?>
					<a href="<?php echo esc_url( $url ); ?>" download><img src="<?php echo esc_url( $url ); ?>" alt="Generated on-model preview"></a>
				<?php endforeach; ?>
				<?php foreach ( (array) $delivery['videos'] as $url ) : ?>
					<video controls playsinline preload="metadata" src="<?php echo esc_url( $url ); ?>"></video>
				<?php endforeach; ?>
			</div>
		</section>
		<?php
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
		if ( ! class_exists( 'WC_Product_Simple' ) || self::service_product() ) {
			return;
		}

		$product = new WC_Product_Simple();
		$product->set_name( 'On-Model Content Package' );
		$product->set_slug( 'on-model-content-package' );
		$product->set_status( 'draft' );
		$product->set_catalog_visibility( 'hidden' );
		$product->set_virtual( true );
		$product->set_sold_individually( true );
		$product->set_sku( self::PRODUCT_SKU );
		$product->set_description( 'On-model product content created from an Amazon listing, ASIN, or customer-supplied product files.' );
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
		<div class="notice notice-warning"><p><strong>On-Model checkout needs a price.</strong> Set the package price and publish the hidden service product to enable the intake-to-checkout flow. <a href="<?php echo esc_url( $url ); ?>">Configure service product</a>.</p></div>
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
		$intake = $cart_item['aip_intake'];
		if ( ! empty( $intake['method'] ) ) {
			$details[] = array( 'key' => 'Product source', 'value' => wc_clean( $intake['method'] ) );
		}
	public static function format_reference_for_display( $ref ) {
		if ( empty( $ref ) ) {
			return '';
		}
		$ref = trim( $ref );
		if ( preg_match( '/([A-Z0-9]{10})/i', $ref, $matches ) ) {
			return 'ASIN: ' . strtoupper( $matches[1] );
		}
		if ( function_exists( 'mb_strimwidth' ) ) {
			return mb_strimwidth( $ref, 0, 45, '…' );
		}
		return ( strlen( $ref ) > 45 ) ? substr( $ref, 0, 42 ) . '...' : $ref;
	}

	public static function filter_order_item_display_meta( $formatted_meta, $item ) {
		foreach ( $formatted_meta as $key => $meta ) {
			if ( isset( $meta->key ) && 'Amazon link / ASIN' === $meta->key ) {
				$formatted_meta[ $key ]->display_value = self::format_reference_for_display( $meta->value );
			}
		}
		return $formatted_meta;
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

		$ref = $item->get_meta( '_aip_raw_reference' ) ?: $item->get_meta( 'Amazon link / ASIN' );
		if ( preg_match( '/([A-Z0-9]{10})/i', (string) $ref, $matches ) ) {
			$asin = strtoupper( $matches[1] );
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
}

add_action( 'plugins_loaded', array( 'AIP_On_Model_Commerce', 'init' ) );
