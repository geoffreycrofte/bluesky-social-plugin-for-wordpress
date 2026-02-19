/**
 * Bluesky Syndication Notice - Heartbeat Integration
 * Handles live updates for syndication status via WordPress Heartbeat API
 */
(function($) {
    'use strict';

    // State management
    let postId = null;
    let isPolling = false;

    /**
     * Initialize heartbeat integration
     */
    function init() {
        const $notice = $('.bluesky-syndication-notice');

        if (!$notice.length) {
            return;
        }

        postId = $notice.data('post-id');
        if (!postId) {
            return;
        }

        // Start polling
        startPolling();

        // Attach retry button handler
        attachRetryHandler();
    }

    /**
     * Start heartbeat polling
     */
    function startPolling() {
        if (isPolling) {
            return;
        }

        isPolling = true;

        // Hook into heartbeat-send
        $(document).on('heartbeat-send.bluesky', function(event, data) {
            data.bluesky_check_syndication = postId;
        });

        // Hook into heartbeat-tick
        $(document).on('heartbeat-tick.bluesky', function(event, data) {
            if (data.bluesky_syndication) {
                handleStatusUpdate(data.bluesky_syndication);
            }
        });
    }

    /**
     * Stop heartbeat polling
     */
    function stopPolling() {
        isPolling = false;
        $(document).off('heartbeat-send.bluesky');
        $(document).off('heartbeat-tick.bluesky');
    }

    /**
     * Handle status update from heartbeat
     * @param {Object} data - Status data from server
     */
    function handleStatusUpdate(data) {
        const status = data.status;
        const $notice = $('.bluesky-syndication-notice[data-post-id="' + data.post_id + '"]');

        if (!$notice.length) {
            return;
        }

        switch (status) {
            case 'completed':
                const accountCount = data.account_count || 1;
                const successMessage = accountCount === 1
                    ? blueskyNotice.i18n.completed_single
                    : blueskyNotice.i18n.completed_multiple.replace('%d', accountCount);

                $notice
                    .removeClass('notice-info')
                    .addClass('notice-success is-dismissible')
                    .html('<p>' + successMessage + '</p>');

                // Stop polling once completed
                stopPolling();
                break;

            case 'failed':
                const failedAccounts = data.failed_accounts || [];
                const accountNames = failedAccounts.join(', ') || blueskyNotice.i18n.unknown_account;
                const failedMessage = blueskyNotice.i18n.failed.replace('%s', accountNames);

                $notice
                    .removeClass('notice-info')
                    .addClass('notice-error')
                    .html(
                        '<p>' + failedMessage + ' ' +
                        '<a href="#" class="bluesky-retry-syndication" data-post-id="' + data.post_id + '" data-nonce="' + blueskyNotice.retryNonce + '">' +
                        blueskyNotice.i18n.retry_now +
                        '</a></p>'
                    );

                // Reattach retry handler
                attachRetryHandler();

                // Stop polling once failed
                stopPolling();
                break;

            case 'partial':
                $notice
                    .removeClass('notice-info')
                    .addClass('notice-warning')
                    .html(
                        '<p>' + blueskyNotice.i18n.partial + ' ' +
                        '<a href="#" class="bluesky-retry-syndication" data-post-id="' + data.post_id + '" data-nonce="' + blueskyNotice.retryNonce + '">' +
                        blueskyNotice.i18n.retry_failed +
                        '</a></p>'
                    );

                // Reattach retry handler
                attachRetryHandler();

                // Stop polling
                stopPolling();
                break;

            case 'retrying':
                // Update notice text if needed
                const currentText = $notice.find('p').text();
                if (!currentText.includes(blueskyNotice.i18n.retrying)) {
                    $notice.html(
                        '<p><span class="spinner is-active" style="float:none;margin:0 5px 0 0;"></span>' +
                        blueskyNotice.i18n.retrying +
                        '</p>'
                    );
                }
                break;

            case 'pending':
                // Already showing pending state, no update needed
                break;

            case 'circuit_open':
            case 'rate_limited':
                // Stop polling for these states
                stopPolling();
                break;
        }
    }

    /**
     * Attach retry button click handler
     */
    function attachRetryHandler() {
        $(document).off('click.bluesky-retry', '.bluesky-retry-syndication');

        $(document).on('click.bluesky-retry', '.bluesky-retry-syndication', function(e) {
            e.preventDefault();

            const $button = $(this);
            const postId = $button.data('post-id');
            const nonce = $button.data('nonce');
            const $notice = $button.closest('.notice');

            // Disable button during request
            $button.css('pointer-events', 'none').css('opacity', '0.5');

            $.ajax({
                url: blueskyNotice.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'bluesky_retry_syndication',
                    post_id: postId,
                    nonce: nonce
                },
                success: function(response) {
                    if (response.success) {
                        // Replace notice with pending state
                        $notice
                            .removeClass('notice-error notice-warning')
                            .addClass('notice-info bluesky-syndication-notice')
                            .attr('data-post-id', postId)
                            .html(
                                '<p><span class="spinner is-active" style="float:none;margin:0 5px 0 0;"></span>' +
                                blueskyNotice.i18n.retrying +
                                '</p>'
                            );

                        // Restart polling
                        startPolling();
                    } else {
                        // Show error inline
                        $button.after('<span style="color:#dc3232;margin-left:5px;">' +
                            (response.data.message || blueskyNotice.i18n.retry_error) +
                            '</span>');

                        // Re-enable button
                        $button.css('pointer-events', '').css('opacity', '');
                    }
                },
                error: function() {
                    // Show error inline
                    $button.after('<span style="color:#dc3232;margin-left:5px;">' +
                        blueskyNotice.i18n.retry_error +
                        '</span>');

                    // Re-enable button
                    $button.css('pointer-events', '').css('opacity', '');
                }
            });
        });
    }

    // Initialize on document ready
    $(document).ready(function() {
        init();
    });

})(jQuery);
