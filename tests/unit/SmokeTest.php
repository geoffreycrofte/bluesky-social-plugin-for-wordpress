<?php

use Brain\Monkey;
use PHPUnit\Framework\TestCase;

/**
 * Smoke test to verify test infrastructure is working.
 * Confirms PHPUnit runs, Brain Monkey loads, and WP function stubs work.
 */
class SmokeTest extends TestCase {

    protected function setUp(): void {
        parent::setUp();
        Monkey\setUp();
    }

    protected function tearDown(): void {
        Monkey\tearDown();
        parent::tearDown();
    }

    public function test_infrastructure_is_working(): void {
        $this->assertTrue( true );
    }
}
