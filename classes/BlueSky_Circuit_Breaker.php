<?php
/**
 * Circuit Breaker for Bluesky API calls
 *
 * Prevents cascading failures by stopping requests to failing accounts after
 * a failure threshold is reached. Implements three states:
 * - Closed: Normal operation (requests allowed)
 * - Open: Circuit tripped (requests blocked during cooldown)
 * - Half-Open: Testing recovery (one request allowed after cooldown)
 *
 * @package BlueSky_Social_Integration
 * @since 1.6.0
 */

class BlueSky_Circuit_Breaker {
    /**
     * Account ID for this circuit breaker instance
     *
     * @var string
     */
    private $account_id;

    /**
     * Number of consecutive failures before opening circuit
     *
     * @var int
     */
    private const FAILURE_THRESHOLD = 3;

    /**
     * Cooldown period in seconds (15 minutes)
     *
     * @var int
     */
    private const COOLDOWN_SECONDS = 900;

    /**
     * Constructor
     *
     * @param string $account_id Account UUID
     */
    public function __construct( $account_id ) {
        $this->account_id = $account_id;
    }

    /**
     * Check if requests are allowed for this account
     *
     * Returns false if circuit is open (during cooldown).
     * Transitions from open to half-open if cooldown has expired.
     *
     * @return bool True if requests should proceed, false if blocked
     */
    public function is_available() {
        $state = $this->get_state();

        // Closed circuit - normal operation
        if ( $state['status'] === 'closed' ) {
            return true;
        }

        // Open circuit - check if cooldown expired
        if ( $state['status'] === 'open' ) {
            if ( time() >= $state['open_until'] ) {
                // Cooldown expired - transition to half-open
                $this->set_state( 'half_open', 0 );
                return true;
            }
            // Still in cooldown
            return false;
        }

        // Half-open circuit - allow test request
        if ( $state['status'] === 'half_open' ) {
            return true;
        }

        return false;
    }

    /**
     * Record a successful API call
     *
     * In closed state: resets failure count
     * In half-open state: closes circuit (recovery successful)
     *
     * @return void
     */
    public function record_success() {
        $state = $this->get_state();

        if ( $state['status'] === 'half_open' ) {
            // Recovery successful - close circuit
            $this->close_circuit();
        } elseif ( $state['status'] === 'closed' ) {
            // Reset failure count on success
            delete_transient( $this->get_failure_key() );
        }
    }

    /**
     * Record a failed API call
     *
     * Increments failure count. Opens circuit if threshold reached.
     * In half-open state, reopens circuit immediately.
     *
     * @return void
     */
    public function record_failure() {
        $state = $this->get_state();

        // Get current failure count
        $failures = (int) get_transient( $this->get_failure_key() );
        $failures++;

        // Store updated failure count
        set_transient( $this->get_failure_key(), $failures, WEEK_IN_SECONDS );

        if ( $state['status'] === 'half_open' ) {
            // Test request failed - reopen circuit
            $this->open_circuit();
        } elseif ( $failures >= self::FAILURE_THRESHOLD ) {
            // Threshold reached - open circuit
            $this->open_circuit();
        }
    }

    /**
     * Get current circuit state
     *
     * @return array State array with 'status' and 'open_until' keys
     */
    private function get_state() {
        $state = get_transient( $this->get_state_key() );

        if ( false === $state ) {
            // No state = closed circuit
            return [
                'status' => 'closed',
                'open_until' => 0,
            ];
        }

        return $state;
    }

    /**
     * Set circuit state
     *
     * @param string $status Circuit status (closed, open, half_open)
     * @param int    $open_until Timestamp when circuit can transition (0 if not applicable)
     * @return void
     */
    private function set_state( $status, $open_until ) {
        $state = [
            'status' => $status,
            'open_until' => $open_until,
        ];

        if ( $status === 'open' ) {
            set_transient( $this->get_state_key(), $state, self::COOLDOWN_SECONDS );
        } else {
            // Store state with longer TTL for closed/half_open
            set_transient( $this->get_state_key(), $state, HOUR_IN_SECONDS );
        }
    }

    /**
     * Open the circuit (start cooldown)
     *
     * @return void
     */
    private function open_circuit() {
        $open_until = time() + self::COOLDOWN_SECONDS;
        $this->set_state( 'open', $open_until );
    }

    /**
     * Close the circuit (reset to normal operation)
     *
     * @return void
     */
    private function close_circuit() {
        delete_transient( $this->get_state_key() );
        delete_transient( $this->get_failure_key() );
    }

    /**
     * Get transient key for circuit state
     *
     * @return string
     */
    private function get_state_key() {
        return 'bluesky_circuit_' . $this->account_id;
    }

    /**
     * Get transient key for failure count
     *
     * @return string
     */
    private function get_failure_key() {
        return 'bluesky_failures_' . $this->account_id;
    }
}
