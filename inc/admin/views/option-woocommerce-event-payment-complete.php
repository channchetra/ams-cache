<?php
/**
 * AMS Cache - WooCommerce - Event - Purchase completed.
 *
 * @author Terry Lin
 * @link https://terryl.in/
 * @since 1.6.0
 * @version 1.6.0
 */

if ( ! defined( 'SCM_INC' ) ) {
	die;
}

$option_woocommerce_event_payment_complete = get_option( 'scm_option_woocommerce_event_payment_complete', 'no' );

?>

<div>
	<div class="scm-option-item">
		<input type="radio" name="scm_option_woocommerce_event_payment_complete" id="ams-cache-woocommerce-event-payment-complete-option-yes" value="yes" 
			<?php checked( $option_woocommerce_event_payment_complete, 'yes' ); ?>>
		<label for="ams-cache-woocommerce-event-payment-complete-option-yes">
			<?php _e( 'Yes', 'ams-cache' ); ?><br />
		<label>
	</div>
	<div class="scm-option-item">
		<input type="radio" name="scm_option_woocommerce_event_payment_complete" id="ams-cache-woocommerce-event-payment-complete-option-no" value="no" 
			<?php checked( $option_woocommerce_event_payment_complete, 'no' ); ?>>
		<label for="ams-cache-woocommerce-event-payment-complete-option-no">
			<?php _e( 'No', 'ams-cache' ); ?>
		<label>
	</div>	
</div>
<p><em><?php _e( 'Clear the product page cache referred to the order after a user has successfully completed a purchase.', 'ams-cache' ); ?></em></p>
