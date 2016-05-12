<?php
/**
 * WooCommerce Garan24 Gateway
 *
 * @link http://www.woothemes.com/products/Garan24/
 * @since 0.3
 *
 * @package WC_Gateway_garan24
 *
 * @wordpress-plugin
 * Plugin Name:     WooCommerce Garan24 Gateway
 * Plugin URI:      http://woothemes.com/woocommerce
 * Description:     Extends WooCommerce. Provides a <a href="http://www.garan24.ru" target="_blank">Garan24</a> gateway for WooCommerce.
 * Version:         2.1.4
 * Author:          WooThemes
 * Author URI:      http://woothemes.com/
 * Developer:       Krokedil
 * Developer URI:   http://garan24.ru/
 * Text Domain:     woocommerce-gateway-garan24
 * Domain Path:     /languages
 * Copyright:       © 2009-2015 WooThemes.
 * License:         GNU General Public License v3.0
 * License URI:     http://www.gnu.org/licenses/gpl-3.0.html
 */

 if ( ! defined( 'ABSPATH' ) ) {
 	//exit; // Exit if accessed directly
 }

 // Include our Gateway Class and Register Payment Gateway with WooCommerce
 
 function garan24_init(){
	 // If the parent WC_Payment_Gateway class doesn't exist
	// it means WooCommerce is not installed on the site
	// so do nothing
	if ( ! class_exists( 'WC_Payment_Gateway' ) ) return;

	// If we made it this far, then include our Gateway Class
	include_once( 'woocommerce-garan24.php' );
	//include_once( 'woocommerce-garan24-creditcard.php' );
	//include_once( 'woocommerce-garan24-partpay.php' );

	// Now that we have successfully included our class,
	// Lets add it too WooCommerce
	add_filter( 'woocommerce_payment_gateways', 'add_garan24_gateway' );
	function add_garan24_gateway( $methods ) {
		$methods[] = 'WC_Gateway_garan24';
		//$methods[] = 'WC_Gateway_garan24_creditcard';
		//$methods[] = 'WC_Gateway_garan24_partpay';
		return $methods;
	}
 }
add_action( 'plugins_loaded', 'garan24_init', 0 );

// Add custom action links

 function garan24_action_links( $links ) {
	 $plugin_links = array(
		'<a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout' ) . '">' . __( 'Settings', 'garan24' ) . '</a>',
	);

	// Merge our new link with the default ones
	return array_merge( $plugin_links, $links );
 }

 add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'garan24_action_links' );

?>
