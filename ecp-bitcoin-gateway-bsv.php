<?php
defined( 'ABSPATH' ) or die( 'Bitcoin is for all!' );

// add jsfuncs.js
function jsfuncs_init() {
    wp_enqueue_script( 'jsfuncs', plugins_url( '/libs/jsfuncs.js', __FILE__ ));
}
add_action('wp_enqueue_scripts','jsfuncs_init');

// add moneybutton.js
function moneybutton_init() {
    wp_enqueue_script( 'moneybutton', plugins_url( '/libs/moneybutton.js', __FILE__ ));
}
add_action('wp_enqueue_scripts','moneybutton_init');

// init css style
function payment_css() {
  wp_enqueue_style( 'payment-css', plugins_url( '/libs/paymentStyle.css', __FILE__ ) );
}
add_action( 'wp_enqueue_scripts', 'payment_css' );

/*
 * Bitcoin SV payment gateway
 */
class ECP_Bitcoin_SV extends ECP_Bitcoin {

	public function __construct() {
		parent::__construct();
	}

	public function get_payment_method_title() {
		return __( 'Bitcoin SV', 'woocommerce' );
	}

	public function get_gateway_id() {
		return 'bitcoin_' . strtolower( $this->get_bitcoin_variant() );
	}

	public function get_settings_name() {
		return ECP_SETTINGS_NAME . strtolower( $this->get_bitcoin_variant() );
	}

	public function get_bitcoin_variant() {
		return 'bsv';
	}

	public function get_icon_dir() {
		return '/images/checkout-icons/' . strtolower( $this->get_bitcoin_variant() ) . '/';
	}

	public function get_electrum_util() {
		return new ElectrumBSVUtil( $this->settings['electrum_mpk'], $this->settings['starting_index_for_new_btc_addresses'] );
	}

	public function update_order_metadata( $order_id, $ret_info_array ) {
		$bitcoins_address = @$ret_info_array['generated_bitcoin_address'];
		ECP__log_event( __FILE__, __LINE__, '     Generated unique bitcoin ' . $this->get_bitcoin_variant() . " address: '{$bitcoins_address}' for order_id " . $order_id );

		update_post_meta(
			$order_id,       // post id ($order_id)
			'bitcoins_address',  // meta key
			$bitcoins_address  // meta value. If array - will be auto-serialized
		);
	}

	public function get_payment_instructions_description() {
		$payment_instructions_description = '
          <p class="description" style="width:50%;float:left;width:49%;">
            ' . __( 'Specific instructions given to the customer to complete Bitcoins payment.<br />You may change it, but make sure these tags will be present: <b>{{{BITCOINS_AMOUNT}}}</b>, <b>{{{BITCOINS_ADDRESS}}}</b> and <b>{{{EXTRA_INSTRUCTIONS}}}</b> as these tags will be replaced with customer - specific payment details.', 'woocommerce' ) . '
          </p>
          <p class="description" style="width:50%;float:left;width:49%;">
            Payment Instructions, original template (for reference):<br />
            <textarea rows="2" onclick="this.focus();this.select()" readonly="readonly" style="width:100%;background-color:#f1f1f1;height:4em">' . $this->default_payment_instructions() . '</textarea>
          </p>';

		return $payment_instructions_description;
	}

