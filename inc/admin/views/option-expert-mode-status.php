<?php
/**
 * AMS Cache - expert-mode option.
 *
 * @author Terry Lin
 * @link https://terryl.in/
 * @since 1.2.1
 * @version 1.2.1
 */

if ( ! defined( 'SCM_INC' ) ) {
	die;
}

$option_expert_mode = get_option( 'scm_option_expert_mode_status', 'enable' );

?>

<div class="scm-expert-mode-intro">
	<p><?php _e( 'Because AMS Cache works after all plugins are installed, it can only save a maximum of 20-25 percent memory.', 'ams-cache' ); ?> <code>:(</code></p><br />
	<p>
		<?php
		echo sprintf(
			// translators: %s is a placeholder for a file name.
			__( 'However, if you modify %s to let AMS Cache output cache before everything initialized, it can save up to a maximum of <strong>95</strong> percent memory - possibly even more.', 'ams-cache' ),
			'<strong>wp-config.php</strong>'
		);
		?>
		<code>:)</code>
		</p>
</div>
<br /><br />
<div>
	<div class="scm-option-item">
		<input type="radio" name="scm_option_expert_mode_status" id="ams-cache-expert-mode-option-enable" value="enable" 
			<?php checked( $option_expert_mode, 'enable' ); ?>>
		<label for="ams-cache-expert-mode-option-enable">
			<?php _e( 'Enable', 'ams-cache' ); ?><br />
		<label>
	</div>
	<div class="scm-option-item">
		<input type="radio" name="scm_option_expert_mode_status" id="ams-cache-expert-mode-option-disable" value="disable" 
			<?php checked( $option_expert_mode, 'disable' ); ?>>
		<label for="ams-cache-expert-mode-option-disable">
			<?php _e( 'Disable', 'ams-cache' ); ?>
		<label>
	</div>	
</div>
<p><em><?php _e( 'This option only works when you have put the custom PHP code in wp-config.php', 'ams-cache' ); ?></em></p>
<?php if ( scm_is_expert_mode_code_ready() ) : ?>
	<p><em class="scm-msg scm-msg-info"><?php _e( 'PHP code for Expert Mode found.', 'ams-cache' ); ?></em></p>
<?php else : ?>
	<p><em class="scm-msg scm-msg-error"><?php _e( 'Could not find PHP code for Expert Mode.', 'ams-cache' ); ?></em></p>
<?php endif; ?>
