<?php
/**
 * @class Payzing_IO
 * @extends WC_Payment_Gateway
 * @version 1.0.0
 * @package Payzing_IO
 * @author  InspiraDigital <[jedv@inspiradigital.co.uk]>
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * Payzing_IO Class.
 */
class Payzing_IO extends WC_Payment_Gateway {
	/**
	 * Constructor for the gateway.
	 */
	function __construct() {
		$this->id = 'payzing_io';

		$this->icon = true; //false;
		$this->icon_url = 'https://api.payzing.io/payzing-graphic.png';

		$this->has_fields = false;

		$this->method_title = 'Payzing.io';

		$this->method_description = 'Payzing.io Payment Gateway.';

		$this->init_form_fields();
		$this->init_settings();

		$this->enabled = $this->get_option( 'enabled' );
		$this->title = $this->get_option( 'title' );
		$this->description = $this->get_option( 'description' );
		$this->api_user = $this->get_option( 'api_user' );
		$this->api_key = $this->get_option( 'api_key' );
		$this->environment = $this->get_option( 'environment' );

		$this->payzing_endpoint = 'https://api.payzing.io/payment-request/woocommerce';

		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		add_action( 'woocommerce_receipt_payzing_io', array( $this, 'receipt_page' ) );
		add_action( 'woocommerce_thankyou_payzing_io', array( $this, 'thankyou_page' ) );
	}


	/**
	 * Form fields method.
	 */
	public function init_form_fields() {
		$this->form_fields = array(
			'enabled' => array(
				'title'		=> __( 'Enable / Disable', 'payzing-io' ),
				'label'		=> __( 'Enable this payment gateway', 'payzing-io' ),
				'type'		=> 'checkbox',
				'default'	=> 'no',
			),
			'title' => array(
				'title'		=> __( 'Title', 'payzing-io' ),
				'type'		=> 'text',
				'desc_tip'	=> __( 'Payment title the customer will see during the checkout process.', 'payzing-io' ),
				'default'	=> __( 'Uphold', 'payzing-io' ),
			),
			'description' => array(
				'title'		=> __( 'Description', 'payzing-io' ),
				'type'		=> 'textarea',
				'desc_tip'	=> __( 'Payment description the customer will see during the checkout process.', 'payzing-io' ),
				'default'	=> __( 'Pay securely using your Uphold account.', 'payzing-io' ),
				'css'		=> 'max-width:350px;'
			),
			'api_user' => array(
				'title'		=> __( 'Uphold Username/Email', 'payzing-io' ),
				'type'		=> 'text',
				'desc_tip'	=> __( 'This is the username or email you gave when signing up to Uphold.', 'payzing-io' ),
			),
			'api_key' => array(
				'title'		=> __( 'Payzing.io Gateway API Key', 'payzing-io' ),
				'type'		=> 'text',
				'desc_tip'	=> __( 'This is the Gateway API Key provided by Payzing.io (Issued in the Payzing.io Merchant area)', 'payzing-io' ),
			)
		);
	}

	/**
	 * Process payments.
	 * @param integer $order_id The ID of the order.
	 * @return array 			Passes array with checkout payment url.
	 */
	public function process_payment( $order_id ) {
		$order = new WC_Order( $order_id );
		return array(
			'result' => 'success',
			'redirect' => $order->get_checkout_payment_url( true ),
		);
	}

	/**
	 * Echo receipt page.
	 * @param int $order_id The ID of the order.
	 */
	public function receipt_page( $order_id ) {
        if ( isset( $_GET['x_account_id'] ) &&
         isset( $_GET['x_reference'] ) &&
		 isset( $_GET['x_currency'] ) &&
		 isset( $_GET['x_test'] ) &&
		 isset( $_GET['x_amount'] ) &&
		 isset( $_GET['x_result'] ) &&
		 isset( $_GET['x_gateway_reference']) &&
		 isset( $_GET['x_timestamp']) ) {

            global $woocommerce;

            $params = $_GET;
 			ksort( $params );
            $hmac_msg = join( '', array_map( array( $this, 'format_key_value' ), array_keys( $params ), $params ) );
         	$generated_signature = hash_hmac( 'sha256', $hmac_msg, $this->api_key );

            $order_id  = absint( $params['x_reference'] );
            $order     = wc_get_order( $order_id );
            $woocommerce->cart->empty_cart();

            if ( $generated_signature === $params['x_signature'] ) {
                if ( $params['x_result'] != 'completed' ) {
                    $order->update_status( 'failed', __( 'Payment was declined by Payzing.', 'woocommerce' ) );
                } else {
                    $order->payment_complete();
                }
                wp_redirect( $this->get_return_url( $order ) );
                exit();
            }
        } else {
            echo '<p>' . __( 'Thank you for your order, please click the button below to pay with Payzing.', 'payzing-io' ) . '</p>';
			echo $this->generate_payzing_io_form( $order_id );
        }
	}

    /**
     * Get gateway icon.
     * @return mixed
     */
    public function get_icon() {
    	$icon_html = '<img src="' . $this->icon_url . '" width="200" />';
        $icon_html .= '<a target="_blank" href="https://www.payzing.io/" class="about_payzing" style="float:right;line-height:35px;font-size:.83em;" title="' . esc_attr__( 'What is Payzing?', 'woocommerce' ) . '">' . esc_attr__( 'What is Payzing?', 'woocommerce' ) . '</a>';
        return apply_filters( 'woocommerce_gateway_icon', $icon_html, $this->id );
    }

    /**
     * Generate a form field
     * @return string
     */
    public function generate_form_field( $key, $value ) {
        return '<input type="hidden" name="' . $key . '" value="' . $value . '" />';
    }

    /**
     * format a key/value for signature signing
     * @return string
     */
    public function format_key_value( $key, $value ) {
        if( strpos( $key, 'x_' ) !== false && $key !== 'x_signature' ) {
            return $key . $value;
        } else {
            return '';
        }
    }

    /**
     * Generates payment request form.
     * @param  int $order_id The order id.
     * @return string 		 The form HTML.
     */
	public function generate_payzing_io_form( $order_id ) {
		global $woocommerce;
		$order = new WC_Order( $order_id );

		$payzing_args = array(
			'x_account_id' => $this->api_user,
			'x_reference' => $order_id,
			'x_invoice' => '#' . $order_id,
			'x_currency' => get_woocommerce_currency(),
			'x_amount' => $order->order_total,
			'x_url_cancel' => $order->get_cancel_order_url_raw(),
			'x_url_complete' => $order->get_checkout_payment_url( true ),
            'x_shop_name' => get_bloginfo( 'name' )
		);
        ksort( $payzing_args );
        $hmac_msg = join( '', array_map( array( $this, 'format_key_value' ), array_keys( $payzing_args ), $payzing_args ) );
		$payzing_args['x_signature'] = hash_hmac( 'sha256', $hmac_msg, $this->api_key );

        $form_fields = join( '', array_map( array( $this, 'generate_form_field' ), array_keys( $payzing_args ), $payzing_args ) );

		$form = '<form action="' . esc_url( $this->payzing_endpoint ) . '" method="POST" id="payzing_payment_form">
			' . $form_fields . '
			<!-- Button Fallback -->
			<div class="payment_buttons">
			<input type="submit" class="button alt" id="submit_payzing_io_payment_form" value="' . __('Pay with Uphold', 'woocommerce') . '" /> <a class="button cancel" href="' . esc_url( $order->get_cancel_order_url() ) . '">' . __('Cancel order &amp; restore cart', 'woocommerce') . '</a>
			</div>
			</form>';

		return $form;
	}
}
?>
