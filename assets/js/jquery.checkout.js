jQuery(document).ready(function($) {

    Stripe.setPublishableKey( stripeVars.key );
    var WooCheckoutForm = $( '.woocommerce form.checkout' );
 
    var stripeResponseHandler = function(status, response) {
      
      if (response.error) {

        // Show the errors on the form
        WooCheckoutForm.find('.payment-errors').html('<div class="bottom-cta">'+response.error.message+'</div>');

        //remove initial token since we have error
        $('input.stripevars').remove();

        //unblock ui
        WooCheckoutForm.unblock();

      } else {

        // token contains id, last4, and card type
        WooCheckoutForm.append('<input type="hidden" class="stripevars" name="stripeToken" value="'+response.id+'" />');

        WooCheckoutForm.submit();
      }
    };
 
    jQuery(function($) {

      $('form.checkout').bind('#place_order, checkout_place_order_mesasix_stripe', function(e) {

        //check if payment selected is indeed mesasix stripe
        if( $( 'input[name=payment_method]:checked' ).val() !== 'mesasix_stripe' ) {
            return true;
        }

        WooCheckoutForm.find('.payment-errors').html('');
        WooCheckoutForm.block({message: stripeVars.ajax_text, overlayCSS: {background: "#fff url(" + woocommerce_params.ajax_loader_url + ") no-repeat center",backgroundSize: "16px 16px",opacity: .6}});

        // continue after adding the token
        if( WooCheckoutForm.find('.stripevars').length )
          return true;

        Stripe.createToken( WooCheckoutForm, stripeResponseHandler );

        return false;
      });

      WooCheckoutForm.on('click', '#place_order,form.checkout input:submit', function(){

        // replace old token with new one
        WooCheckoutForm.find('input.stripevars').remove();
      });

      //check for placeholder support. Modernizr is nice but...
      if ( document.createElement('input').placeholder == undefined ) {
        // display lavel for placeholder disabled browser. fuck ie
        WooCheckoutForm.find('#mesasix_stripe_payment_checkout').find('.form-row').find('label').css('display', 'block');
      }

    });
}); //end ready