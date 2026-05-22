<?php
/**
 * WooCommerce PayIsland gateway.
 *
 * @package PayIsland_WooCommerce
 */

defined( 'ABSPATH' ) || exit;

/**
 * PayIsland WooCommerce payment gateway.
 */
class WC_Gateway_PayIsland extends WC_Payment_Gateway {
	/**
	 * Secret API key.
	 *
	 * @var string
	 */
	protected $secret_key;

	/**
	 * Payment item ID.
	 *
	 * @var string
	 */
	protected $payment_item_id;

	/**
	 * Payment channel.
	 *
	 * @var string
	 */
	protected $payment_channel;

	/**
	 * Success order status setting.
	 *
	 * @var string
	 */
	protected $order_status_after_success;

	/**
	 * Whether debug logging is enabled.
	 *
	 * @var bool
	 */
	protected $debug_logging;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->id                 = 'payisland';
		$this->method_title       = __( 'PayIsland', 'payisland-woocommerce' );
		$this->method_description = __( 'Accept payments on WooCommerce using PayIsland. Sandbox or live mode is determined by the PayIsland API key.', 'payisland-woocommerce' );
		$this->has_fields         = false;
		$this->supports           = array( 'products' );

		$icon_path = PAYISLAND_WOOCOMMERCE_PATH . 'assets/icon.png';
		if ( file_exists( $icon_path ) ) {
			$this->icon = PAYISLAND_WOOCOMMERCE_URL . 'assets/icon.png';
		}

		$this->init_form_fields();
		$this->init_settings();

