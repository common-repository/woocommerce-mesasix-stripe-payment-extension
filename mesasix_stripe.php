<?php
/**
 *
 * @package   Mesasix Woocommerce Stripe Extension
 * @author    Matthew Suan <matthew@mesasix.com.com>
 * @license   GPL-2.0+
 * @link      http://www.mesasix.com
 * @copyright 2014 Mesasix
 *
 *
 * @wordpress-plugin
 * Plugin Name:       Mesasix Woocommerce Stripe Extension
 * Plugin URI:        http://www.mesasix.com
 * Description:       Free Payment Gateway Extension for Woocommerce Using Stripe. 
 * Version:           1.0.0
 * Author:            Matthew Suan
 * Author URI:        http://www.mesasix.com
 * Text Domain:       mesasix
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) )
	die;


add_action( 'plugins_loaded', 'mesasix_gateway_class_init', 0 );

function mesasix_gateway_class_init() {

	//initialize stripe
	if ( !class_exists('Stripe') )
    	require_once( 'includes/lib/stripe-php/lib/Stripe.php' );

	//return if class did not exists - maybe woocommerce is deactivated
	if(!class_exists('WC_Payment_Gateway')) 
		return;

/**
 *
 *
 * @package Mesasix Stripe
 * @author  Matthew Suan <matthew@mesasix.com>
 */
class Mesasix_Stripe extends WC_Payment_Gateway {

	protected $stripe_transaction_id     	= NULL;
	protected $stripe_card_type     		= NULL;
	protected $stripe_last_four		     	= NULL;
    protected $stripe_test_secret_key     	= NULL;
    protected $stripe_test_publishable_key  = NULL;
    protected $stripe_publishable_key       = NULL;
    protected $stripe_secret_key          	= NULL;
    protected $customerOrder	          	= NULL;

	public function __construct() {

		$this->id 					= 'mesasix_stripe';
		$this->has_fields 			= true;
		$this->icon              	= apply_filters( 'mesasix_stripe_checkout_icon', plugins_url( '/assets/img/stripe.png', __FILE__ ) );
		$this->method_title 		= 'Mesasix Stripe';
		$this->method_description 	= 'Mesasix is a leading performance marketing agency in dallas. Stripe - Web and mobile payments made for developers. This plugin is intended for direct payments. Subbscriptions and complex payment schemes will be tackled in the next version of this plugin.';

		$this->init_form_fields();
		$this->init_settings();

		$this->order_button_text 			= __( $this->get_option( 'stripe_button_text' ), 'woocommerce' );
		$this->title 						= $this->get_option( 'title' );
		$this->mesasix_stripe_mode 			= $this->settings['mesasix_stripe_mode'];
		$this->stripe_test_secret_key 		= $this->settings['stripe_test_secret_key'];
		$this->stripe_test_publishable_key 	= $this->settings['stripe_test_publishable_key'];
		$this->stripe_secret_key 			= $this->settings['stripe_secret_key'];
		$this->stripe_publishable_key 		= $this->settings['stripe_publishable_key'];
		$this->stripe_footer_notice 		= $this->settings['stripe_footer_notice'];
		$this->admin_mail_optin 			= $this->settings['admin_mail_optin'];
		$this->admin_mail 					= $this->settings['admin_mail'];
		$this->ajax_text 					= $this->settings['ajax_loading_text'];

		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		add_action('admin_notices', array( $this, 'mesasix_notify_admin' ) );
	}

	/**
	 * Notify admins regarding SSL
	 *
	 *
	 * @since    1.0.0
	 * @param    none
	 * @return   none
	 */
	public function mesasix_notify_admin() {

		if ( $this->mesasix_stripe_mode !== 'yes' && get_option('woocommerce_force_ssl_checkout') == 'no' && $this->enabled == 'yes' ) { ?>

            <div class="error">
            	<p>Mesasix Stripe payment gateway is on live mode which can perform live/real transactions via Stripe.com. However, WooCommerce SSL is disabled rendering your online payment as insecure. Make sure you have SSL enabled before accepting live/real transactions.</p>
            </div>
         	
        <?php 
        } if ( $this->enabled == 'yes' && $this->mesasix_stripe_mode == 'yes' && ( empty( $this->stripe_test_secret_key ) || empty( $this->stripe_test_publishable_key ) ) ) {
        ?>

        	<div class="error">
            	<p>Please provide both Stripe api and publishable test keys to start accepting credit card test payments using Stripe.com.</p>
            </div>

		<?php 
        } if ( $this->enabled == 'yes' && $this->mesasix_stripe_mode !== 'yes' && ( empty( $this->stripe_secret_key ) || empty( $this->stripe_publishable_key ) ) ) {
        ?>

        	<div class="error">
            	<p>Please provide both Stripe api and publishable keys to start accepting live credit card payments using Stripe.com.</p>
            </div>

		<?php 
        }
	}

