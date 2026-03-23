/**
 * Bluesky Link Panel
 *
 * Gutenberg Document Settings panel for manually linking a published post to
 * its corresponding Bluesky post URL.
 *
 * Only rendered when:
 * - blueskyLinkData.isSyndicated === false (post not yet syndicated)
 * - The post status is "publish"
 */

(function (wp) {
    'use strict';

    if (!window.blueskyLinkData || window.blueskyLinkData.isSyndicated) {
        return; // Already syndicated — panel not needed.
    }

    var registerPlugin        = wp.plugins.registerPlugin;
    var PluginDocumentSettingPanel = wp.editPost.PluginDocumentSettingPanel;
    var Component             = wp.element.Component;
    var createElement         = wp.element.createElement;
    var withSelect            = wp.data.withSelect;
    var compose               = wp.compose.compose;
    var TextControl           = wp.components.TextControl;
    var Button                = wp.components.Button;
    var Notice                = wp.components.Notice;
    var __                    = wp.i18n.__;

    var i18n = window.blueskyLinkData.i18n || {};

    /**
     * Basic client-side URL validation to give immediate feedback before the
     * round-trip to the server.
     */
    function isValidBskyUrl(url) {
        return /^https:\/\/bsky\.app\/profile\/[^/\s]+\/post\/[a-zA-Z0-9]+$/.test(url.trim());
    }

    var BlueSkyLinkPanel = /** @class */ (function (_super) {
        function BlueSkyLinkPanel(props) {
            _super.call(this, props);
            this.state = {
                url:       '',
                isLoading: false,
                error:     null,
                success:   false,
            };
            this.handleLink = this.handleLink.bind(this);
        }

        BlueSkyLinkPanel.prototype = Object.create(Component.prototype);
        BlueSkyLinkPanel.prototype.constructor = BlueSkyLinkPanel;

        BlueSkyLinkPanel.prototype.handleLink = function () {
            var self = this;
            var url  = this.state.url.trim();

            if (!url) { return; }

            if (!isValidBskyUrl(url)) {
                self.setState({
                    error: i18n.invalidUrl || __('Invalid Bluesky URL. Expected: https://bsky.app/profile/handle/post/postid', 'social-integration-for-bluesky')
                });
                return;
            }

            self.setState({ isLoading: true, error: null });

            var data = new FormData();
            data.append('action',      'bluesky_link_post');
            data.append('nonce',       window.blueskyLinkData.nonce);
            data.append('post_id',     String(window.blueskyLinkData.postId));
            data.append('bluesky_url', url);

            fetch(window.blueskyLinkData.ajaxUrl, { method: 'POST', body: data })
                .then(function (r) { return r.json(); })
                .then(function (response) {
                    if (response.success) {
                        self.setState({ success: true, isLoading: false });
                        setTimeout(function () { window.location.reload(); }, 1200);
                    } else {
                        self.setState({
                            error: (typeof response.data === 'string' && response.data)
                                ? response.data
                                : (i18n.fallbackError || __('Failed to link post.', 'social-integration-for-bluesky')),
                            isLoading: false
                        });
                    }
                })
                .catch(function () {
                    self.setState({
                        error:     i18n.networkError || __('Network error. Please try again.', 'social-integration-for-bluesky'),
                        isLoading: false
                    });
                });
        };

        BlueSkyLinkPanel.prototype.render = function () {
            // Only show for published posts
            if (this.props.postStatus !== 'publish') {
                return null;
            }

            var self     = this;
            var url      = this.state.url;
            var isLoading= this.state.isLoading;
            var error    = this.state.error;
            var success  = this.state.success;
            var disabled = isLoading || success;

            return createElement(
                PluginDocumentSettingPanel,
                {
                    name:      'bluesky-link-panel',
                    title:     i18n.panelTitle || __('Link to Bluesky', 'social-integration-for-bluesky'),
                    className: 'bluesky-link-panel'
                },

                // Description
                createElement('p', {
                    style: { fontSize: '13px', color: '#757575', marginBottom: '12px', marginTop: '0' }
                }, i18n.description || __('Already published this post on Bluesky? Paste the URL to link it.', 'social-integration-for-bluesky')),

                // Error notice
                error && createElement(Notice, {
                    status:        'error',
                    isDismissible: true,
                    onRemove:      function () { self.setState({ error: null }); },
                    style:         { marginBottom: '12px' }
                }, error),

                // Success notice
                success && createElement(Notice, {
                    status:        'success',
                    isDismissible: false,
                    style:         { marginBottom: '12px' }
                }, i18n.successMsg || __('Post linked! Reloading\u2026', 'social-integration-for-bluesky')),

                // URL input
                createElement(TextControl, {
                    label:       i18n.label || __('Bluesky Post URL', 'social-integration-for-bluesky'),
                    value:       url,
                    onChange:    function (val) { self.setState({ url: val, error: null }); },
                    placeholder: i18n.placeholder || 'https://bsky.app/profile/handle/post/\u2026',
                    type:        'url',
                    disabled:    disabled
                }),

                // Link button
                createElement(Button, {
                    variant:  'primary',
                    onClick:  this.handleLink,
                    isBusy:   isLoading,
                    disabled: !url || disabled,
                    style:    { marginTop: '4px', width: '100%', justifyContent: 'center' }
                }, isLoading
                    ? (i18n.linking || __('Linking\u2026', 'social-integration-for-bluesky'))
                    : (i18n.button  || __('Link to Bluesky', 'social-integration-for-bluesky'))
                )
            );
        };

        return BlueSkyLinkPanel;
    }(Component));

    var ConnectedPanel = compose([
        withSelect(function (select) {
            var editor = select('core/editor');
            return {
                postStatus: editor.getEditedPostAttribute('status') || ''
            };
        })
    ])(BlueSkyLinkPanel);

    registerPlugin('bluesky-link-panel', {
        render: ConnectedPanel,
        icon:   null
    });

})(window.wp);
