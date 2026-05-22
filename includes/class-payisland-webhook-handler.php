<?php
/**
 * PayIsland callback and webhook handling.
 *
 * @package PayIsland_WooCommerce
 */

defined( 'ABSPATH' ) || exit;

/**
 * Handles public PayIsland return and webhook endpoints.
 */
class PayIsland_Webhook_Handler {
	/**
	 * Handle customer return callback.
	 *
	 * @return void
	 */
	public function handle_callback() {
		$reference = PayIsland_Utils::extract_reference( wp_unslash( $_GET ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( '' === $reference ) {
			wc_add_notice( __( 'PayIsland payment reference was missing.', 'payisland-woocommerce' ), 'error' );
			wp_safe_redirect( wc_get_checkout_url() );
			exit;
		}

		$order = PayIsland_Utils::find_order_by_reference( $reference );

		if ( ! $order ) {
			wc_add_notice( __( 'We could not find the PayIsland order for this payment.', 'payisland-woocommerce' ), 'error' );
			wp_safe_redirect( wc_get_checkout_url() );
			exit;
		}

		$verification_reference = $this->get_verification_reference( $order, $reference );
		$result                 = $this->verify_and_update_order( $order, $verification_reference, 'callback' );

		if ( 'success' === $result['group'] ) {
			wp_safe_redirect( $order->get_checkout_order_received_url() );
			exit;
		}

		if ( 'pending' === $result['group'] ) {
			wc_add_notice( __( 'Your PayIsland payment is still pending. We will update the order once payment is confirmed.', 'payisland-woocommerce' ), 'notice' );
			wp_safe_redirect( $order->get_checkout_order_received_url() );
			exit;
		}

		wc_add_notice( __( 'Your PayIsland payment was not successful. Please try again.', 'payisland-woocommerce' ), 'error' );
		wp_safe_redirect( $order->is_paid() ? $order->get_checkout_order_received_url() : $order->get_checkout_payment_url() );
		exit;
	}

	/**
	 * Handle PayIsland webhook.
	 *
	 * @return void
	 */
	public function handle_webhook() {
		$raw_body  = file_get_contents( 'php://input' );
		$settings  = PayIsland_Utils::get_gateway_settings();
		$logger    = function_exists( 'wc_get_logger' ) ? wc_get_logger() : null;
		$debug     = PayIsland_Utils::is_debug_enabled( $settings );
		$signature = $this->get_signature_header();

		if ( ! $this->is_valid_signature( $raw_body, $signature, $settings ) ) {
			PayIsland_Utils::log( $logger, 'warning', 'PayIsland webhook rejected because signature verification failed.', $debug );
			wp_send_json_error( array( 'message' => 'Invalid signature.' ), 401 );
		}

		$payload = json_decode( $raw_body, true );

		if ( ! is_array( $payload ) ) {
			wp_send_json_error( array( 'message' => 'Invalid JSON payload.' ), 400 );
		}

		$reference = PayIsland_Utils::extract_reference( $payload );

		if ( '' === $reference ) {
			wp_send_json_error( array( 'message' => 'Missing transaction reference.' ), 400 );
		}

		$order = PayIsland_Utils::find_order_by_reference( $reference );

		if ( ! $order ) {
			wp_send_json_error( array( 'message' => 'Order not found.' ), 404 );
		}

		$verification_reference = $this->get_verification_reference( $order, $reference );
		$result                 = $this->verify_and_update_order( $order, $verification_reference, 'webhook' );

		if ( empty( $result['verified'] ) ) {
			wp_send_json_error( array( 'message' => 'Transaction verification failed.' ), 400 );
		}

		wp_send_json_success(
			array(
				'message' => 'Webhook processed.',
				'status'  => $result['status'],
				'group'   => $result['group'],
			)
		);
	}

	/**
	 * Verify transaction and update the order idempotently.
	 *
	 * @param WC_Order $order WooCommerce order.
	 * @param string   $reference PayIsland transaction reference.
	 * @param string   $source Callback source.
	 * @return array<string, mixed>
	 */
	private function verify_and_update_order( $order, $reference, $source ) {
		$settings = PayIsland_Utils::get_gateway_settings();
		$client   = PayIsland_Utils::get_api_client( $settings );
		$response = $client->verify_transaction( $reference );

		if ( empty( $response['success'] ) ) {
			$order->add_order_note(
				sprintf(
					/* translators: 1: Callback source, 2: PayIsland reference. */
					__( 'PayIsland %1$s verification failed for reference %2$s.', 'payisland-woocommerce' ),
					sanitize_text_field( $source ),
					sanitize_text_field( $reference )
				)
			);

			return array(
				'verified' => false,
				'status'   => '',
				'group'    => 'unknown',
			);
		}

		$status = PayIsland_Utils::extract_payment_status( $response['body'] );
		$group  = PayIsland_Utils::status_group( $status );

		$this->update_order_for_status( $order, $reference, $status, $group, $settings, $source );

		return array(
			'verified' => true,
			'status'   => $status,
			'group'    => $group,
		);
	}

	/**
	 * Update a WooCommerce order according to verified payment status.
	 *
	 * @param WC_Order             $order WooCommerce order.
	 * @param string               $reference PayIsland reference.
	 * @param string               $status Raw normalized status.
	 * @param string               $group Status group.
	 * @param array<string, mixed> $settings Gateway settings.
	 * @param string               $source Callback source.
	 * @return void
	 */
	private function update_order_for_status( $order, $reference, $status, $group, $settings, $source ) {
		$last_status = (string) $order->get_meta( PayIsland_Utils::META_LAST_STATUS );
		$status_note = $status ? $status : 'unknown';

		if ( $last_status !== $status_note ) {
			$order->update_meta_data( PayIsland_Utils::META_LAST_STATUS, $status_note );
			$order->add_order_note(
				sprintf(
					/* translators: 1: Callback source, 2: PayIsland reference, 3: Payment status. */
					__( 'PayIsland %1$s verified reference %2$s with status: %3$s.', 'payisland-woocommerce' ),
					sanitize_text_field( $source ),
					sanitize_text_field( $reference ),
					sanitize_text_field( $status_note )
				)
			);
			$order->save();
		}

		if ( 'success' === $group ) {
			if ( ! $order->is_paid() ) {
				$order->payment_complete( $reference );
			}

			$success_status = PayIsland_Utils::get_success_order_status( $settings );
			if ( 'default' !== $success_status && $order->get_status() !== $success_status ) {
				$order->update_status(
					$success_status,
					__( 'PayIsland payment verified successfully.', 'payisland-woocommerce' )
				);
			}

			return;
		}

		if ( 'pending' === $group ) {
			if ( ! $order->is_paid() && ! $order->has_status( array( 'pending', 'on-hold' ) ) ) {
				$order->update_status( 'on-hold', __( 'PayIsland payment is pending verification.', 'payisland-woocommerce' ) );
			}

			return;
		}

		if ( 'failed' === $group && ! $order->is_paid() && ! $order->has_status( array( 'failed', 'cancelled' ) ) ) {
			$order->update_status( 'failed', __( 'PayIsland payment failed after verification.', 'payisland-woocommerce' ) );
		}
	}

	/**
	 * Get the PayIsland-generated reference to use for verification.
	 *
	 * @param WC_Order $order WooCommerce order.
	 * @param string   $fallback_reference Fallback request reference.
	 * @return string
	 */
	private function get_verification_reference( $order, $fallback_reference ) {
		$payisland_reference = (string) $order->get_meta( PayIsland_Utils::META_REFERENCE );

		if ( '' !== $payisland_reference ) {
			return sanitize_text_field( $payisland_reference );
		}

		return sanitize_text_field( $fallback_reference );
	}

	/**
	 * Read PayIsland signature header.
	 *
	 * @return string
	 */
	private function get_signature_header() {
		if ( isset( $_SERVER['HTTP_X_PAYISLAND_SIGNATURE'] ) ) {
			return sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_PAYISLAND_SIGNATURE'] ) );
		}

		if ( function_exists( 'getallheaders' ) ) {
			$headers = getallheaders();
			foreach ( $headers as $name => $value ) {
				if ( 'x-payisland-signature' === strtolower( $name ) ) {
					return sanitize_text_field( $value );
				}
			}
		}

		return '';
	}

	/**
	 * Validate webhook signature when a webhook secret is configured.
	 *
	 * @param string               $raw_body Raw request body.
	 * @param string               $signature Signature header.
	 * @param array<string, mixed> $settings Gateway settings.
	 * @return bool
	 */
	private function is_valid_signature( $raw_body, $signature, $settings ) {
		$secret = isset( $settings['webhook_secret'] ) ? trim( (string) $settings['webhook_secret'] ) : '';

		if ( '' === $secret ) {
			return true;
		}

		if ( '' === $signature ) {
			return false;
		}

		$expected  = hash_hmac( 'sha256', $raw_body, $secret );
		$signature = preg_replace( '/^sha256=/i', '', trim( $signature ) );

		return hash_equals( $expected, $signature );
	}
}
