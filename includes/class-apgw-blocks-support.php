<?php
/**
 * WooCommerce Blocks integration.
 *
 * @package Asan_Pardakht_Gateway
 */

defined( 'ABSPATH' ) || exit;

class APGW_Blocks_Support extends \Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType {

	/** @var string */
	protected $name = 'asan_pardakht';

	/**
	 * Load gateway settings.
	 */
	public function initialize() {
		$this->settings = get_option( 'woocommerce_' . $this->name . '_settings', array() );
	}

	/**
	 * @return bool
	 */
	public function is_active() {
		$gateways = WC()->payment_gateways()->payment_gateways();
		return isset( $gateways[ $this->name ] ) && $gateways[ $this->name ]->is_available();
	}

	/**
	 * @return string[]
	 */
	public function get_payment_method_script_handles() {
		$handle = 'apgw-blocks';
		wp_register_script(
			$handle,
			plugins_url( 'assets/js/blocks.js', APGW_FILE ),
			array( 'wc-blocks-registry', 'wc-settings', 'wp-element', 'wp-html-entities' ),
			APGW_VERSION,
			true
		);

		return array( $handle );
	}

	/**
	 * @return array
	 */
	public function get_payment_method_data() {
		return array(
			'title'       => $this->get_setting( 'title', __( 'پرداخت اینترنتی آپ', 'asan-pardakht-woocommerce' ) ),
			'description' => $this->get_setting( 'description', __( 'پرداخت امن با کلیه کارت‌های عضو شتاب از طریق درگاه آپ.', 'asan-pardakht-woocommerce' ) ),
			'supports'    => array( 'products' ),
		);
	}
}

