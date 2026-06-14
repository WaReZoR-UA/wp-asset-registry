<?php
/**
 * @package AssetRegistry
 */

declare( strict_types=1 );

namespace AssetRegistry\Tests\Unit;

use Brain\Monkey\Functions;

final class HarnessTest extends UnitTestCase {

    public function test_brain_monkey_can_mock_a_wordpress_function(): void {
        Functions\when( 'esc_html' )->returnArg();

        $this->assertSame( 'hello', esc_html( 'hello' ) );
    }
}
