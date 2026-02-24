<?php
use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

/**
 * Tests for BlueSky_Circuit_Breaker
 *
 * Covers:
 * - Circuit states: closed (normal), open (failing), half-open (testing recovery)
 * - Failure threshold triggering (3 failures opens circuit)
 * - Cooldown period (15 minutes)
 * - Per-account isolation
 * - State persistence via transients
 */
class BlueSky_Circuit_Breaker_Test extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    public function test_is_available_returns_true_when_circuit_is_closed()
    {
        // Arrange - fresh circuit with no prior state
        Functions\expect('get_transient')
            ->once()
            ->with('bluesky_circuit_account-1')
            ->andReturn(false); // No state = closed circuit

        $breaker = new BlueSky_Circuit_Breaker('account-1');

        // Act
        $result = $breaker->is_available();

        // Assert
        $this->assertTrue($result, 'Circuit should be available when closed');
    }

    public function test_record_failure_increments_failure_count()
    {
        // Arrange
        $account_id = 'account-2';

        Functions\expect('get_transient')
            ->once()
            ->with('bluesky_circuit_account-2')
            ->andReturn(false);

        Functions\expect('get_transient')
            ->once()
            ->with('bluesky_failures_account-2')
            ->andReturn(false); // No failures yet

        Functions\expect('set_transient')
            ->once()
            ->with('bluesky_failures_account-2', 1, \Mockery::type('int'))
            ->andReturn(true);

        $breaker = new BlueSky_Circuit_Breaker($account_id);

        // Act
        $breaker->record_failure();

        // Assert - expectations verified by Brain Monkey
        $this->assertTrue(true);
    }

    public function test_circuit_opens_after_three_failures()
    {
        // Arrange
        $account_id = 'account-3';
        $now = time();

        // Circuit state checks (each record_failure calls get_state)
        // First two return closed, third returns closed before opening
        Functions\expect('get_transient')
            ->times(3)
            ->with('bluesky_circuit_account-3')
            ->andReturn(false); // Closed state

        // Failure count checks for record_failure calls
        Functions\expect('get_transient')
            ->times(3)
            ->with('bluesky_failures_account-3')
            ->andReturnUsing(function() {
                static $count = 0;
                return $count++;
            });

        // Set failure count (increments to 1, 2, 3)
        Functions\expect('set_transient')
            ->times(2)
            ->with('bluesky_failures_account-3', \Mockery::anyOf(1, 2), \Mockery::type('int'))
            ->andReturn(true);

        // Final set_transient opens the circuit (count = 3)
        Functions\expect('set_transient')
            ->once()
            ->with('bluesky_circuit_account-3', \Mockery::type('array'), 900)
            ->andReturn(true);

        Functions\expect('set_transient')
            ->once()
            ->with('bluesky_failures_account-3', 3, \Mockery::type('int'))
            ->andReturn(true);

        Functions\expect('delete_transient')
            ->never(); // Should not delete on open

        $breaker = new BlueSky_Circuit_Breaker($account_id);

        // Act - record 3 failures
        $breaker->record_failure();
        $breaker->record_failure();
        $breaker->record_failure();

        // Assert - check circuit is now open
        Functions\expect('get_transient')
            ->once()
            ->with('bluesky_circuit_account-3')
            ->andReturn([
                'status' => 'open',
                'open_until' => $now + 900
            ]);

        $is_available = $breaker->is_available();
        $this->assertFalse($is_available, 'Circuit should be open after 3 failures');
    }

    public function test_is_available_returns_false_during_cooldown()
    {
        // Arrange - circuit opened 5 minutes ago (still in cooldown)
        $now = time();
        $opened_at = $now - 300; // 5 minutes ago
        $open_until = $opened_at + 900; // Opens for 15 minutes

        Functions\expect('get_transient')
            ->once()
            ->with('bluesky_circuit_account-4')
            ->andReturn([
                'status' => 'open',
                'open_until' => $open_until
            ]);

        $breaker = new BlueSky_Circuit_Breaker('account-4');

        // Act
        $result = $breaker->is_available();

        // Assert
        $this->assertFalse($result, 'Circuit should remain open during cooldown period');
    }

    public function test_circuit_transitions_to_half_open_after_cooldown()
    {
        // Arrange - circuit opened 16 minutes ago (cooldown expired)
        $now = time();
        $opened_at = $now - 960; // 16 minutes ago
        $open_until = $opened_at + 900; // 15-minute cooldown already passed

        Functions\expect('get_transient')
            ->once()
            ->with('bluesky_circuit_account-5')
            ->andReturn([
                'status' => 'open',
                'open_until' => $open_until
            ]);

        Functions\expect('set_transient')
            ->once()
            ->with('bluesky_circuit_account-5', ['status' => 'half_open', 'open_until' => 0], \Mockery::type('int'))
            ->andReturn(true);

        $breaker = new BlueSky_Circuit_Breaker('account-5');

        // Act
        $result = $breaker->is_available();

        // Assert
        $this->assertTrue($result, 'Circuit should transition to half-open and allow test request');
    }

    public function test_record_success_in_half_open_closes_circuit()
    {
        // Arrange - circuit is half-open
        Functions\expect('get_transient')
            ->once()
            ->with('bluesky_circuit_account-6')
            ->andReturn([
                'status' => 'half_open',
                'open_until' => 0
            ]);

        Functions\expect('delete_transient')
            ->once()
            ->with('bluesky_circuit_account-6')
            ->andReturn(true);

        Functions\expect('delete_transient')
            ->once()
            ->with('bluesky_failures_account-6')
            ->andReturn(true);

        $breaker = new BlueSky_Circuit_Breaker('account-6');

        // Act
        $breaker->record_success();

        // Assert - expectations verified by Brain Monkey
        $this->assertTrue(true);
    }

    public function test_record_failure_in_half_open_reopens_circuit()
    {
        // Arrange - circuit is half-open
        Functions\expect('get_transient')
            ->once()
            ->with('bluesky_circuit_account-7')
            ->andReturn([
                'status' => 'half_open',
                'open_until' => 0
            ]);

        Functions\expect('get_transient')
            ->once()
            ->with('bluesky_failures_account-7')
            ->andReturn(3); // Already had 3 failures

        Functions\expect('set_transient')
            ->once()
            ->with('bluesky_circuit_account-7', \Mockery::type('array'), 900)
            ->andReturn(true);

        Functions\expect('set_transient')
            ->once()
            ->with('bluesky_failures_account-7', 4, \Mockery::type('int'))
            ->andReturn(true);

        $breaker = new BlueSky_Circuit_Breaker('account-7');

        // Act
        $breaker->record_failure();

        // Assert - expectations verified by Brain Monkey
        $this->assertTrue(true);
    }

    public function test_record_success_in_closed_state_resets_failure_count()
    {
        // Arrange - circuit closed with 1 failure
        Functions\expect('get_transient')
            ->once()
            ->with('bluesky_circuit_account-8')
            ->andReturn(false); // Closed state

        Functions\expect('delete_transient')
            ->once()
            ->with('bluesky_failures_account-8')
            ->andReturn(true);

        $breaker = new BlueSky_Circuit_Breaker('account-8');

        // Act
        $breaker->record_success();

        // Assert - expectations verified by Brain Monkey
        $this->assertTrue(true);
    }

    public function test_different_accounts_have_independent_circuits()
    {
        // Arrange - account-9 has open circuit, account-10 has closed circuit
        Functions\expect('get_transient')
            ->once()
            ->with('bluesky_circuit_account-9')
            ->andReturn([
                'status' => 'open',
                'open_until' => time() + 900
            ]);

        Functions\expect('get_transient')
            ->once()
            ->with('bluesky_circuit_account-10')
            ->andReturn(false); // Closed

        $breaker_9 = new BlueSky_Circuit_Breaker('account-9');
        $breaker_10 = new BlueSky_Circuit_Breaker('account-10');

        // Act
        $available_9 = $breaker_9->is_available();
        $available_10 = $breaker_10->is_available();

        // Assert
        $this->assertFalse($available_9, 'Account 9 circuit should be open');
        $this->assertTrue($available_10, 'Account 10 circuit should be closed and available');
    }
}