	/**
	 * 
	 * Processes form fields on the admin section
	 *
	 * @since    1.0.0
	 * @param    none
	 * @return   none
	 */

	public function init_form_fields() {
		$this->form_fields = array(
			'enabled' => array(
				'title' => __( 'Enable', 'woocommerce' ),
				'type' => 'checkbox',
				'label' => __( 'Enable Stripe Payments?', 'woocommerce' ),
				'default' => 'yes'
			),
			'title' => array(
				'title' => __( 'Title', 'woocommerce' ),
				'type' => 'text',
				'description' => __( 'Checkout page title.', 'woocommerce' ),
				'default' => __( 'Mesasix Stripe Payment', 'woocommerce' ),
				'desc_tip'      => true,
			),
			'mesasix_stripe_mode' => array(
				'title'       => __( 'Enable Test Mode?', 'woocommerce' ),
				'type'        => 'checkbox',
				'description' => __( 'For testing purposes, check this box. Uncheck this box when using on a production environment.', 'woocommerce' ),
				'default'     => 'yes'
			),
			'stripe_test_secret_key' => array(
				'title'       => __( 'Stripe Test Secret Key', 'woocommerce' ),
				'type'        => 'text',
				'description' => __( 'Secret token for testing purposes. Please go to stripe.com to get your Test Secret Key.', 'woocommerce' ),
				'default'     => '',
				'placeholder' => __( 'Enter Test Secret Key Here', 'woocommerce' ),
			),
			'stripe_test_publishable_key' => array(
				'title'       => __( 'Stripe Test Publishable Key', 'woocommerce' ),
				'type'        => 'text',
				'description' => __( 'Publishable token for testing purposes. Please go to stripe.com to get your Test Publishable Key.', 'woocommerce' ),
				'default'     => '',
				'placeholder' => __( 'Enter Test Publishable Key Here', 'woocommerce' ),
			),
			'stripe_secret_key' => array(
				'title'       => __( 'Stripe Secret Key', 'woocommerce' ),
				'type'        => 'text',
				'description' => __( 'Secret token. Please go to stripe.com to get your Secret Key.', 'woocommerce' ),
				'default'     => '',
				'placeholder' => __( 'Enter Secret Key Here', 'woocommerce' ),
			),
			'stripe_publishable_key' => array(
				'title'       => __( 'Stripe Publishable Key', 'woocommerce' ),
				'type'        => 'text',
				'description' => __( 'Publishable token. Please go to stripe.com to get your Publishable Key.', 'woocommerce' ),
				'default'     => '',
				'placeholder' => __( 'Enter Publishable Key Here', 'woocommerce' ),
			),
			'stripe_footer_notice' => array(
				'title'       => __( 'Disclaimer or Notice on the checkout bottom.', 'woocommerce' ),
				'type'        => 'textarea',
				'description' => __( 'Description or any text you like to put after the stripe form.', 'woocommerce' ),
				'default'     => 'We do not store credit card details on this server. We are using Stripe<sup>TM</sup> to process payments via a token.',
			),
			'stripe_button_text' => array(
				'title'       => __( 'Button Text', 'woocommerce' ),
				'type'        => 'text',
				'description' => __( 'Button text for the checkout form.', 'woocommerce' ),
				'default'     => 'Pay Now',
			),
			'admin_mail_optin' => array(
				'title'       => __( 'Email Notifications', 'woocommerce' ),
				'type'        => 'checkbox',
				'description' => __( 'Enable email notifications for every Stripe charge (successful or not).', 'woocommerce' ),
				'default'     => 'yes',
			),
			'admin_mail' => array(
				'title'       => __( 'Admin Notifications Email', 'woocommerce' ),
				'type'        => 'email',
				'description' => __( 'Notify this email for every Stripe charge (successful or not).', 'woocommerce' ),
				'default'     => get_option( 'admin_email' ),
			),
			'ajax_loading_text' => array(
				'title'       => __( 'Ajax Loading Text', 'woocommerce' ),
				'type'        => 'text',
				'description' => __( 'Text to display to users while form is being submitted in the background via ajax.', 'woocommerce' ),
				'default'     => 'Processing Credit Card Data.',
			),
		);
	}


