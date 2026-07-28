<?php

use Brain\Monkey\Functions;

function mock_user_function( string $function, $args, $times, $return = null ) : void {
	$expectation = Functions\expect( $function );

	if ( $times === null ) {
		$expectation->zeroOrMoreTimes();
	} else {
		$expectation->times( $times );
	}

	if ( $args === null ) {
		$expectation->withAnyArgs();
	} else if ( $args === [] ) {
		$expectation->withNoArgs();
	} else {
		$expectation->with( ...$args );
	}

	$expectation->andReturn( $return );
}
