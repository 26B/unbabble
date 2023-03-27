<?php

function mock_user_function( string $function, $args, $times, $return = null ) : void {
	\WP_Mock::userFunction(
		$function,
		[
			'args'   => $args,
			'times'  => $times,
			'return' => $return,
		]
	);
}
