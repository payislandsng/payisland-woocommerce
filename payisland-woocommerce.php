<?php
/**
 * Plugin Name: PayIsland for WooCommerce
 * Plugin URI: https://payislands.com
 * Description: Accept payments on WooCommerce using PayIsland.
 * Version: 0.1.0
 * Author: PayIsland
 * Author URI: https://payislands.com
 * License: MIT
 * Text Domain: payisland-woocommerce
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 *
 * @package PayIsland_WooCommerce
 */

defined( 'ABSPATH' ) || exit;

define( 'PAYISLAND_WOOCOMMERCE_VERSION', '0.1.0' );
define( 'PAYISLAND_WOOCOMMERCE_FILE', __FILE__ );
define( 'PAYISLAND_WOOCOMMERCE_PATH', plugin_dir_path( __FILE__ ) );
define( 'PAYISLAND_WOOCOMMERCE_URL', plugin_dir_url( __FILE__ ) );

/**
 * Load PayIsland after WooCommerce has loaded.
 *
 * @return void
 */
function payisland_woocommerce_init() {
	load_plugin_textdomain( 'payisland-woocommerce', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

	if ( ! class_exists( 'WooCommerce' ) || ! class_exists( 'WC_Payment_Gateway' ) ) {
		add_action( 'admin_notices', 'payisland_woocommerce_missing_woocommerce_notice' );
		return;
	}

	require_once PAYISLAND_WOOCOMMERCE_PATH . 'includes/class-payisland-utils.php';
	require_once PAYISLAND_WOOCOMMERCE_PATH . 'includes/class-payisland-api-client.php';
	require_once PAYISLAND_WOOCOMMERCE_PATH . 'includes/class-payisland-webhook-handler.php';
	require_once PAYISLAND_WOOCOMMERCE_PATH . 'includes/class-wc-gateway-payisland.php';

	add_filter( 'woocommerce_payment_gateways', 'payisland_woocommerce_add_gateway' );

	$handler = new PayIsland_Webhook_Handler();
	add_action( 'woocommerce_api_payisland_callback', array( $handler, 'handle_callback' ) );
	add_action( 'woocommerce_api_payisland_webhook', array( $handler, 'handle_webhook' ) );
}
add_action( 'plugins_loaded', 'payisland_woocommerce_init', 11 );

/**
 * Show an admin notice when WooCommerce is unavailable.
 *
 * @return void
 */
function payisland_woocommerce_missing_woocommerce_notice() {
	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}

	echo '<div class="notice notice-error"><p>';
	echo esc_html__( 'PayIsland for WooCommerce requires WooCommerce to be installed and active.', 'payisland-woocommerce' );
	echo '</p></div>';
}

/**
 * Register the PayIsland payment gateway with WooCommerce.
 *
 * @param array<int, string> $gateways Payment gateway classes.
 * @return array<int, string>
 */
function payisland_woocommerce_add_gateway( $gateways ) {
	$gateways[] = 'WC_Gateway_PayIsland';
	return $gateways;
}

/**
 * Declare compatibility with WooCommerce custom order tables.
 *
 * @return void
 */
function payisland_woocommerce_declare_hpos_compatibility() {
	if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
	}
}
add_action( 'before_woocommerce_init', 'payisland_woocommerce_declare_hpos_compatibility' );
