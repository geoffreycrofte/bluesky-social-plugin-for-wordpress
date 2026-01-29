/**
 * Bluesky Discussion Display JavaScript
 * Handles interactions for the discussion metabox
 */

(function($) {
    'use strict';

    // Wait for DOM to be ready
    $(document).ready(function() {

        // Refresh discussion button handler
        $('#bluesky-refresh-discussion').on('click', function(e) {
            e.preventDefault();

            var $button = $(this);
            var $content = $('#bluesky-discussion-content');
            var $icon = $button.find('.dashicons');

            // Disable button during refresh
            $button.prop('disabled', true);
            $icon.addClass('spin-animation');

            // Show loading state
            $content.html('<div class="bluesky-discussion-loading">' +
                         blueskyDiscussionData.loadingText +
                         '</div>');

            // Make AJAX request
            $.ajax({
                url: blueskyDiscussionData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'refresh_bluesky_discussion',
                    post_id: blueskyDiscussionData.postId,
                    nonce: blueskyDiscussionData.nonce
                },
                success: function(response) {
                    if (response.success && response.data.html) {
                        $content.html(response.data.html);

                        // Show success feedback
                        showNotice('success', 'Discussion refreshed successfully!');
                    } else {
                        $content.html('<div class="bluesky-discussion-error">' +
                                     '<p>Failed to refresh discussion. Please try again.</p>' +
                                     '</div>');
                        showNotice('error', 'Failed to refresh discussion.');
                    }
                },
                error: function() {
                    $content.html('<div class="bluesky-discussion-error">' +
                                 '<p>Network error. Please try again.</p>' +
                                 '</div>');
                    showNotice('error', 'Network error occurred.');
                },
                complete: function() {
                    // Re-enable button
                    $button.prop('disabled', false);
                    $icon.removeClass('spin-animation');
                }
            });
        });

        // Auto-expand replies when clicking on reply count
        $(document).on('click', '.bluesky-reply-stat', function(e) {
            e.preventDefault();
            var $reply = $(this).closest('.bluesky-reply');
            var $children = $reply.find('> .bluesky-reply-children');

            if ($children.length) {
                $children.slideToggle(200);
            }
        });

        // Collapse/expand nested replies
        $(document).on('click', '.bluesky-reply-header', function(e) {
            if ($(e.target).is('a, img')) {
                return; // Don't toggle if clicking link or avatar
            }

            var $reply = $(this).closest('.bluesky-reply');
            var $children = $reply.find('> .bluesky-reply-children');

            if ($children.length && $children.children().length > 0) {
                $children.slideToggle(200);
                $reply.toggleClass('collapsed');
            }
        });

        // Link preview on hover
        $(document).on('mouseenter', '.bluesky-reply-author-handle', function() {
            $(this).css('text-decoration', 'underline');
        }).on('mouseleave', '.bluesky-reply-author-handle', function() {
            $(this).css('text-decoration', 'none');
        });

        // Helper function to show notices
        function showNotice(type, message) {
            var $notice = $('<div class="notice notice-' + type + ' is-dismissible">' +
                           '<p>' + message + '</p>' +
                           '</div>');

            // Insert after the first heading or at the top
            if ($('.wrap > h1, .wrap > h2').length) {
                $('.wrap > h1, .wrap > h2').first().after($notice);
            } else {
                $('.wrap').prepend($notice);
            }

            // Make dismissible
            $notice.find('.notice-dismiss').on('click', function() {
                $notice.fadeOut(200, function() {
                    $(this).remove();
                });
            });

            // Auto-dismiss after 3 seconds
            setTimeout(function() {
                $notice.fadeOut(200, function() {
                    $(this).remove();
                });
            }, 3000);
        }

        // Add spin animation styles if not already present
        if (!$('#bluesky-discussion-styles').length) {
            $('<style id="bluesky-discussion-styles">')
                .text('@keyframes spin { to { transform: rotate(360deg); } }' +
                      '.spin-animation { animation: spin 0.8s linear infinite; }' +
                      '.bluesky-reply.collapsed > .bluesky-reply-children { display: none; }' +
                      '.bluesky-reply-header { cursor: pointer; }' +
                      '.bluesky-reply-header:hover { background: rgba(0,0,0,0.02); }')
                .appendTo('head');
        }

        // Initialize collapsed state for deeply nested replies (optional)
        $('.bluesky-reply[data-depth="4"], .bluesky-reply[data-depth="5"]').each(function() {
            var $children = $(this).find('> .bluesky-reply-children');
            if ($children.length) {
                $children.hide();
                $(this).addClass('collapsed');
            }
        });

    });

})(jQuery);