	/**
	 * Do form validations
	 *
	 * @since    1.0.0
	 * @return   Boolean
	 * @param    none
	 */
	public function validate_fields() {

		global $woocommerce;

		// get stripe token
		$token = sanitize_text_field( $_POST['stripeToken'] );

		if ( empty( $token ) ) {
			$woocommerce->add_error( __('Payment error: No token was added.', 'woothemes') );
			return false;
		} else {
			return true;
		}
	}


	/**
	 * Do stripe processing. This is used in process_payment() method.
	 *
	 * @since    1.0.0
	 * @return   Boolean
	 * @param    none
	 */
	protected function mesasix_stripe_process() {

		global $woocommerce;

		//check first of we have an order
		if ( !$this->customerOrder || $this->customerOrder == NULL || empty($this->customerOrder) ) {
			
			return false;

		} else {

		    // get api key
		    if ( $this->mesasix_stripe_mode !== 'yes' ) {

		    	Stripe::setApiKey( $this->stripe_secret_key );
		    } else {

		    	Stripe::setApiKey( $this->stripe_test_secret_key );
		    }

		    // begin stripe charge. visit stripe.com for more documentation
		    try {

	            $charge = Stripe_Charge::create( array(
	            	'amount'      	=> (float) $this->customerOrder->get_total() * 100,
	            	'currency'    	=> strtolower( get_woocommerce_currency() ),
	            	'card'        	=> sanitize_text_field( $_POST['stripeToken'] ),
	            	'description'	=> 'THRIVE Institute Supplements Payment by ' . $this->customerOrder->billing_email . ' for Order #' . $this->customerOrder->id,
	            	'metadata' 		=> array(
	            							'user_id'	=> get_current_user_id(),
	            							'order_id'	=> $this->customerOrder->id
	            						)
	            ));

	            //set transaction id property
		        $this->stripe_transaction_id = $charge['id'];
		        $this->stripe_card_type		 = $charge['card']['type'];
		        $this->stripe_last_four		 = $charge['card']['last4'];

		        //attach meta data to order id
		        update_post_meta( $this->customerOrder->id, 'stripe_transaction_id', $this->stripe_transaction_id );
		        update_post_meta( $this->customerOrder->id, 'stripe_card_type', $this->stripe_card_type );
		        update_post_meta( $this->customerOrder->id, 'stripe_last_four', $this->stripe_last_four );

		        //Notify Admin
		        if ( $this->admin_mail_optin == 'yes' ) {

		        	$subject = 'Successful Stripe Charge on Order ID #' . $this->stripe_transaction_id;
		        	$message = 'New Stripe Charge. Details below:' . "\n";
		        	$message .= 'Order ID:' . $this->stripe_transaction_id . "\n";
		        	$message .= 'Amount:' . $this->customerOrder->get_total() . "\n";
		        	$message .= 'Card Type:' . $this->stripe_card_type . "\n";
		        	$message .= 'Last Four:' . $this->stripe_last_four . "\n";

		        	wp_mail( sanitize_email( $this->admin_mail ), $subject, $message );
		        }

		        //log successful charge
		        error_log( 'Successful Stripe Charge: Order ID #' . $this->customerOrder->id . "\n" );

		        //return successful charge
		        return true;

	      	} catch( Stripe_CardError $e ) {
				
				// Since it's a decline, Stripe_CardError will be caught
		        $error 	= $e->getJsonBody();
		        $mess  	= $error['error'];

		        //notify admin via email of error
		        if ( $this->admin_mail_optin == 'yes' ) {

		        	$subject = 'Stripe Charge Error on Order ID #' . $this->stripe_transaction_id;
		        	$message = 'Error on new Stripe Charge. Details below:' . "\n";
		        	$message .= 'Order ID:' . $this->stripe_transaction_id . "\n";
		        	$message .= 'Error Code:' . $mess['code'] . "\n";
		        	$message .= 'Error Type:' . $mess['type'] . "\n";
		        	$message .= 'Error Message:' . $mess['message'] . "\n";

		        	wp_mail( sanitize_email( $this->admin_mail ), $subject, $message );
		        }

		        //log errors
		        error_log( 'Error on Stripe Charge:' . $mess['message'] . ' for order ID #' . $this->customerOrder->id . "\n" );

		        //finally let woocommerce know there is an error
		        $woocommerce->add_error( __( 'Payment error: ', 'woothemes' ) . __( $mess['message'], 'woothemes' ) );

		       	return false;

			} catch ( Stripe_InvalidRequestError $e ) {

  				//log errors
		        error_log( 'Stripe API Error. Please fix immediately. Order ID #' . $this->customerOrder->id . "\n" );

		        //finally let woocommerce know there is an error
		        $woocommerce->add_error( __( 'Stripe error: This is not your fault. Please contact admin of this issue. Thanks.', 'woothemes' ) );

		       	return false;

			} catch ( Stripe_AuthenticationError $e ) {
			  	
			  	//log errors
		        error_log( 'Stripe Authentication Error. Please fix immediately. Order ID #' . $this->customerOrder->id . "\n" );

		        //finally let woocommerce know there is an error
		        $woocommerce->add_error( __( 'Stripe error: This is not your fault. Please contact admin of this issue. Thanks.', 'woothemes' ) );

		       	return false;

			} catch ( Stripe_ApiConnectionError $e ) {
			  	
			  	//log errors
		        error_log( 'Stripe Api ConnectionError Error. Please fix immediately. Order ID #' . $this->customerOrder->id . "\n" );

		        //finally let woocommerce know there is an error
		        $woocommerce->add_error( __( 'Stripe error: This is not your fault. Please contact admin of this issue. Thanks.', 'woothemes' ) );

		       	return false;

			} catch( Stripe_Error $e ) {

		        //notify admin via email if error
		        if ( $this->admin_mail_optin == 'yes' ) {

		        	$subject = 'Stripe Charge Error on Order ID #' . $this->customerOrder->id;
		        	$message = 'Error on new Stripe Charge. Details below:' . "\n";
		        	$message .= 'Order ID:' . $this->customerOrder->id . "\n";
		        	$message .= 'Error Message:' . $e . "\n";

		        	wp_mail( sanitize_email( $this->admin_mail ), $subject, $message );
		        }

		        //log errors
		        error_log( 'Error on Stripe Charge: Generic Stripe_Error for order ID #' . $this->customerOrder->id . "\n" );

		        //finally let woocommerce know there is an error
		        $woocommerce->add_error( __( 'Stripe error: This is not your fault. Please contact admin of this issue. Thanks.', 'woothemes' ) );

		        //return error
		        return false;

	      	} catch ( Exception $e ) {
			  	
			  	//log errors
		        error_log( 'Unknow error for order ID #' . $this->customerOrder->id . "\n" );

		        //finally let woocommerce know there is an error
		        $woocommerce->add_error( __( 'Stripe error: This is not your fault. Please contact admin of this issue. Thanks.', 'woothemes' ) );

		       	return false;

			}
	    }
    }

