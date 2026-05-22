<?php
/**
 * WooCommerce Checkout Blocks support for PayIsland.
 *
 * @package PayIsland_WooCommerce
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registers PayIsland as a WooCommerce Blocks payment method.
 */
class PayIsland_Blocks_Support extends \Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType {
	/**
	 * Payment method name.
	 *
	 * @var string
	 */
	protected $name = 'payisland';

	/**
	 * Gateway settings.
	 *
	 * @var array<string, mixed>
	 */
	protected $settings = array();

	/**
	 * Initialize settings from the existing classic gateway.
	 *
	 * @return void
	 */
	public function initialize() {
		$this->settings = get_option( 'woocommerce_payisland_settings', array() );

		if ( ! is_array( $this->settings ) ) {
			$this->settings = array();
		}
	}

	/**
	 * Check if the PayIsland gateway is enabled.
	 *
	 * @return bool
	 */
	public function is_active() {
		$settings = $this->get_settings();

		return isset( $settings['enabled'] ) && 'yes' === $settings['enabled'];
	}

	/**
	 * Register and return frontend script handles.
	 *
	 * @return array<int, string>
	 */
	public function get_payment_method_script_handles() {
		$handle = 'payisland-blocks';

		wp_register_script(
			$handle,
			PAYISLAND_WOOCOMMERCE_URL . 'assets/js/payisland-blocks.js',
			array( 'wc-blocks-registry', 'wc-settings', 'wp-element', 'wp-html-entities' ),
			PAYISLAND_WOOCOMMERCE_VERSION,
			true
		);

		return array( $handle );
	}

	/**
	 * Register and return admin editor script handles.
	 *
	 * @return array<int, string>
	 */
	public function get_payment_method_script_handles_for_admin() {
		return $this->get_payment_method_script_handles();
	}

	/**
	 * Provide data consumed by the Checkout Blocks frontend script.
	 *
	 * @return array<string, mixed>
	 */
	public function get_payment_method_data() {
		$settings    = $this->get_settings();
		$title       = isset( $settings['title'] ) && '' !== $settings['title'] ? $settings['title'] : __( 'PayIsland', 'payisland-woocommerce' );
		$description = isset( $settings['description'] ) && '' !== $settings['description'] ? $settings['description'] : __( 'Pay securely using PayIsland.', 'payisland-woocommerce' );
		$icon        = '';

		if ( file_exists( PAYISLAND_WOOCOMMERCE_PATH . 'assets/icon.png' ) ) {
			$icon = PAYISLAND_WOOCOMMERCE_URL . 'assets/icon.png';
		}

		return array(
			'title'       => wp_strip_all_tags( $title ),
			'description' => wp_kses_post( $description ),
			'supports'    => array( 'products' ),
			'icon'        => esc_url_raw( $icon ),
			'enabled'     => $this->is_active(),
		);
	}

	/**
	 * Get initialized settings.
	 *
	 * @return array<string, mixed>
	 */
	private function get_settings() {
		if ( empty( $this->settings ) ) {
			$this->initialize();
		}

		return $this->settings;
	}
}
