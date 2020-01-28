<?php

namespace Bluehost\Maestro;

add_action( 'edit_user_profile', __NAMESPACE__ . '\\manage_maestro_profile' );
add_action( 'show_user_profile', __NAMESPACE__ . '\\manage_maestro_profile' );

function manage_maestro_profile( $user ) {
	echo '
	<h2>Bluehost Maestro</h2>
	<table class="form-table" role="presentation">
		<tbody>
			<tr class="user-maestro-revoke">
				<th>Revoke</th>
				<td><a href="" class="button button-secondary">Revoke Maestro Access</a></td>
			</tr>
		</tbody>
	</table>';
}
