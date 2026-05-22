<?php
/**
 * PayIsland API client.
 *
 * @package PayIsland_WooCommerce
 */

defined( 'ABSPATH' ) || exit;

/**
 * Minimal PayIsland API client using WordPress HTTP APIs.
 */
class PayIsland_API_Client {
	/**
	 * Secret API key.
	 *
	 * @var string
	 */
	private $secret_key;

	/**
	 * API base URL.
	 *
	 * @var string
	 */
	private $base_url;

	/**
	 * WooCommerce logger.
	 *
	 * @var WC_Logger|null
	 */
	private $logger;

	/**
	 * Whether debug logging is enabled.
	 *
	 * @var bool
	 */
	private $debug_enabled;

	/**
	 * Constructor.
	 *
	 * @param string         $secret_key API secret key.
	 * @param string         $base_url API base URL.
	 * @param WC_Logger|null $logger Optional logger.
	 * @param bool           $debug_enabled Whether debug logging is enabled.
	 */
	public function __construct( $secret_key, $base_url = 'https://ags.payislands.com', $logger = null, $debug_enabled = false ) {
		$this->secret_key    = trim( (string) $secret_key );
		$this->base_url      = untrailingslashit( $base_url );
		$this->logger        = $logger;
		$this->debug_enabled = (bool) $debug_enabled;
	}

	/**
	 * Initialize an incoming transaction.
	 *
	 * @param array<string, mixed> $payload Transaction payload.
	 * @return array<string, mixed>
	 */
	public function initialize_transaction( array $payload ) {
		$this->log_initialize_payload( $payload );

		return $this->request(
			'POST',
			'/api/v1/transactions/in/initialize',
			$payload
		);
	}

	/**
	 * Verify a transaction by reference.
	 *
	 * @param string $reference Transaction reference.
	 * @return array<string, mixed>
	 */
	public function verify_transaction( $reference ) {
		$reference = rawurlencode( sanitize_text_field( (string) $reference ) );

		return $this->request(
			'GET',
			'/api/v1/transactions/in/check-transaction-status/' . $reference
		);
	}

	/**
	 * Run an API request.
	 *
	 * @param string               $method HTTP method.
	 * @param string               $path API path.
	 * @param array<string, mixed> $payload Optional payload.
	 * @return array<string, mixed>
	 */
	private function request( $method, $path, array $payload = array() ) {
		if ( '' === $this->secret_key ) {
			return array(
				'success'     => false,
				'status_code' => 0,
				'body'        => array(),
				'message'     => __( 'PayIsland secret key is not configured.', 'payisland-woocommerce' ),
			);
		}

		$url  = $this->base_url . $path;
		$args = array(
			'timeout' => 30,
			'headers' => array(
				'Authorization' => 'Bearer ' . $this->secret_key,
				'Content-Type'  => 'application/json',
				'Accept'        => 'application/json',
				'User-Agent'    => 'payisland-woocommerce',
			),
		);

		if ( 'POST' === $method ) {
			$args['body'] = wp_json_encode( $payload );
		}

		PayIsland_Utils::log(
			$this->logger,
			'debug',
			sprintf( 'PayIsland API request: %s %s', $method, $path ),
			$this->debug_enabled
		);

		$response = 'POST' === $method ? wp_remote_post( $url, $args ) : wp_remote_get( $url, $args );

		if ( is_wp_error( $response ) ) {
			$message = $response->get_error_message();

			PayIsland_Utils::log(
				$this->logger,
				'error',
				sprintf( 'PayIsland API request failed: %s', $message ),
				$this->debug_enabled
			);

			return array(
				'success'     => false,
				'status_code' => 0,
				'body'        => array(),
				'message'     => $message,
			);
		}

		$status_code = absint( wp_remote_retrieve_response_code( $response ) );
		$raw_body    = wp_remote_retrieve_body( $response );
		$body        = json_decode( $raw_body, true );

		if ( ! is_array( $body ) ) {
			$body = array();
		}

		if ( $status_code < 200 || $status_code >= 300 ) {
			$message = PayIsland_Utils::array_get_first(
				$body,
				array( 'message', 'error', 'data.message' ),
				__( 'PayIsland API returned an unsuccessful response.', 'payisland-woocommerce' )
			);
			$message = sanitize_text_field( (string) $message );

			PayIsland_Utils::log(
				$this->logger,
				'error',
				sprintf( 'PayIsland API non-2xx response: HTTP %d', $status_code ),
				$this->debug_enabled
			);

			PayIsland_Utils::log(
				$this->logger,
				'error',
				sprintf( 'PayIsland API error message: %s', $message ),
				$this->debug_enabled
			);

			PayIsland_Utils::log(
				$this->logger,
				'error',
				sprintf( 'PayIsland API response body: %s', $this->format_response_body_for_log( $raw_body, $body ) ),
				$this->debug_enabled
			);

			return array(
				'success'     => false,
				'status_code' => $status_code,
				'body'        => $body,
				'message'     => $message,
			);
		}

		return array(
			'success'     => true,
			'status_code' => $status_code,
			'body'        => $body,
			'message'     => '',
		);
	}

