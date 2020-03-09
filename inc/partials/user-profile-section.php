<h2><?php _e( 'Bluehost Maestro', 'bluehost-maesetro' ); ?></h2>
<table class="form-table" role="presentation">
	<tbody>
		<tr class="user-maestro-added-date">
			<th><?php _e( 'Date Added' ); ?></th>
			<td><?php echo esc_html( gmdate( get_option( 'date_format' ), (int) $webpro->added_date ) ); ?></td>
		</tr>
		<tr class="user-maestro-added-by">
			<th><?php _e( 'Added By' ); ?></th>
			<td><?php echo esc_html( $webpro->added_by ); ?></td>
		</tr>
		<tr class="user-maestro-revoke">
			<th><?php _e( 'Revoke' ); ?></th>
			<td><a href="" class="button button-secondary"><?php _e( 'Revoke Maestro Access' ); ?></a></td>
		</tr>
	</tbody>
</table>
