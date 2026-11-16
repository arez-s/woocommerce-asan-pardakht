<?php
/**
 * Asan Pardakht REST API client.
 *
 * @package Asan_Pardakht_Gateway
 */

defined( 'ABSPATH' ) || exit;

class APGW_API_Client {

	const API_BASE_URL = 'https://ipgrest.asanpardakht.ir/';

	/** @var string */
	private $username;

	/** @var string */
	private $password;

	/** @var int */
	private $merchant_configuration_id;

	/** @var callable|null */
	private $logger;

	/**
	 * @param string        $username Merchant API username.
	 * @param string        $password Merchant API password.
	 * @param int           $merchant_configuration_id Merchant configuration ID.
	 * @param callable|null $logger Optional logger callback.
	 */
	public function __construct( $username, $password, $merchant_configuration_id, $logger = null ) {
		$this->username                  = (string) $username;
		$this->password                  = (string) $password;
		$this->merchant_configuration_id = (int) $merchant_configuration_id;
		$this->logger                    = is_callable( $logger ) ? $logger : null;
	}

	/**
	 * Request a payment token.
	 *
	 * @param int    $invoice_id Unique local invoice number.
	 * @param int    $amount_in_rials Amount in rials.
	 * @param string $callback_url Callback URL without the invoice query argument.
	 * @return array
	 */
	public function token( $invoice_id, $amount_in_rials, $callback_url ) {
		$callback_url = add_query_arg( 'invoice', (string) $invoice_id, $callback_url );

		try {
			$timezone = new DateTimeZone( 'Asia/Tehran' );
			$now      = new DateTimeImmutable( 'now', $timezone );
		} catch ( Exception $exception ) {
			$now = new DateTimeImmutable( 'now' );
		}

		return $this->request(
			'POST',
			'v1/Token',
			array(
				'serviceTypeId'          => 1,
				'merchantConfigurationId' => $this->merchant_configuration_id,
				'localInvoiceId'         => (int) $invoice_id,
				'amountInRials'          => (int) $amount_in_rials,
				'localDate'              => $now->format( 'Ymd His' ),
				'callbackURL'            => esc_url_raw( $callback_url ),
				'paymentId'              => 0,
				'additionalData'         => '',
			)
		);
	}

	/**
	 * Get the transaction result by local invoice number.
	 *
	 * @param int $invoice_id Local invoice number.
	 * @return array
	 */
	public function transaction_result( $invoice_id ) {
		$endpoint = add_query_arg(
			array(
				'merchantConfigurationId' => $this->merchant_configuration_id,
				'localInvoiceId'          => (int) $invoice_id,
			),
			'v1/TranResult'
		);

		return $this->request( 'GET', $endpoint );
	}

	/**
	 * Verify a transaction.
	 *
	 * @param string $transaction_id PayGate transaction ID.
	 * @return array
	 */
	public function verify( $transaction_id ) {
		return $this->transaction_action( 'v1/Verify', $transaction_id );
	}

	/**
	 * Settle a verified transaction.
	 *
	 * @param string $transaction_id PayGate transaction ID.
	 * @return array
	 */
	public function settlement( $transaction_id ) {
		return $this->transaction_action( 'v1/Settlement', $transaction_id );
	}

	/**
	 * Reverse a transaction. Exposed for controlled recovery; not called automatically.
	 *
	 * @param string $transaction_id PayGate transaction ID.
	 * @return array
	 */
	public function reverse( $transaction_id ) {
		return $this->transaction_action( 'v1/Reverse', $transaction_id );
	}

	/**
	 * Cancel a transaction. Exposed for controlled recovery; not called automatically.
	 *
	 * @param string $transaction_id PayGate transaction ID.
	 * @return array
	 */
	public function cancel( $transaction_id ) {
		return $this->transaction_action( 'v1/Cancel', $transaction_id );
	}