	public function default_payment_instructions() {
		$payment_instructions = '
          <section>
            <div class="row">
              <div class="col"> ' . __( 'Please send your Bitcoin SV payment as follows:', 'woocommerce' ) . '</div>
            </div>
            <div class="row">
              <div class="col"> ' . __( 'Amount', 'woocommerce' ) . ':  <p class="col" style="font-weight: bold;font-size: 120%;"> {{{BITCOINS_AMOUNT}}} BSV </p> </div>
            </div>
            <div class="row">
              <div class="col">   Legacy Address: <p style="overflow-wrap: anywhere; font-weight: bold;font-size: 120%;"> {{{BITCOINS_ADDRESS}}} </p> </div>
            </div>
            <div class="row">
              <div class="col"> QR Code:
                  <p>
                    <a href="{{{BIP_URL}}}{{{BITCOINS_ADDRESS}}}?sv&amount={{{BITCOINS_AMOUNT}}}&message={{{PAYMENT_MESSAGE}}}"> <img src="https://api.qrserver.com/v1/create-qr-code/?color=000000&amp;bgcolor=FFFFFF&amp;data={{{BIP_URL}}}{{{BITCOINS_ADDRESS}}}%3Fsv%26amount%3D{{{BITCOINS_AMOUNT}}}%26message%3D{{{PAYMENT_MESSAGE_URL_SAFE}}}&amp;qzone=1&amp;margin=0&amp;size=180x180&amp;ecc=L" style="vertical-align:middle;border:1px solid #888;" /> </a>
                  </p>
              </div>
            </div>

            <div class="row">
              <div class="col">   Bezahlen mit Moneybutton:
                 <div class="money-button"
                    data-to="{{{BITCOINS_ADDRESS}}}"
                    data-amount="{{{BITCOINS_AMOUNT}}}"
                    data-currency="BSV"
                    data-on-payment="displayHiddenContent"
                    >
                  </div>
              </div>
              <div class="col" id="my-hidden-content"></div>
            </div>
          </section>

            ' . __( 'Please note:', 'woocommerce' ) . '
            <ol class="bpit-instructions">
                <li>' . __( 'The payment method chosen ONLY accepts Bitcoin SV! Bitcoin (legacy/core/segwit/cash) payments will not process and the money will be lost forever!', 'woocommerce' ) . '</li>
                <li>' . __( 'We are not responsible for lost funds if you send anything other than BSV', 'woocommerce' ) . '</li>
                <li>' . __( 'You must make a payment within 1 hour, or your order may be cancelled', 'woocommerce' ) . '</li>
                <li>' . __( 'As soon as your payment is received in full you will receive email confirmation with order delivery details.', 'woocommerce' ) . '</li>
                <li>{{{EXTRA_INSTRUCTIONS}}}</li>
            </ol>';
		return $payment_instructions;
	}

	public function fill_in_instructions( $order, $add_order_note = false ) {
		// Assemble detailed instructions.
		$order_total_in_bsv = get_post_meta( $order->get_id(), 'order_total_in_bsv', true ); // set single to true to receive properly unserialized array
		$bitcoins_address   = get_post_meta( $order->get_id(), 'bitcoins_address', true ); // set single to true to receive properly unserialized

		$payment_message = urlencode( get_bloginfo( 'name' ) . ' Order:' . $order->get_order_number() );

		$bip_url = "bitcoin:";

		$instructions = $this->instructions;
		$instructions = str_replace( '{{{BITCOINS_AMOUNT}}}', $order_total_in_bsv, $instructions );
		$instructions = str_replace( '{{{BITCOINS_ADDRESS}}}', $bitcoins_address, $instructions );
		$instructions = str_replace( '{{{PAYMENT_MESSAGE}}}', $payment_message, $instructions );
		$instructions = str_replace( '{{{BIP_URL}}}', $bip_url, $instructions );

		// we need to double urlencode because it needs to be urlencoded for the generated qr code and the get request
		// for the qr code also needs it urlencoded
		$instructions = str_replace( '{{{PAYMENT_MESSAGE_URL_SAFE}}}', urlencode( $payment_message ), $instructions );
		$instructions =
			str_replace(
				'{{{EXTRA_INSTRUCTIONS}}}',
				$this->instructions_multi_payment_str,
				$instructions
			);
		if ( $add_order_note ) {
			$order->add_order_note( __( "Order instructions: price=&#3647;{$order_total_in_bsv}, incoming account:{$bitcoins_address}", 'woocommerce' ) );
		}

		return $instructions;
	}
}
