<?php
/**
 * Plugin Name: درگاه پرداخت آسان پرداخت (آپ) برای ووکامرس
 * Plugin URI:  https://asanpardakht.ir/
 * Description: درگاه پرداخت اینترنتی آسان پرداخت پرشین (آپ) برای ووکامرس، مبتنی بر REST API.
 * Version:     1.0.1
 * Text Domain: asan-pardakht-woocommerce
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

defined( 'ABSPATH' ) || exit;

define( 'APGW_VERSION', '1.0.1' );
define( 'APGW_FILE', __FILE__ );
define( 'APGW_DIR', plugin_dir_path( __FILE__ ) );

/**
 * Declare compatibility with WooCommerce features.
 */
function apgw_declare_compatibility() {
	if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, true );
	}
}
add_action( 'before_woocommerce_init', 'apgw_declare_compatibility' );

/**
 * Load the gateway after WooCommerce has loaded.
 */
function apgw_init() {
	if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
		return;
	}

	require_once APGW_DIR . 'includes/class-apgw-api-client.php';
	require_once APGW_DIR . 'includes/class-apgw-wc-gateway-asan-pardakht.php';
}
add_action( 'plugins_loaded', 'apgw_init', 20 );

/**
 * Register integration with the WooCommerce Checkout block.
 */
function apgw_register_blocks_support() {
	if ( ! class_exists( '\Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType' ) ) {
		return;
	}

	require_once APGW_DIR . 'includes/class-apgw-blocks-support.php';

	add_action(
		'woocommerce_blocks_payment_method_type_registration',
		function ( $payment_method_registry ) {
			$payment_method_registry->register( new APGW_Blocks_Support() );
		}
	);
}
add_action( 'woocommerce_blocks_loaded', 'apgw_register_blocks_support' );

/**
 * Register the gateway with WooCommerce.
 *
 * @param array $gateways Existing payment gateways.
 * @return array
 */
function apgw_register_gateway( $gateways ) {
	$gateways[] = 'APGW_WC_Gateway_Asan_Pardakht';
	return $gateways;
}
add_filter( 'woocommerce_payment_gateways', 'apgw_register_gateway' );

/**
 * Show an actionable dependency notice to administrators.
 */
function apgw_missing_woocommerce_notice() {
	if ( class_exists( 'WooCommerce' ) || ! current_user_can( 'activate_plugins' ) ) {
		return;
	}

	echo '<div class="notice notice-error"><p>';
	echo esc_html__( 'برای استفاده از درگاه آسان پرداخت (آپ)، افزونه ووکامرس باید نصب و فعال باشد.', 'asan-pardakht-woocommerce' );
	echo '</p></div>';
}
add_action( 'admin_notices', 'apgw_missing_woocommerce_notice' );