	/**
	 * Process payment
	 *
	 * @since    1.0.0
	 * @return   conditional arrays
	 * @param    order_id
	 */
	public function process_payment( $order_id ) {
		
		global $woocommerce;

		//make an order
		$this->customerOrder = new WC_Order( $order_id );

		//start striping
		if ( $this->mesasix_stripe_process() ) {
			
			if ( $this->customerOrder->status == 'completed' )
            	return;

	        $this->customerOrder->payment_complete();
	        $this->customerOrder->reduce_order_stock();

	        //empty cart
	        $woocommerce->cart->empty_cart();

	        $this->customerOrder->add_order_note( 'Stripe payment completed. Stripe transaction ID is '. $this->stripe_transaction_id );

	        // Return thankyou redirect
			return array(
				'result' => 'success',
				'redirect' => $this->get_return_url( $this->customerOrder )
			);

		} else {

			// Mark as failed
			$this->customerOrder->update_status('failed', __( 'Payment using credit card failed.', 'woocommerce' ));

			$this->customerOrder->add_order_note( 'Order payment failed. Do not ship items.' );

			$woocommerce->add_error(__('Payment Error: Please try again.'), 'woothemes');

		}
	}

	/**
	 * Checks if admin has provided necessary api keys from stripe.
	 *
	 * @since    1.0.0
	 * @return   Boolean
	 */
	function check_for_stripe_variables() {

		$check = false;
		switch ( $this->mesasix_stripe_mode ) {
			case 'yes':
				if ( !empty( $this->stripe_test_secret_key ) && !empty( $this->stripe_test_publishable_key ) )
					$check = true;

				break;
			
			default:
				if ( !empty( $this->stripe_secret_key ) && !empty( $this->stripe_publishable_key ) )
					$check = true;
				
				break;
		} 

		return $check;
	}

