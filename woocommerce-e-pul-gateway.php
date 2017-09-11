<?php
/*
Plugin Name: E-PUL PayViaEpul - WooCommerce Gateway
Plugin URI: https://www.e-pul.az/
Description: Extends WooCommerce by Adding the E-PUL PayViaEpul Gateway.
Version: 1
Author: PaySys Ltd., E-PUL
*/

// Include our Gateway Class and register Payment Gateway with WooCommerce
add_action( 'plugins_loaded', 'woo_epul_init', 0 );
function woo_epul_init() {
	// If the parent WC_Payment_Gateway class doesn't exist
	// it means WooCommerce is not installed on the site
	// so do nothing
	if ( ! class_exists( 'WC_Payment_Gateway' ) ) return;
	
	// If we made it this far, then include our Gateway Class
	include_once( 'woocommerce-e-pul.php' );

	// Now that we have successfully included our class,
	// Lets add it too WooCommerce
	add_filter( 'woocommerce_payment_gateways', 'woo_add_epul_gateway' );
	function woo_add_epul_gateway( $methods ) {
		$methods[] = 'woo_EPUL';
		return $methods;
	}
}

// Add custom action links
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'woo_epul_action_links' );
function woo_epul_action_links( $links ) {
	$plugin_links = array(
		'<a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout' ) . '">' . __( 'Settings', 'woo-e-pul' ) . '</a>',
	);

	// Merge our new link with the default ones
	return array_merge( $plugin_links, $links );	
}