/**
 * Bluesky Pre-Publish Panel
 * Adds a preview panel to the Gutenberg pre-publish checklist
 */

(function (wp) {
    const { registerPlugin } = wp.plugins;
    const { PluginPrePublishPanel } = wp.editPost;
    const { Component, Fragment } = wp.element;
    const { withSelect } = wp.data;
    const { Spinner } = wp.components;
    const { __ } = wp.i18n;

    class BlueSkyPrePublishPanel extends Component {
        constructor(props) {
            super(props);
            this.state = {
                preview: null,
                loading: true,
                error: null
            };
            this.loadPreview = this.loadPreview.bind(this);
        }

        componentDidMount() {
            // Check if syndication is enabled
            const dontSyndicate = this.props.meta._bluesky_dont_syndicate;
            if (dontSyndicate !== '1') {
                this.loadPreview();
            } else {
                this.setState({ loading: false });
            }
        }

        componentDidUpdate(prevProps) {
            // Reload preview if syndication setting changes
            const prevDontSyndicate = prevProps.meta._bluesky_dont_syndicate;
            const currentDontSyndicate = this.props.meta._bluesky_dont_syndicate;

            if (prevDontSyndicate !== currentDontSyndicate) {
                if (currentDontSyndicate !== '1') {
                    this.setState({ loading: true, error: null });
                    this.loadPreview();
                } else {
                    this.setState({ preview: null, loading: false, error: null });
                }
            }
        }

        loadPreview() {
            const { postId, title, content, excerpt } = this.props;

            // Don't load if syndication is disabled
            const dontSyndicate = this.props.meta._bluesky_dont_syndicate;
            if (dontSyndicate === '1') {
                this.setState({ preview: null, loading: false });
                return;
            }

            this.setState({ loading: true, error: null });

            const data = new FormData();
            data.append('action', 'get_bluesky_post_preview');
            data.append('post_id', postId);
            data.append('title', title || '');
            data.append('content', content || '');
            data.append('excerpt', excerpt || '');
            data.append('nonce', blueskyPrePublishData.nonce);

            fetch(ajaxurl, {
                method: 'POST',
                credentials: 'same-origin',
                body: data
            })
                .then(response => response.json())
                .then(response => {
                    if (response.success && response.data) {
                        this.setState({
                            preview: response.data.html,
                            loading: false,
                            error: null
                        });
                    } else {
                        this.setState({
                            preview: null,
                            loading: false,
                            error: __('Unable to generate preview.', 'social-integration-for-bluesky')
                        });
                    }
                })
                .catch(error => {
                    console.error('Bluesky preview error:', error);
                    this.setState({
                        preview: null,
                        loading: false,
                        error: __('Error loading preview.', 'social-integration-for-bluesky')
                    });
                });
        }

        render() {
            const { loading, preview, error } = this.state;
            const dontSyndicate = this.props.meta._bluesky_dont_syndicate;

            // Don't show panel if syndication is disabled
            if (dontSyndicate === '1') {
                return wp.element.createElement(
                    PluginPrePublishPanel,
                    {
                        title: __('Bluesky Syndication', 'social-integration-for-bluesky'),
                        icon: wp.element.createElement('svg', {
                            width: 24,
                            height: 24,
                            viewBox: '0 0 166 146',
                            xmlns: 'http://www.w3.org/2000/svg'
                        }, wp.element.createElement('path', {
                            d: 'M36.454 10.4613C55.2945 24.5899 75.5597 53.2368 83 68.6104C90.4409 53.238 110.705 24.5896 129.546 10.4613C143.14 0.26672 165.167 -7.6213 165.167 17.4788C165.167 22.4916 162.289 59.5892 160.602 65.6118C154.736 86.5507 133.361 91.8913 114.348 88.6589C147.583 94.3091 156.037 113.025 137.779 131.74C103.101 167.284 87.9374 122.822 84.05 111.429C83.3377 109.34 83.0044 108.363 82.9995 109.194C82.9946 108.363 82.6613 109.34 81.949 111.429C78.0634 122.822 62.8997 167.286 28.2205 131.74C9.96137 113.025 18.4158 94.308 51.6513 88.6589C32.6374 91.8913 11.2622 86.5507 5.39715 65.6118C3.70956 59.5886 0.832367 22.4911 0.832367 17.4788C0.832367 -7.6213 22.8593 0.26672 36.453 10.4613H36.454Z',
                            fill: '#1185FE'
                        })),
                        className: 'bluesky-pre-publish-panel'
                    },
                    wp.element.createElement('p', {
                        style: { color: '#757575', fontSize: '13px' }
                    }, __('Syndication disabled for this post.', 'social-integration-for-bluesky'))
                );
            }

            return wp.element.createElement(
                PluginPrePublishPanel,
                {
                    title: __('Bluesky Post Preview', 'social-integration-for-bluesky'),
                    icon: wp.element.createElement('svg', {
                        width: 24,
                        height: 24,
                        viewBox: '0 0 166 146',
                        xmlns: 'http://www.w3.org/2000/svg'
                    }, wp.element.createElement('path', {
                        d: 'M36.454 10.4613C55.2945 24.5899 75.5597 53.2368 83 68.6104C90.4409 53.238 110.705 24.5896 129.546 10.4613C143.14 0.26672 165.167 -7.6213 165.167 17.4788C165.167 22.4916 162.289 59.5892 160.602 65.6118C154.736 86.5507 133.361 91.8913 114.348 88.6589C147.583 94.3091 156.037 113.025 137.779 131.74C103.101 167.284 87.9374 122.822 84.05 111.429C83.3377 109.34 83.0044 108.363 82.9995 109.194C82.9946 108.363 82.6613 109.34 81.949 111.429C78.0634 122.822 62.8997 167.286 28.2205 131.74C9.96137 113.025 18.4158 94.308 51.6513 88.6589C32.6374 91.8913 11.2622 86.5507 5.39715 65.6118C3.70956 59.5886 0.832367 22.4911 0.832367 17.4788C0.832367 -7.6213 22.8593 0.26672 36.453 10.4613H36.454Z',
                        fill: '#1185FE'
                    })),
                    className: 'bluesky-pre-publish-panel',
                    initialOpen: true
                },
                wp.element.createElement('div', { className: 'bluesky-pre-publish-content' },
                    wp.element.createElement('p', {
                        style: { color: '#757575', fontSize: '13px', marginTop: 0 }
                    }, __('This is what will be posted to Bluesky:', 'social-integration-for-bluesky')),

                    loading && wp.element.createElement('div', {
                        className: 'bluesky-preview-loading',
                        style: {
                            display: 'flex',
                            alignItems: 'center',
                            gap: '8px',
                            padding: '20px',
                            justifyContent: 'center'
                        }
                    },
                        wp.element.createElement(Spinner),
                        wp.element.createElement('span', { style: { color: '#757575' } },
                            __('Loading preview...', 'social-integration-for-bluesky')
                        )
                    ),

                    error && wp.element.createElement('div', {
                        className: 'bluesky-preview-error',
                        style: {
                            color: '#d63638',
                            padding: '10px',
                            textAlign: 'center'
                        }
                    }, error),

                    !loading && !error && preview && wp.element.createElement('div', {
                        className: 'bluesky-preview-wrapper',
                        dangerouslySetInnerHTML: { __html: preview }
                    }),

                    !loading && !error && wp.element.createElement('div', {
                        style: { marginTop: '12px' }
                    },
                        wp.element.createElement('button', {
                            type: 'button',
                            className: 'components-button is-secondary is-small',
                            onClick: this.loadPreview,
                            style: {
                                display: 'flex',
                                alignItems: 'center',
                                gap: '4px'
                            }
                        },
                            wp.element.createElement('svg', {
                                width: 16,
                                height: 16,
                                viewBox: '0 0 24 24',
                                xmlns: 'http://www.w3.org/2000/svg'
                            }, wp.element.createElement('path', {
                                d: 'M17.91 14c-.478 2.833-2.943 5-5.91 5-3.308 0-6-2.692-6-6s2.692-6 6-6h2.172l-2.086 2.086L13.5 10.5 18 6l-4.5-4.5-1.414 1.414L14.172 5H12c-4.418 0-8 3.582-8 8s3.582 8 8 8c4.08 0 7.438-3.055 7.93-7h-2.02z',
                                fill: 'currentColor'
                            })),
                            __('Refresh Preview', 'social-integration-for-bluesky')
                        )
                    )
                )
            );
        }
    }

    // Connect to WordPress data store
    const BlueSkyPrePublishPanelWithData = withSelect((select) => {
        const editor = select('core/editor');
        const postId = editor.getCurrentPostId();
        const title = editor.getEditedPostAttribute('title');
        const content = editor.getEditedPostAttribute('content');
        const excerpt = editor.getEditedPostAttribute('excerpt');
        const meta = editor.getEditedPostAttribute('meta') || {};

        return {
            postId,
            title,
            content,
            excerpt,
            meta
        };
    })(BlueSkyPrePublishPanel);

    // Register the plugin
    registerPlugin('bluesky-pre-publish-panel', {
        render: BlueSkyPrePublishPanelWithData,
        icon: null
    });

})(window.wp);