	/**
	 * NOTE:  Outputs the html form in WooCoomerce checkout page.
	 *
	 * @since    1.0.0
	 * @return   none
	 */
	function payment_fields() {

		if ( !$this->check_for_stripe_variables() ) {
			echo 'You did not provide stripe secret and publishable keys. Go to stripe.com and get your own secret keys and publishable keys.';
			exit;
		}

		wp_register_style( 'mesasix_stripe_css', plugins_url( 'assets/css/checkout.css', __FILE__ ), false, true );
		wp_register_script( 'stripe', 'https://js.stripe.com/v2/', array( 'jquery' ), false, true );
		wp_register_script( 'mesasix_stripe-checkout', plugins_url( 'assets/js/jquery.checkout.js', __FILE__ ), array( 'jquery', 'stripe' ), false, true );
	    wp_enqueue_script( 'mesasix_stripe-checkout' );
	    //wp_enqueue_style( 'mesasix_stripe_css' );

	    //check if test mode or production
	    if ( $this->mesasix_stripe_mode == 'yes' ) {

	    	$stripe_key = $this->stripe_test_publishable_key;
	    } else {

	    	$stripe_key = $this->stripe_publishable_key;
	    }

	    $ajax_text = empty( $this->ajax_text ) ? null : $this->ajax_text;

	    //print to html check out page the publishable keys for use in the stripejs token.
	    wp_localize_script( 'mesasix_stripe-checkout', 'stripeVars', array( 'admin_ajax' => admin_url().'admin-ajax.php', 'nonce' => wp_create_nonce('stripe-nonce-action'), 'key' => $stripe_key, 'ajax_text' => $ajax_text ) );
	   	
	   	ob_end_flush();
		ob_start(); ?>

		<div id="mesasix_stripe_payment_checkout">
			<?php do_action('mesasix_stripe_payment_before_fields'); ?>
			<h2>Payment Information</h2>
			<p>Stripe<sup>TM</sup> accepts credit cards from all major card providers. </p>
			<div class="payment-errors"></div>

		 		<div class="form-row">
			    	<label>Card Number</label>
			        <input type="text" size="20" data-stripe="number" placeholder="Card Number"/>
			    </div>
			 
			    <div class="third left">
			    	<div class="form-row">
				    	<label>CVC</label>
				        <input type="number" max="999" min="99" data-stripe="cvc" placeholder="CVC"/>
			    	</div>
			    </div>
			 	
			 	<div class="third left">
				    <div class="form-row">
				    	<label>Expiration Month &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</label>
				        <input type="number" max="12" min="01" size="2" data-stripe="exp-month" placeholder="MM"/>
			    	</div>
			    </div>

				<div class="third left">
				    <div class="form-row">
				    	<label>Expiration Year&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</label>
				      <input type="number" min="<?php echo date('Y'); ?>" max="<?php echo (int) date('Y') + 10; ?>" data-stripe="exp-year" placeholder="YYYY"/>
				    </div>
				</div>
			<div style="clear:both;"></div>
			<p class="mesasix_stripe_disclaimer"><?php echo $this->stripe_footer_notice; ?></p>
			<center class="mesasix_stripe_bottom_image"><img src="<?php echo apply_filters( 'mesasix_stripe_cards_icon', plugins_url( '/assets/img/cards.png', __FILE__ ) ); ?>"></center>
		</div>
		
		<?php ob_end_flush(); ob_start();
	}

} //end class

	/**
	 * NOTE:  Filters are points of execution in which WordPress modifies data
	 *        before saving it or sending it to the browser.
	 *
	 *        Filters: http://codex.wordpress.org/Plugin_API#Filters
	 *        Reference:  http://codex.wordpress.org/Plugin_API/Filter_Reference
	 *
	 * @since    1.0.0
	 */

	function mesasix_gateway_class($methods) {
		$methods[] = 'Mesasix_Stripe'; 
		return $methods;
	}
	add_filter( 'woocommerce_payment_gateways', 'mesasix_gateway_class' );

}//end function plugins loaded