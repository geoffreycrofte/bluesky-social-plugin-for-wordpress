/**
 * Bluesky Admin Notices - Persistent Notice Dismissal
 * Handles AJAX dismissal of persistent admin notices (expired credentials, circuit breaker)
 */
(function($) {
    'use strict';

    // Handle dismissal of persistent notices
    $(document).on('click', '.notice[data-dismissible] .notice-dismiss', function() {
        var $notice = $(this).closest('.notice');
        var noticeKey = $notice.data('dismissible');

        if (!noticeKey) return;

        $.post(ajaxurl, {
            action: 'bluesky_dismiss_notice',
            notice_key: noticeKey,
            nonce: blueskyAdminNotices.dismissNonce
        });
    });
})(jQuery);
