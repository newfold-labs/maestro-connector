<h2><?php _e( 'Bluehost Maestro', 'maestro-connector' ); ?></h2>
<table class="form-table" role="presentation">
	<tbody>
		<tr class="user-maestro-added-date">
			<th><?php _e( 'Date Added', 'maestro-connector' ); ?></th>
			<td><?php echo esc_html( gmdate( get_option( 'date_format' ), (int) $webpro->added_time ) ); ?></td>
		</tr>
		<tr class="user-maestro-added-by">
			<th><?php _e( 'Added By', 'maestro-connector' ); ?></th>
			<td><?php echo esc_html( $webpro->added_by ); ?></td>
		</tr>
		<tr class="user-maestro-revoke">
			<th><?php _e( 'Revoke', 'maestro-connector' ); ?></th>
			<td><a href="<?php echo $revoke_url; ?>" class="button button-secondary"><?php _e( 'Revoke Maestro Access', 'maestro-connector' ); ?></a></td>
		</tr>
	</tbody>
</table>