	/**
	 * Run an API action against a transaction.
	 *
	 * @param string $endpoint API endpoint.
	 * @param string $transaction_id PayGate transaction ID.
	 * @return array
	 */
	private function transaction_action( $endpoint, $transaction_id ) {
		return $this->request(
			'POST',
			$endpoint,
			array(
				'merchantConfigurationId' => $this->merchant_configuration_id,
				'payGateTranId'           => (string) $transaction_id,
			)
		);
	}

	/**
	 * Send an HTTP request through the WordPress HTTP API.
	 *
	 * @param string     $method HTTP method.
	 * @param string     $endpoint Relative API endpoint.
	 * @param array|null $data JSON request payload.
	 * @return array{success:bool,code:int,body:mixed,raw:string,message:string}
	 */
	private function request( $method, $endpoint, $data = null ) {
		$url  = self::API_BASE_URL . ltrim( $endpoint, '/' );
		$args = array(
			'method'      => strtoupper( $method ),
			'timeout'     => 30,
			'redirection' => 0,
			'sslverify'   => true,
			'headers'     => array(
				'Accept'       => 'application/json',
				'Usr'          => $this->username,
				'Pwd'          => $this->password,
				'Content-Type' => 'application/json; charset=utf-8',
			),
		);

		if ( null !== $data ) {
			$args['body'] = wp_json_encode( $data );
		}

		$this->log(
			'API request',
			array(
				'method'   => $args['method'],
				'endpoint' => $endpoint,
			)
		);

		$response = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			$result = array(
				'success' => false,
				'code'    => 0,
				'body'    => null,
				'raw'     => '',
				'message' => $response->get_error_message(),
			);
			$this->log( 'API transport error', array( 'message' => $result['message'] ), 'error' );
			return $result;
		}

		$code    = (int) wp_remote_retrieve_response_code( $response );
		$raw     = (string) wp_remote_retrieve_body( $response );
		$decoded = json_decode( $raw, true );
		$body    = ( JSON_ERROR_NONE === json_last_error() ) ? $decoded : trim( $raw );
		$success = ( 200 === $code );
		$message = $success ? '' : $this->extract_error_message( $body, $raw );

		$result = array(
			'success' => $success,
			'code'    => $code,
			'body'    => $body,
			'raw'     => $raw,
			'message' => $message,
		);

		$this->log(
			'API response',
			array(
				'endpoint' => $endpoint,
				'code'     => $code,
				'body'     => $this->safe_log_body( $body ),
			),
			$success ? 'info' : 'error'
		);

		return $result;
	}

	/**
	 * Extract a human-readable API error without exposing credentials.
	 *
	 * @param mixed  $body Parsed response body.
	 * @param string $raw Raw response body.
	 * @return string
	 */
	private function extract_error_message( $body, $raw ) {
		if ( is_array( $body ) ) {
			foreach ( array( 'message', 'Message', 'error', 'Error', 'title' ) as $key ) {
				if ( isset( $body[ $key ] ) && is_scalar( $body[ $key ] ) ) {
					return sanitize_text_field( (string) $body[ $key ] );
				}
			}
		}

		return sanitize_text_field( wp_strip_all_tags( $raw ) );
	}

	/**
	 * Keep logs bounded and avoid dumping arbitrary nested data.
	 *
	 * @param mixed $body Parsed response body.
	 * @return mixed
	 */
	private function safe_log_body( $body ) {
		$encoded = wp_json_encode( $body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		if ( false === $encoded ) {
			return '[unserializable]';
		}

		return function_exists( 'mb_substr' ) ? mb_substr( $encoded, 0, 2000 ) : substr( $encoded, 0, 2000 );
	}

	/**
	 * @param string $message Log message.
	 * @param array  $context Log context.
	 * @param string $level Log level.
	 */
	private function log( $message, $context = array(), $level = 'info' ) {
		if ( $this->logger ) {
			call_user_func( $this->logger, $level, $message, $context );
		}
	}
}

