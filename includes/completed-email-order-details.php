<?php
/**
 * REii Customer Completed Order Delivery Details Template.
 *
 * Suppresses the order summary table while executing delivery links
 * and create another video prompt actions.
 */

defined( 'ABSPATH' ) || exit;

do_action( 'woocommerce_email_before_order_table', $order, $sent_to_admin, $plain_text, $email );
do_action( 'woocommerce_email_after_order_table', $order, $sent_to_admin, $plain_text, $email );