	/**
	 * Log the sanitized transaction initialization payload when debug logging is enabled.
	 *
	 * @param array<string, mixed> $payload Transaction payload.
	 * @return void
	 */
	private function log_initialize_payload( array $payload ) {
		$customer_info = isset( $payload['customer_info'] ) && is_array( $payload['customer_info'] ) ? $payload['customer_info'] : array();

		$sanitized_payload = array(
			'callback_url'          => isset( $payload['callback_url'] ) ? esc_url_raw( (string) $payload['callback_url'] ) : '',
			'payment_item_id'       => isset( $payload['payment_item_id'] ) ? sanitize_text_field( (string) $payload['payment_item_id'] ) : '',
			'transaction_reference' => isset( $payload['transaction_reference'] ) ? sanitize_text_field( (string) $payload['transaction_reference'] ) : '',
			'channel'               => isset( $payload['channel'] ) ? sanitize_text_field( (string) $payload['channel'] ) : '',
			'amount'                => isset( $payload['amount'] ) ? sanitize_text_field( (string) $payload['amount'] ) : '',
			'customer_info'         => array(
				'email'        => isset( $customer_info['email'] ) ? sanitize_email( (string) $customer_info['email'] ) : '',
				'phone_number' => isset( $customer_info['phone_number'] ) ? sanitize_text_field( (string) $customer_info['phone_number'] ) : '',
				'first_name'   => isset( $customer_info['first_name'] ) ? sanitize_text_field( (string) $customer_info['first_name'] ) : '',
				'last_name'    => isset( $customer_info['last_name'] ) ? sanitize_text_field( (string) $customer_info['last_name'] ) : '',
			),
		);

		PayIsland_Utils::log(
			$this->logger,
			'debug',
			sprintf( 'PayIsland initialize payload: %s', wp_json_encode( $sanitized_payload ) ),
			$this->debug_enabled
		);
	}

	/**
	 * Format a response body for WooCommerce logs.
	 *
	 * @param string               $raw_body Raw response body.
	 * @param array<string, mixed> $body Decoded response body.
	 * @return string
	 */
	private function format_response_body_for_log( $raw_body, array $body ) {
		$formatted_body = ! empty( $body ) ? wp_json_encode( $this->sanitize_log_data( $body ) ) : sanitize_textarea_field( (string) $raw_body );

		if ( ! is_string( $formatted_body ) ) {
			$formatted_body = '';
		}

		if ( strlen( $formatted_body ) > 5000 ) {
			$formatted_body = substr( $formatted_body, 0, 5000 ) . '...';
		}

		return $formatted_body;
	}

	/**
	 * Sanitize nested API response data for logging.
	 *
	 * @param mixed $data Response data.
	 * @return mixed
	 */
	private function sanitize_log_data( $data ) {
		if ( is_array( $data ) ) {
			$sanitized = array();

			foreach ( $data as $key => $value ) {
				$sanitized[ sanitize_text_field( (string) $key ) ] = $this->sanitize_log_data( $value );
			}

			return $sanitized;
		}

		if ( is_bool( $data ) || is_int( $data ) || is_float( $data ) || null === $data ) {
			return $data;
		}

		return sanitize_text_field( (string) $data );
	}
}
