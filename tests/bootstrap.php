<?php

// First we need to load the composer autoloader so we can use WP Mock
require_once './vendor/autoload.php';

require_once './tests/MockHelpers.php';

// Now call the bootstrap method of WP Mock
WP_Mock::activateStrictMode();
WP_Mock::bootstrap();

\WP_Mock::passthruFunction( '__', [ 'return_arg' => 0 ] );
