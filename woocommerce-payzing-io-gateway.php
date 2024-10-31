<?php
/**
 * Plugin Name: Uphold payments by Payzing - WooCommerce Gateway
 * Plugin URI: http://www.payzing.io/woocommerce/
 * Description: Start accepting instant global payments with no chargebacks for just 0.25%. Payzing makes it simple to make and receive payments using Uphold.
 * Version: 1.0.0
 * Author: InspiraDigital
 * Author URI: http://www.inspiradigital.co.uk/
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

// Include our Gateway Class and Register Payment Gateway with WooCommerce.
add_action( 'plugins_loaded', 'payzing_io_init', 0 );

/**
 * Initializes Payzing Plugin inside Wordpress/WooCommerce.
 */
function payzing_io_init() {
	if( ! class_exists( 'WC_Payment_Gateway' ) ) return;

	include_once( 'woocommerce-payzing-io-gateway.class.php' );

	add_filter( 'woocommerce_payment_gateways', 'add_payzing_io_gateway' );

	/**
	 * Adds Payzing_IO class to the methods array.
	 * @param array $methods Wordpress methods class array.
	 */
	function add_payzing_io_gateway( $methods ) {
		$methods[] = 'Payzing_IO';
		return $methods;
	}
}


// Add custom action links.
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'payzing_io_action_links' );

/**
 * Adds payzing settings link to plugin page block.
 * @param  array $links Current plugin links.
 * @return array        Updated plugin links.
 */
function payzing_io_action_links( $links ) {
	$plugin_links = array(
		'<a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout&section=payzing_io' ) . '">' . __( 'Settings', 'payzing-io' ) . '</a>',
	);

	return array_merge( $plugin_links, $links );
}
