<?php
/**
 * AMS Cache - TTL.
 *
 * @author Terry Lin
 * @link https://terryl.in/
 * @since 1.0.0
 * @version 1.0.0
 */

if ( ! defined( 'SCM_INC' ) ) {
	die;
}

$option_ttl           = (int) get_option( 'scm_option_ttl', '86400' );
$option_ttl_mechanism = get_option( 'scm_option_ttl_mechanism', 'enable' );

// 5 minutes.
if ( $option_ttl < 300 ) {
	$option_ttl = '300';
	update_option( 'scm_option_ttl', $option_ttl );
}

// 1 month.
if ( $option_ttl > 2592000 ) {
	$option_ttl = '2592000';
	update_option( 'scm_option_ttl', $option_ttl );
}

?>

<div>
	<div class="scm-option-item">
		<input type="radio" name="scm_option_ttl_mechanism" id="ams-cache-ttl-mechanisum-enable" value="enable" 
			<?php checked( $option_ttl_mechanism, 'enable' ); ?>>
		<label for="ams-cache-ttl-mechanisum-enable">
			<?php _e( 'Enable', 'ams-cache' ); ?><br />
		<label>
	</div>
	<div class="scm-option-item">
		<input type="radio" name="scm_option_ttl_mechanism" id="ams-cache-ttl-mechanisum-disable" value="disable" 
			<?php checked( $option_ttl_mechanism, 'disable' ); ?>>
		<label for="ams-cache-ttl-mechanisum-disable">
			<?php _e( 'Disable', 'ams-cache' ); ?>
		<label>
	</div>	
</div>
<p><em><?php _e( 'Once you disable this option, AMS Cache will not automatically clear and update the cache.', 'ams-cache' ); ?></em></p>

<div style="margin-top: 30px">
	<div>
		<input type="text" name="scm_option_ttl" value="<?php echo esc_attr( $option_ttl ); ?>">
	</div>
</div>
<p><em><?php _e( 'Please fill in a number between 300-2592000. (in seconds)', 'ams-cache' ); ?></em></p>
<h4><?php _e( 'Examples', 'ams-cache' ); ?></h3>
<ul class="ams-ttl-example-list">
	<li class="ams-ttl-example-head">
		<span><?php _e( 'Time', 'ams-cache' ); ?></span>
		<span><?php _e( 'Description', 'ams-cache' ); ?></span>
	</li>
	<li><span>300</span><span><?php _e( '5 minutes', 'ams-cache' ); ?></span></li>
	<li><span>3600</span><span><?php _e( '1 hour', 'ams-cache' ); ?></span></li>
	<li><span>86400</span><span><?php _e( '24 hours', 'ams-cache' ); ?></span></li>
	<li><span>2592000</span><span><?php _e( '30 days', 'ams-cache' ); ?></span></li>
</ul>
