<?php
/**
 * WooCommerce Asan Pardakht payment gateway.
 *
 * @package Asan_Pardakht_Gateway
 */

defined( 'ABSPATH' ) || exit;

class APGW_WC_Gateway_Asan_Pardakht extends WC_Payment_Gateway {

	/** @var string */
	private $username;

	/** @var string */
	private $password;

	/** @var int */
	private $merchant_configuration_id;

	/** @var string */
	private $currency_mode;

	/** @var bool */
	private $debug;

	/** @var WC_Logger|null */
	private $logger;

	public function __construct() {
		$this->id                 = 'asan_pardakht';
		$this->icon               = '';
		$this->has_fields         = false;
		$this->method_title       = __( 'آسان پرداخت (آپ)', 'asan-pardakht-woocommerce' );
		$this->method_description = __( 'پرداخت امن با درگاه اینترنتی آسان پرداخت پرشین (آپ).', 'asan-pardakht-woocommerce' );
		$this->supports           = array( 'products' );

		$this->init_form_fields();
		$this->init_settings();

		$this->title                     = $this->get_option( 'title', __( 'پرداخت اینترنتی آپ', 'asan-pardakht-woocommerce' ) );
		$this->description               = $this->get_option( 'description', __( 'پرداخت امن با کلیه کارت‌های عضو شتاب از طریق درگاه آپ.', 'asan-pardakht-woocommerce' ) );
		$this->enabled                   = $this->get_option( 'enabled', 'no' );
		$this->username                  = trim( (string) $this->get_option( 'username', '' ) );
		$this->password                  = (string) $this->get_option( 'password', '' );
		$this->merchant_configuration_id = absint( $this->get_option( 'merchant_configuration_id', 0 ) );
		$this->currency_mode             = (string) $this->get_option( 'currency_mode', 'auto' );
		$this->debug                     = 'yes' === $this->get_option( 'debug', 'no' );

		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		add_action( 'woocommerce_api_apgw_redirect', array( $this, 'handle_gateway_redirect' ) );
		add_action( 'woocommerce_api_apgw_callback', array( $this, 'handle_callback' ) );
	}

