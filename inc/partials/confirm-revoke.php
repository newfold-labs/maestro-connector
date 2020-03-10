<p class="thin"><?php _e( 'Are you sure you would like to revoke access for this Web Pro?' ); ?></p>
<div class="details bold">
	<div class='name'><span><?php _e( 'Name' ); ?>:</span> <span><?php echo esc_html( $webpro->user->first_name ); ?> <?php echo esc_html( $webpro->user->last_name ); ?></span></div>
	<div class='email'><span><?php _e( 'Email' ); ?>:</span> <span><?php echo esc_html( $webpro->user->user_email ); ?></span></div>
</div>
<div class="buttons">
	<a href="<?php echo $cancel_url; ?>" class="maestro-button secondary"><?php _e( 'No, leave them' ); ?></a>
	<a href="<?php echo $confirm_url; ?>" class="maestro-button primary"><?php _e( 'Yes, revoke access' ); ?></a>
</div>
