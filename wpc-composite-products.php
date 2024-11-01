<?php
/*
Plugin Name: WPC Composite Products for WooCommerce
Plugin URI: https://wpclever.net/
Description: WPC Composite Products provide a powerful kit-building solution for WooCommerce store.
Version: 7.3.8
Author: WPClever
Author URI: https://wpclever.net
Text Domain: wpc-composite-products
Domain Path: /languages/
Requires Plugins: woocommerce
Requires at least: 4.0
Tested up to: 6.7
WC requires at least: 3.0
WC tested up to: 9.3
*/

defined( 'ABSPATH' ) || exit;

! defined( 'WOOCO_VERSION' ) && define( 'WOOCO_VERSION', '7.3.8' );
! defined( 'WOOCO_LITE' ) && define( 'WOOCO_LITE', __FILE__ );
! defined( 'WOOCO_FILE' ) && define( 'WOOCO_FILE', __FILE__ );
! defined( 'WOOCO_URI' ) && define( 'WOOCO_URI', plugin_dir_url( __FILE__ ) );
! defined( 'WOOCO_DIR' ) && define( 'WOOCO_DIR', plugin_dir_path( __FILE__ ) );
! defined( 'WOOCO_DOCS' ) && define( 'WOOCO_DOCS', 'https://doc.wpclever.net/wooco/' );
! defined( 'WOOCO_SUPPORT' ) && define( 'WOOCO_SUPPORT', 'https://wpclever.net/support?utm_source=support&utm_medium=wooco&utm_campaign=wporg' );
! defined( 'WOOCO_REVIEWS' ) && define( 'WOOCO_REVIEWS', 'https://wordpress.org/support/plugin/wpc-composite-products/reviews/?filter=5' );
! defined( 'WOOCO_CHANGELOG' ) && define( 'WOOCO_CHANGELOG', 'https://wordpress.org/plugins/wpc-composite-products/#developers' );
! defined( 'WOOCO_DISCUSSION' ) && define( 'WOOCO_DISCUSSION', 'https://wordpress.org/support/plugin/wpc-composite-products' );
! defined( 'WPC_URI' ) && define( 'WPC_URI', WOOCO_URI );

include 'includes/dashboard/wpc-dashboard.php';
include 'includes/kit/wpc-kit.php';
include 'includes/hpos.php';

if ( ! function_exists( 'wooco_init' ) ) {
	add_action( 'plugins_loaded', 'wooco_init', 11 );

	function wooco_init() {
		// load text-domain
		load_plugin_textdomain( 'wpc-composite-products', false, basename( __DIR__ ) . '/languages/' );

		if ( ! function_exists( 'WC' ) || ! version_compare( WC()->version, '3.0', '>=' ) ) {
			add_action( 'admin_notices', 'wooco_notice_wc' );

			return null;
		}

		include_once 'includes/class-product.php';
		include_once 'includes/class-wooco.php';
	}
}

if ( ! function_exists( 'wooco_notice_wc' ) ) {
	function wooco_notice_wc() {
		?>
        <div class="error">
            <p><strong>WPC Composite Products</strong> requires WooCommerce version 3.0 or greater.</p>
        </div>
		<?php
	}
}
