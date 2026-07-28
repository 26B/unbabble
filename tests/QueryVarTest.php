<?php

namespace TwentySixB\WP\Plugin\Unbabble\Tests;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use TwentySixB\WP\Plugin\Unbabble\Router\QueryVar;

/**
 * Unit tests for query var routing.
 *
 * @since 0.7.0
 */
class QueryVarTest extends TestCase {
	use MockeryPHPUnitIntegration;

	/**
	 * Set up tests.
	 *
	 * @since 0.7.0
	 *
	 * @return void
	 */
	public function setUp() : void {
		parent::setUp();
		Monkey\setUp();
	}

	/**
	 * Tear down tests.
	 *
	 * @since 0.7.0
	 *
	 * @return void
	 */
	public function tearDown() : void {
		Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Test redirect guess pre-filter values.
	 *
	 * @since 0.7.0
	 *
	 * @testdox pre_redirect_guess_404_permalink - returns non-null pre-filter values unchanged
	 *
	 * @return void
	 */
	public function testPreRedirectGuess404PermalinkReturnsPreFilterValue() : void {
		Functions\expect( 'get_query_var' )->never();

		$this->assertSame(
			'https://example.test/manual/',
			QueryVar::pre_redirect_guess_404_permalink( 'https://example.test/manual/' )
		);
	}

	/**
	 * Test network_home_url signature.
	 *
	 * @since 0.7.0
	 *
	 * @testdox network_home_url - accepts resolver arguments
	 *
	 * @return void
	 */
	public function testNetworkHomeUrlAcceptsResolverArguments() : void {
		$method = new \ReflectionMethod( QueryVar::class, 'network_home_url' );

		$this->assertSame( 3, $method->getNumberOfParameters() );
	}
}
