<h2><?php _e( 'Bluehost Maestro', 'bluehost-maesetro' ); ?></h2>
<table class="form-table" role="presentation">
	<tbody>
		<tr class="user-maestro-added-date">
			<th><?php _e( 'Date Added', 'bluehost-maestro' ); ?></th>
			<td><?php echo esc_html( gmdate( get_option( 'date_format' ), (int) get_user_meta( $user->ID, 'bh_maestro_added_date', true ) ) ); ?></td>
		</tr>
		<tr class="user-maestro-added-by">
			<th><?php _e( 'Added By', 'bluehost-maestro' ); ?></th>
			<td><?php echo esc_html( get_user_meta( $user->ID, 'bh_maestro_added_by', true ) ); ?></td>
		</tr>
		<tr class="user-maestro-revoke">
			<th><?php _e( 'Revoke', 'bluehost-maestro' ); ?></th>
			<td><a href="" class="button button-secondary"><?php _e( 'Revoke Maestro Access', 'bluehost-maestro' ); ?></a></td>
		</tr>
	</tbody>
</table>