	/**
	 * Gateway settings.
	 */
	public function init_form_fields() {
		$this->form_fields = array(
			'enabled' => array(
				'title'   => __( 'فعال‌سازی', 'asan-pardakht-woocommerce' ),
				'type'    => 'checkbox',
				'label'   => __( 'فعال‌سازی درگاه آسان پرداخت (آپ)', 'asan-pardakht-woocommerce' ),
				'default' => 'no',
			),
			'title' => array(
				'title'       => __( 'عنوان درگاه', 'asan-pardakht-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'عنوانی که مشتری در صفحه تسویه‌حساب می‌بیند.', 'asan-pardakht-woocommerce' ),
				'default'     => __( 'پرداخت اینترنتی آپ', 'asan-pardakht-woocommerce' ),
				'desc_tip'    => true,
			),
			'description' => array(
				'title'       => __( 'توضیحات', 'asan-pardakht-woocommerce' ),
				'type'        => 'textarea',
				'description' => __( 'متن نمایش‌داده‌شده زیر عنوان درگاه در صفحه تسویه‌حساب.', 'asan-pardakht-woocommerce' ),
				'default'     => __( 'پرداخت امن با کلیه کارت‌های عضو شتاب از طریق درگاه آپ.', 'asan-pardakht-woocommerce' ),
			),
			'username' => array(
				'title'       => __( 'نام کاربری API', 'asan-pardakht-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'نام کاربری REST API دریافتی از آسان پرداخت.', 'asan-pardakht-woocommerce' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'password' => array(
				'title'       => __( 'رمز عبور API', 'asan-pardakht-woocommerce' ),
				'type'        => 'password',
				'description' => __( 'رمز عبور REST API دریافتی از آسان پرداخت.', 'asan-pardakht-woocommerce' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'merchant_configuration_id' => array(
				'title'       => __( 'شناسه پیکربندی پذیرنده', 'asan-pardakht-woocommerce' ),
				'type'        => 'number',
				'description' => __( 'Merchant Configuration ID دریافتی از آسان پرداخت.', 'asan-pardakht-woocommerce' ),
				'default'     => '',
				'desc_tip'    => true,
				'custom_attributes' => array(
					'min'  => '1',
					'step' => '1',
				),
			),
			'currency_mode' => array(
				'title'       => __( 'واحد مبلغ فروشگاه', 'asan-pardakht-woocommerce' ),
				'type'        => 'select',
				'description' => __( 'API آپ مبلغ را به ریال دریافت می‌کند. در حالت خودکار، ارزهای تومان در ۱۰ ضرب می‌شوند.', 'asan-pardakht-woocommerce' ),
				'default'     => 'auto',
				'desc_tip'    => true,
				'options'     => array(
					'auto'  => __( 'تشخیص خودکار از ارز ووکامرس', 'asan-pardakht-woocommerce' ),
					'rial'  => __( 'ریال (بدون تبدیل)', 'asan-pardakht-woocommerce' ),
					'toman' => __( 'تومان (ضرب در ۱۰)', 'asan-pardakht-woocommerce' ),
				),
			),
			'debug' => array(
				'title'       => __( 'گزارش اشکال‌زدایی', 'asan-pardakht-woocommerce' ),
				'type'        => 'checkbox',
				'label'       => __( 'ثبت رویدادها در گزارش‌های ووکامرس', 'asan-pardakht-woocommerce' ),
				'description' => __( 'برای عیب‌یابی موقت فعال کنید. رمز عبور در گزارش ثبت نمی‌شود.', 'asan-pardakht-woocommerce' ),
				'default'     => 'no',
			),
		);
	}

	/**
	 * Add callback information below the standard settings table.
	 */
	public function admin_options() {
		parent::admin_options();
		if ( function_exists( 'WC' ) && WC() ) {
			echo '<p><strong>' . esc_html__( 'نشانی بازگشت (Callback):', 'asan-pardakht-woocommerce' ) . '</strong><br>';
			echo '<code dir="ltr">' . esc_html( WC()->api_request_url( 'apgw_callback' ) ) . '</code></p>';
		}
	}

	/**
	 * Hide the gateway until all required credentials exist.
	 *
	 * @return bool
	 */
	public function is_available() {
		return parent::is_available()
			&& '' !== $this->username
			&& '' !== $this->password
			&& 0 < $this->merchant_configuration_id;
	}

	/**
	 * Create a token and send WooCommerce to the local POST bridge.
	 *
	 * @param int $order_id WooCommerce order ID.
	 * @return array|null
	 */
	public function process_payment( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			wc_add_notice( __( 'سفارش معتبر نیست. لطفاً دوباره تلاش کنید.', 'asan-pardakht-woocommerce' ), 'error' );
			return null;
		}

		$amount_in_rials = $this->get_amount_in_rials( $order );
		if ( $amount_in_rials < 12000 ) {
			wc_add_notice( __( 'حداقل مبلغ قابل پرداخت در درگاه آپ ۱۲٬۰۰۰ ریال است.', 'asan-pardakht-woocommerce' ), 'error' );
			return null;
		}

		$invoice_id  = $this->generate_invoice_id( $order->get_id() );
		$callback_url = add_query_arg(
			array(
				'order_id' => $order->get_id(),
				'key'      => $order->get_order_key(),
			),
			WC()->api_request_url( 'apgw_callback' )
		);

		$result = $this->api()->token( $invoice_id, $amount_in_rials, $callback_url );
		$token  = $this->extract_token( $result['body'] );

		if ( ! $result['success'] || '' === $token ) {
			$this->log( 'error', 'Token request failed', array( 'order_id' => $order->get_id(), 'http_code' => $result['code'] ) );
			wc_add_notice(
				sprintf(
					/* translators: %d HTTP status code. */
					__( 'ارتباط با درگاه آپ برقرار نشد (کد %d). لطفاً دوباره تلاش کنید.', 'asan-pardakht-woocommerce' ),
					(int) $result['code']
				),
				'error'
			);
			return null;
		}

		$order->update_meta_data( '_apgw_invoice_id', (string) $invoice_id );
		$order->update_meta_data( '_apgw_token', $token );
		$order->update_meta_data( '_apgw_amount_rials', $amount_in_rials );
		$order->add_order_note(
			sprintf(
				/* translators: %s Local invoice ID. */
				__( 'توکن درگاه آپ دریافت شد. شماره فاکتور محلی: %s', 'asan-pardakht-woocommerce' ),
				(string) $invoice_id
			)
		);
		$order->save();

		$redirect_url = add_query_arg(
			array(
				'order_id' => $order->get_id(),
				'key'      => $order->get_order_key(),
			),
			WC()->api_request_url( 'apgw_redirect' )
		);

		return array(
			'result'   => 'success',
			'redirect' => $redirect_url,
		);
	}

	/**
	 * Render a secure auto-submitting POST form to the Shaparak endpoint.
	 */
	public function handle_gateway_redirect() {
		$order = $this->get_request_order();
		if ( ! $order ) {
			wp_die( esc_html__( 'درخواست انتقال به درگاه معتبر نیست.', 'asan-pardakht-woocommerce' ), '', array( 'response' => 403 ) );
		}

		$token = (string) $order->get_meta( '_apgw_token', true );
		if ( '' === $token || $order->is_paid() ) {
			wp_safe_redirect( $order->is_paid() ? $this->get_return_url( $order ) : $order->get_checkout_payment_url() );
			exit;
		}

		$mobile = $this->normalize_mobile( $order->get_billing_phone() );
		status_header( 200 );
		nocache_headers();
		?>
<!doctype html>
<html lang="fa" dir="rtl">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title><?php echo esc_html__( 'انتقال به درگاه آپ', 'asan-pardakht-woocommerce' ); ?></title>
</head>
<body>
	<p style="font-family:Tahoma,Arial,sans-serif;text-align:center;margin-top:3rem">
		<?php echo esc_html__( 'در حال انتقال به درگاه امن آسان پرداخت…', 'asan-pardakht-woocommerce' ); ?>
	</p>
	<form id="apgw-form" method="post" action="https://asan.shaparak.ir">
		<input type="hidden" name="RefId" value="<?php echo esc_attr( $token ); ?>">
		<input type="hidden" name="mobileap" value="<?php echo esc_attr( $mobile ); ?>">
		<noscript><p style="text-align:center"><button type="submit"><?php echo esc_html__( 'ورود به درگاه پرداخت', 'asan-pardakht-woocommerce' ); ?></button></p></noscript>
	</form>
	<script>document.getElementById('apgw-form').submit();</script>
</body>
</html>
		<?php
		exit;
	}

	/**
	 * Complete TranResult -> Verify -> Settlement and then complete the order.
	 */
	public function handle_callback() {
		$order = $this->get_request_order();
		if ( ! $order ) {
			wp_die( esc_html__( 'درخواست بازگشت از درگاه معتبر نیست.', 'asan-pardakht-woocommerce' ), '', array( 'response' => 403 ) );
		}

		if ( $order->is_paid() ) {
			wp_safe_redirect( $this->get_return_url( $order ) );
			exit;
		}

		$invoice_id        = $this->request_value( 'invoice' );
		$expected_invoice = (string) $order->get_meta( '_apgw_invoice_id', true );
		if ( '' === $invoice_id || '' === $expected_invoice || ! hash_equals( $expected_invoice, $invoice_id ) ) {
			$this->fail_payment( $order, __( 'شماره فاکتور بازگشتی با سفارش مطابقت ندارد.', 'asan-pardakht-woocommerce' ) );
			return;
		}

		$lock_key = 'apgw_callback_' . $order->get_id();
		if ( get_transient( $lock_key ) ) {
			wp_safe_redirect( $order->get_checkout_payment_url() );
			exit;
		}
		set_transient( $lock_key, 1, MINUTE_IN_SECONDS );

		$result = $this->api()->transaction_result( (int) $invoice_id );
		if ( ! $result['success'] || ! is_array( $result['body'] ) ) {
			delete_transient( $lock_key );
			$this->fail_payment( $order, $this->api_failure_message( __( 'دریافت نتیجه تراکنش', 'asan-pardakht-woocommerce' ), $result ) );
			return;
		}

		$transaction_id = $this->find_transaction_id( $result['body'] );
		if ( '' === $transaction_id ) {
			delete_transient( $lock_key );
			$this->fail_payment( $order, __( 'شناسه تراکنش معتبر از درگاه دریافت نشد.', 'asan-pardakht-woocommerce' ) );
			return;
		}

		$order->update_meta_data( '_apgw_transaction_result', $result['body'] );
		$order->update_meta_data( '_apgw_transaction_id', $transaction_id );
		$order->save();

		$verify = $this->api()->verify( $transaction_id );
		if ( ! $verify['success'] ) {
			delete_transient( $lock_key );
			$this->fail_payment( $order, $this->api_failure_message( __( 'تأیید تراکنش', 'asan-pardakht-woocommerce' ), $verify ) );
			return;
		}

		$order->update_meta_data( '_apgw_verify_response', $verify['body'] );
		$order->save();

		$settlement = $this->api()->settlement( $transaction_id );
		if ( ! $settlement['success'] ) {
			delete_transient( $lock_key );
			$order->delete_meta_data( '_apgw_token' );
			$order->update_status(
				'on-hold',
				$this->api_failure_message( __( 'تسویه تراکنش آپ', 'asan-pardakht-woocommerce' ), $settlement )
			);
			$order->update_meta_data( '_apgw_settlement_pending', 'yes' );
			$order->save();
			wc_add_notice( __( 'پرداخت تأیید شد، اما پاسخ تسویه قطعی نبود. سفارش برای بررسی مدیر نگه داشته شد.', 'asan-pardakht-woocommerce' ), 'notice' );
			wp_safe_redirect( $order->get_checkout_order_received_url() );
			exit;
		}

		$order->update_meta_data( '_apgw_settlement_response', $settlement['body'] );
		$order->delete_meta_data( '_apgw_settlement_pending' );
		$order->delete_meta_data( '_apgw_token' );
		$order->payment_complete( $transaction_id );
		$order->add_order_note(
			sprintf(
				/* translators: %s Gateway transaction ID. */
				__( 'پرداخت با درگاه آپ تأیید و تسویه شد. شناسه تراکنش: %s', 'asan-pardakht-woocommerce' ),
				$transaction_id
			)
		);
		$order->save();
		delete_transient( $lock_key );

		if ( function_exists( 'WC' ) && WC()->cart ) {
			WC()->cart->empty_cart();
		}

		wp_safe_redirect( $this->get_return_url( $order ) );
		exit;
	}

	/**
	 * Resolve and authenticate the order from the request.
	 *
	 * @return WC_Order|false
	 */
	private function get_request_order() {
		$order_id = absint( $this->request_value( 'order_id' ) );
		$key      = $this->request_value( 'key' );
		$order    = wc_get_order( $order_id );

		if ( ! $order || '' === $key || ! hash_equals( (string) $order->get_order_key(), $key ) ) {
			return false;
		}

		return $order;
	}

	/**
	 * Read a scalar request value from GET or POST.
	 *
	 * @param string $key Request key.
	 * @return string
	 */
	private function request_value( $key ) {
		if ( ! isset( $_REQUEST[ $key ] ) || ! is_scalar( $_REQUEST[ $key ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Third-party gateway callback uses order key authentication.
			return '';
		}

		return sanitize_text_field( wp_unslash( $_REQUEST[ $key ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	}

	/**
	 * Convert WooCommerce totals to the rial amount required by the API.
	 *
	 * @param WC_Order $order Order object.
	 * @return int
	 */
	private function get_amount_in_rials( $order ) {
		$multiplier = 1;
		if ( 'toman' === $this->currency_mode ) {
			$multiplier = 10;
		} elseif ( 'auto' === $this->currency_mode ) {
			$currency = strtoupper( (string) $order->get_currency() );
			if ( in_array( $currency, array( 'IRT', 'TMN', 'TOMAN' ), true ) ) {
				$multiplier = 10;
			}
		}

		return (int) round( (float) $order->get_total() * $multiplier );
	}

	/**
	 * Generate a numeric, Int64-safe, per-attempt invoice ID.
	 *
	 * @param int $order_id WooCommerce order ID.
	 * @return int
	 */
	private function generate_invoice_id( $order_id ) {
		$suffix = str_pad( (string) ( absint( $order_id ) % 10000 ), 4, '0', STR_PAD_LEFT );
		$random = str_pad( (string) wp_rand( 0, 99 ), 2, '0', STR_PAD_LEFT );
		return (int) ( time() . $suffix . $random );
	}

	/**
	 * Extract tokens from either the sample's plain body or common JSON wrappers.
	 *
	 * @param mixed $body API response body.
	 * @return string
	 */
	private function extract_token( $body ) {
		if ( is_scalar( $body ) ) {
			return sanitize_text_field( trim( (string) $body, " \t\n\r\0\x0B\"" ) );
		}

		if ( is_array( $body ) ) {
			foreach ( array( 'token', 'Token', 'refId', 'RefId', 'content' ) as $key ) {
				if ( isset( $body[ $key ] ) && is_scalar( $body[ $key ] ) ) {
					return sanitize_text_field( (string) $body[ $key ] );
				}
			}
		}

		return '';
	}

	/**
	 * Locate the PayGate transaction ID despite casing variations.
	 *
	 * @param array $body Transaction result body.
	 * @return string
	 */
	private function find_transaction_id( $body ) {
		foreach ( array( 'payGateTranID', 'payGateTranId', 'PayGateTranID', 'PayGateTranId' ) as $key ) {
			if ( isset( $body[ $key ] ) && is_scalar( $body[ $key ] ) ) {
				return sanitize_text_field( (string) $body[ $key ] );
			}
		}

		return '';
	}

	/**
	 * Normalize Iranian mobile numbers for the optional mobileap field.
	 *
	 * @param string $mobile Raw billing phone.
	 * @return string
	 */
	private function normalize_mobile( $mobile ) {
		$mobile = preg_replace( '/[^0-9+]/', '', (string) $mobile );
		if ( 0 === strpos( $mobile, '+98' ) ) {
			$mobile = '0' . substr( $mobile, 3 );
		} elseif ( 0 === strpos( $mobile, '0098' ) ) {
			$mobile = '0' . substr( $mobile, 4 );
		} elseif ( 0 === strpos( $mobile, '98' ) && 12 === strlen( $mobile ) ) {
			$mobile = '0' . substr( $mobile, 2 );
		}

		return preg_match( '/^09\d{9}$/', $mobile ) ? $mobile : '';
	}

	/**
	 * Mark the order failed and take the customer back to payment.
	 *
	 * @param WC_Order $order Order object.
	 * @param string   $message Failure reason.
	 */
	private function fail_payment( $order, $message ) {
		$this->log( 'error', $message, array( 'order_id' => $order->get_id() ) );
		$order->delete_meta_data( '_apgw_token' );
		$order->update_status( 'failed', $message );
		wc_add_notice( $message, 'error' );
		wp_safe_redirect( $order->get_checkout_payment_url() );
		exit;
	}

	/**
	 * Format a controlled API failure message.
	 *
	 * @param string $stage Human-readable stage.
	 * @param array  $response API response.
	 * @return string
	 */
	private function api_failure_message( $stage, $response ) {
		return sprintf(
			/* translators: 1: payment stage, 2: HTTP status code. */
			__( 'خطا در مرحله «%1$s» (کد پاسخ: %2$d).', 'asan-pardakht-woocommerce' ),
			$stage,
			isset( $response['code'] ) ? (int) $response['code'] : 0
		);
	}

	/**
	 * Build an API client using the current settings.
	 *
	 * @return APGW_API_Client
	 */
	private function api() {
		return new APGW_API_Client(
			$this->username,
			$this->password,
			$this->merchant_configuration_id,
			array( $this, 'log' )
		);
	}

	/**
	 * WooCommerce logger adapter.
	 *
	 * @param string $level Log level.
	 * @param string $message Log message.
	 * @param array  $context Log context.
	 */
	public function log( $level, $message, $context = array() ) {
		if ( ! $this->debug ) {
			return;
		}

		if ( null === $this->logger ) {
			$this->logger = wc_get_logger();
		}

		$context['source'] = 'asan-pardakht-woocommerce';
		$this->logger->log( $level, $message, $context );
	}
}

