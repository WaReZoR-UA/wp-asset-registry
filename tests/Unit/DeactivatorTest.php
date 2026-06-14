<?php
/**
 * @package AssetRegistry
 */

declare( strict_types=1 );

namespace AssetRegistry\Tests\Unit;

use AssetRegistry\Deactivator;
use Brain\Monkey\Functions;

final class DeactivatorTest extends UnitTestCase {

    public function test_deactivate_flushes_rewrite_rules(): void {
        Functions\expect( 'flush_rewrite_rules' )->once();

        Deactivator::deactivate();
    }
}