		$this->title                      = $this->get_option( 'title', __( 'Pay with PayIsland', 'payisland-woocommerce' ) );
		$this->description                = $this->get_option( 'description', __( 'Pay securely using PayIsland.', 'payisland-woocommerce' ) );
		$this->enabled                    = $this->get_option( 'enabled', 'no' );
		$this->secret_key                 = $this->get_option( 'secret_key', '' );
		$this->payment_item_id            = $this->get_option( 'payment_item_id', '' );
		$this->payment_channel            = $this->get_option( 'payment_channel', 'card' );
		$this->order_status_after_success = $this->get_option( 'order_status_after_success', 'default' );
		$this->debug_logging              = 'yes' === $this->get_option( 'debug_logging', 'no' );

		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
	}

	/**
	 * Initialize gateway settings fields.
	 *
	 * @return void
	 */
	public function init_form_fields() {
		$this->form_fields = array(
			'enabled'                    => array(
				'title'   => __( 'Enable/Disable', 'payisland-woocommerce' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable PayIsland payments', 'payisland-woocommerce' ),
				'default' => 'no',
			),
			'title'                      => array(
				'title'       => __( 'Title', 'payisland-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'This controls the payment method title customers see during checkout.', 'payisland-woocommerce' ),
				'default'     => __( 'Pay with PayIsland', 'payisland-woocommerce' ),
				'desc_tip'    => true,
			),
			'description'                => array(
				'title'       => __( 'Description', 'payisland-woocommerce' ),
				'type'        => 'textarea',
				'description' => __( 'This controls the payment method description customers see during checkout.', 'payisland-woocommerce' ),
				'default'     => __( 'Pay securely using PayIsland.', 'payisland-woocommerce' ),
				'desc_tip'    => true,
			),
			'secret_key'                 => array(
				'title'       => __( 'Secret Key', 'payisland-woocommerce' ),
				'type'        => 'password',
				'description' => __( 'Enter your PayIsland secret key. Sandbox or live mode is controlled by the API key supplied by PayIsland.', 'payisland-woocommerce' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'payment_item_id'            => array(
				'title'       => __( 'Payment Item ID', 'payisland-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'Required PayIsland payment item ID for incoming transactions.', 'payisland-woocommerce' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'payment_channel'            => array(
				'title'       => __( 'Payment Channel', 'payisland-woocommerce' ),
				'type'        => 'select',
				'description' => __( 'Choose the PayIsland payment channel to request at checkout.', 'payisland-woocommerce' ),
				'default'     => 'card',
				'options'     => array(
					'card'          => __( 'Card', 'payisland-woocommerce' ),
					'bank'          => __( 'Bank', 'payisland-woocommerce' ),
					'bank-transfer' => __( 'Bank transfer', 'payisland-woocommerce' ),
				),
				'desc_tip'    => true,
			),
			'order_status_after_success' => array(
				'title'       => __( 'Order Status After Success', 'payisland-woocommerce' ),
				'type'        => 'select',
				'description' => __( 'Use WooCommerce default payment-complete behavior, or force a specific status after a successful PayIsland verification.', 'payisland-woocommerce' ),
				'default'     => 'default',
				'options'     => $this->get_success_status_options(),
				'desc_tip'    => true,
			),
			'webhook_secret'             => array(
				'title'       => __( 'Webhook Secret', 'payisland-woocommerce' ),
				'type'        => 'password',
				'description' => __( 'Optional. If PayIsland provides a webhook signing secret, enter it to verify X-PayIsland-Signature HMAC SHA256 signatures.', 'payisland-woocommerce' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'debug_logging'              => array(
				'title'       => __( 'Debug Logging', 'payisland-woocommerce' ),
				'type'        => 'checkbox',
				'label'       => __( 'Enable WooCommerce logs for PayIsland requests and callbacks', 'payisland-woocommerce' ),
				'description' => __( 'Logs are available under WooCommerce > Status > Logs. Secret keys are never logged.', 'payisland-woocommerce' ),
				'default'     => 'no',
			),
		);
	}

	/**
	 * Validate settings before saving.
	 *
	 * @return bool
	 */
	public function process_admin_options() {
		$saved = parent::process_admin_options();

		$settings = PayIsland_Utils::get_gateway_settings();
		if ( isset( $settings['payment_channel'] ) && ! in_array( $settings['payment_channel'], array( 'card', 'bank', 'bank-transfer' ), true ) ) {
			$settings['payment_channel'] = 'card';
			update_option( 'woocommerce_payisland_settings', $settings );
		}

		return $saved;
	}

	/**
	 * Start a PayIsland payment.
	 *
	 * @param int $order_id WooCommerce order ID.
	 * @return array<string, string>
	 * @throws Exception When payment initialization fails.
	 */
	public function process_payment( $order_id ) {
		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			throw new Exception( esc_html__( 'Unable to find WooCommerce order.', 'payisland-woocommerce' ) );
		}

		if ( '' === trim( $this->secret_key ) || '' === trim( $this->payment_item_id ) ) {
			$order->add_order_note( __( 'PayIsland payment could not start because gateway credentials are incomplete.', 'payisland-woocommerce' ) );
			throw new Exception( esc_html__( 'PayIsland is not fully configured. Please contact the store owner.', 'payisland-woocommerce' ) );
		}

		$reference = PayIsland_Utils::generate_transaction_reference( $order );
		$payload   = array(
			'callback_url'          => PayIsland_Utils::wc_api_url( 'payisland_callback' ),
			'payment_item_id'       => sanitize_text_field( $this->payment_item_id ),
			'transaction_reference' => $reference,
			'channel'               => sanitize_text_field( $this->payment_channel ),
			'amount'                => (float) $order->get_total(),
			'customer_info'         => array(
				'first_name' => $order->get_billing_first_name(),
				'last_name'  => $order->get_billing_last_name(),
				'email'      => $order->get_billing_email(),
				'phone'      => $order->get_billing_phone(),
			),
		);

		$client   = PayIsland_Utils::get_api_client( $this->settings );
		$response = $client->initialize_transaction( $payload );

		if ( empty( $response['success'] ) ) {
			$message = ! empty( $response['message'] ) ? $response['message'] : __( 'Unable to initialize PayIsland payment.', 'payisland-woocommerce' );

			$order->add_order_note(
				sprintf(
					/* translators: %s: Error message. */
					__( 'PayIsland payment initialization failed: %s', 'payisland-woocommerce' ),
					sanitize_text_field( (string) $message )
				)
			);

			throw new Exception( esc_html__( 'Unable to start PayIsland payment. Please try again.', 'payisland-woocommerce' ) );
		}

		$authorization_url = PayIsland_Utils::extract_authorization_url( $response['body'] );

		if ( '' === $authorization_url ) {
			$order->add_order_note( __( 'PayIsland payment initialization failed: authorization URL was missing.', 'payisland-woocommerce' ) );
			throw new Exception( esc_html__( 'Unable to start PayIsland payment. Please try again.', 'payisland-woocommerce' ) );
		}

		$order->update_meta_data( PayIsland_Utils::META_REFERENCE, $reference );
		$order->update_meta_data( PayIsland_Utils::META_AUTHORIZATION_URL, $authorization_url );
		$order->save();

		$order->add_order_note(
			sprintf(
				/* translators: %s: PayIsland transaction reference. */
				__( 'PayIsland payment initialized. Reference: %s', 'payisland-woocommerce' ),
				$reference
			)
		);

		return array(
			'result'   => 'success',
			'redirect' => $authorization_url,
		);
	}

	/**
	 * Get success status options.
	 *
	 * @return array<string, string>
	 */
	private function get_success_status_options() {
		$options = array(
			'default' => __( 'WooCommerce default', 'payisland-woocommerce' ),
		);

		if ( function_exists( 'wc_get_order_statuses' ) ) {
			foreach ( wc_get_order_statuses() as $status => $label ) {
				$options[ $status ] = $label;
			}
		}

		return $options;
	}
}
