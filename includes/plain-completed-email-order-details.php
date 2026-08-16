<?php
/**
 * REii Customer Completed Order Plain Delivery Details Template.
 */

defined( 'ABSPATH' ) || exit;

do_action( 'woocommerce_email_before_order_table', $order, $sent_to_admin, $plain_text, $email );
do_action( 'woocommerce_email_after_order_table', $order, $sent_to_admin, $plain_text, $email );
