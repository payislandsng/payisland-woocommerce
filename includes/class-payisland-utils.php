<?php
/**
 * Shared PayIsland helpers.
 *
 * @package PayIsland_WooCommerce
 */

defined( 'ABSPATH' ) || exit;

/**
 * Utility methods for PayIsland gateway behavior.
 */
class PayIsland_Utils {
	const META_REFERENCE         = '_payisland_reference';
	const META_AUTHORIZATION_URL = '_payisland_authorization_url';
	const META_LAST_STATUS       = '_payisland_last_status';

	/**
	 * Generate a merchant-side transaction reference.
	 *
	 * @param WC_Order $order WooCommerce order.
	 * @return string
	 */
	public static function generate_transaction_reference( $order ) {
		return sprintf( 'WC-%d-%d', absint( $order->get_id() ), time() );
	}

	/**
	 * Get PayIsland gateway settings from WooCommerce options.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_gateway_settings() {
		$settings = get_option( 'woocommerce_payisland_settings', array() );

		return is_array( $settings ) ? $settings : array();
	}

	/**
	 * Build a configured API client.
	 *
	 * @param array<string, mixed>|null $settings Gateway settings.
	 * @return PayIsland_API_Client
	 */
	public static function get_api_client( $settings = null ) {
		$settings = is_array( $settings ) ? $settings : self::get_gateway_settings();
		$logger   = function_exists( 'wc_get_logger' ) ? wc_get_logger() : null;

		return new PayIsland_API_Client(
			isset( $settings['secret_key'] ) ? (string) $settings['secret_key'] : '',
			'https://ags.payislands.com',
			$logger,
			self::is_debug_enabled( $settings )
		);
	}

	/**
	 * Check whether debug logging is enabled.
	 *
	 * @param array<string, mixed> $settings Gateway settings.
	 * @return bool
	 */
	public static function is_debug_enabled( $settings ) {
		return isset( $settings['debug_logging'] ) && 'yes' === $settings['debug_logging'];
	}

	/**
	 * Write to WooCommerce logs.
	 *
	 * @param WC_Logger|null $logger Logger instance.
	 * @param string         $level Log level.
	 * @param string         $message Log message.
	 * @param bool           $debug_enabled Whether debug logging is enabled.
	 * @return void
	 */
	public static function log( $logger, $level, $message, $debug_enabled = false ) {
		if ( ! $logger || ! is_callable( array( $logger, 'log' ) ) ) {
			return;
		}

		if ( ! $debug_enabled && in_array( $level, array( 'debug', 'info' ), true ) ) {
			return;
		}

		$logger->log(
			$level,
			$message,
			array(
				'source' => 'payisland-woocommerce',
			)
		);
	}

	/**
	 * Safely read a value from a nested array.
	 *
	 * @param array<string, mixed> $array Source array.
	 * @param array<int, string>   $paths Dot-notated paths to check.
	 * @param mixed                $default Default value.
	 * @return mixed
	 */
	public static function array_get_first( $array, $paths, $default = null ) {
		foreach ( $paths as $path ) {
			$value = self::array_get( $array, $path, null );

			if ( null !== $value && '' !== $value ) {
				return $value;
			}
		}

		return $default;
	}

	/**
	 * Safely read a value from a nested array by dot path.
	 *
	 * @param array<string, mixed> $array Source array.
	 * @param string               $path Dot-notated path.
	 * @param mixed                $default Default value.
	 * @return mixed
	 */
	public static function array_get( $array, $path, $default = null ) {
		$value = $array;

		foreach ( explode( '.', $path ) as $segment ) {
			if ( ! is_array( $value ) || ! array_key_exists( $segment, $value ) ) {
				return $default;
			}

			$value = $value[ $segment ];
		}

		return $value;
	}

	/**
	 * Normalize PayIsland status text.
	 *
	 * @param mixed $status Raw status.
	 * @return string
	 */
	public static function normalize_status( $status ) {
		return strtolower( sanitize_text_field( (string) $status ) );
	}

	/**
	 * Categorize PayIsland payment status.
	 *
	 * @param mixed $status Raw status.
	 * @return string One of success, pending, failed, unknown.
	 */
	public static function status_group( $status ) {
		$status = self::normalize_status( $status );

		if ( in_array( $status, array( 'paid', 'successful', 'success' ), true ) ) {
			return 'success';
		}

		if ( in_array( $status, array( 'pending', 'unpaid' ), true ) ) {
			return 'pending';
		}

		if ( in_array( $status, array( 'failed', 'cancelled', 'canceled', 'expired', 'reversed' ), true ) ) {
			return 'failed';
		}

		return 'unknown';
	}

	/**
	 * Extract a transaction reference from request data or payload data.
	 *
	 * @param array<string, mixed> $data Request or payload data.
	 * @return string
	 */
	public static function extract_reference( $data ) {
		$reference = self::array_get_first(
			$data,
			array(
				'reference',
				'transaction_reference',
				'trxref',
				'data.reference',
				'data.transaction_reference',
				'data.trxref',
				'event.reference',
				'event.transaction_reference',
			),
			''
		);

		return sanitize_text_field( (string) $reference );
	}

	/**
	 * Extract payment status from a verification response.
	 *
	 * @param array<string, mixed> $response Verification response.
	 * @return string
	 */
	public static function extract_payment_status( $response ) {
		$status = self::array_get_first(
			$response,
			array(
				'payment_status',
				'status',
				'transaction_status',
				'data.payment_status',
				'data.status',
				'data.transaction_status',
				'data.payment.status',
			),
			''
		);

		return self::normalize_status( $status );
	}

	/**
	 * Extract an authorization URL from an initialize response.
	 *
	 * @param array<string, mixed> $response Initialize response.
	 * @return string
	 */
	public static function extract_authorization_url( $response ) {
		$url = self::array_get_first(
			$response,
			array(
				'authorization_url',
				'payment_url',
				'checkout_url',
				'redirect_url',
				'data.authorization_url',
				'data.payment_url',
				'data.checkout_url',
				'data.redirect_url',
			),
			''
		);

		return esc_url_raw( (string) $url );
	}

	/**
	 * Find a WooCommerce order by PayIsland reference.
	 *
	 * @param string $reference PayIsland reference.
	 * @return WC_Order|null
	 */
	public static function find_order_by_reference( $reference ) {
		$reference = sanitize_text_field( $reference );

		if ( '' === $reference || ! function_exists( 'wc_get_orders' ) ) {
			return null;
		}

		$orders = wc_get_orders(
			array(
				'limit'      => 1,
				'meta_key'   => self::META_REFERENCE, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value' => $reference, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				'return'     => 'objects',
			)
		);

		if ( empty( $orders ) || ! $orders[0] instanceof WC_Order ) {
			return null;
		}

		return $orders[0];
	}

	/**
	 * Get configured success order status.
	 *
	 * @param array<string, mixed> $settings Gateway settings.
	 * @return string
	 */
	public static function get_success_order_status( $settings ) {
		$status = isset( $settings['order_status_after_success'] ) ? sanitize_key( $settings['order_status_after_success'] ) : 'default';

		if ( 'default' === $status || '' === $status ) {
			return 'default';
		}

		return 0 === strpos( $status, 'wc-' ) ? substr( $status, 3 ) : $status;
	}

	/**
	 * Build a WooCommerce API endpoint URL.
	 *
	 * @param string $endpoint Endpoint name.
	 * @return string
	 */
	public static function wc_api_url( $endpoint ) {
		return WC()->api_request_url( $endpoint );
	}
}
