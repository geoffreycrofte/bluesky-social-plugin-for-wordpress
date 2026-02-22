/**
 * Bluesky Sidebar Panel
 * Provides editable syndication text in Gutenberg document settings
 */

(function (wp) {
    'use strict';

    const { registerPlugin } = wp.plugins;
    const { PluginDocumentSettingPanel } = wp.editPost;
    const { Component } = wp.element;
    const { withSelect, withDispatch } = wp.data;
    const { compose } = wp.compose;
    const { __ } = wp.i18n;

    class BlueSkyDocumentPanel extends Component {
        constructor(props) {
            super(props);
            this.handleTextChange = this.handleTextChange.bind(this);
            this.handleReset = this.handleReset.bind(this);
        }

        /**
         * Generate default text from title and excerpt
         */
        getDefaultText() {
            const { title, excerpt } = this.props;
            const maxLength = 300;

            if (!title) {
                return '';
            }

            let defaultText = title;

            // Add excerpt if there's space
            if (excerpt) {
                const excerptClean = excerpt.replace(/<[^>]+>/g, '').trim();
                const spaceForExcerpt = maxLength - defaultText.length - 10;

                if (spaceForExcerpt > 50 && excerptClean) {
                    let excerptTrimmed = excerptClean.substring(0, spaceForExcerpt);
                    const lastSpace = excerptTrimmed.lastIndexOf(' ');
                    if (lastSpace !== -1 && lastSpace > 30) {
                        excerptTrimmed = excerptTrimmed.substring(0, lastSpace);
                    }
                    defaultText += '\n\n' + excerptTrimmed.trim() + '...';
                }
            }

            return defaultText;
        }

        /**
         * Handle text change
         */
        handleTextChange(event) {
            const { updateMeta } = this.props;
            updateMeta({
                _bluesky_syndication_text: event.target.value
            });
        }

        /**
         * Handle reset to default
         */
        handleReset(event) {
            event.preventDefault();
            const { updateMeta } = this.props;
            updateMeta({
                _bluesky_syndication_text: ''
            });
        }

        /**
         * Get character count display
         */
        getCharacterCount(text) {
            if (!window.BlueSkyCharacterCounter) {
                return null;
            }

            const status = window.BlueSkyCharacterCounter.getCountStatus(text, 300);
            const countStyle = {
                fontSize: '13px',
                marginTop: '8px',
                color: status.isOverLimit ? '#d63638' : '#757575',
                fontWeight: status.isOverLimit ? 600 : 400
            };

            return wp.element.createElement('div', {
                style: countStyle,
                'aria-live': 'polite'
            }, status.count + ' / 300');
        }

        render() {
            const { meta, dontSyndicate } = this.props;

            // Don't show panel when syndication is disabled
            if (dontSyndicate === '1') {
                return null;
            }

            const customText = meta._bluesky_syndication_text || '';
            const displayText = customText || this.getDefaultText();
            const isUsingDefault = !customText;

            return wp.element.createElement(
                PluginDocumentSettingPanel,
                {
                    name: 'bluesky-syndication-panel',
                    title: __('Bluesky Post Text', 'social-integration-for-bluesky'),
                    className: 'bluesky-sidebar-panel'
                },
                wp.element.createElement('div', {
                    style: { marginTop: '12px' }
                },
                    wp.element.createElement('textarea', {
                        className: 'components-textarea-control__input',
                        rows: 6,
                        value: customText,
                        onChange: this.handleTextChange,
                        placeholder: __('Auto-generated from title and excerpt', 'social-integration-for-bluesky'),
                        style: {
                            width: '100%',
                            fontSize: '13px',
                            fontFamily: '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif'
                        }
                    }),

                    this.getCharacterCount(displayText),

                    !isUsingDefault && wp.element.createElement('div', {
                        style: { marginTop: '12px' }
                    },
                        wp.element.createElement('button', {
                            type: 'button',
                            className: 'components-button is-link is-destructive',
                            onClick: this.handleReset,
                            style: {
                                fontSize: '13px',
                                textDecoration: 'none'
                            }
                        }, __('Reset to default', 'social-integration-for-bluesky'))
                    ),

                    isUsingDefault && wp.element.createElement('div', {
                        style: {
                            marginTop: '12px',
                            padding: '8px',
                            backgroundColor: '#f0f0f1',
                            borderRadius: '4px',
                            fontSize: '12px',
                            color: '#757575'
                        }
                    },
                        wp.element.createElement('strong', null,
                            __('Preview:', 'social-integration-for-bluesky')
                        ),
                        wp.element.createElement('div', {
                            style: {
                                marginTop: '4px',
                                whiteSpace: 'pre-wrap',
                                wordBreak: 'break-word'
                            }
                        }, displayText || __('(Title and excerpt will be used)', 'social-integration-for-bluesky'))
                    )
                )
            );
        }
    }

    // Connect to WordPress data store
    const ConnectedPanel = compose([
        withSelect((select) => {
            const editor = select('core/editor');
            const meta = editor.getEditedPostAttribute('meta') || {};
            const title = editor.getEditedPostAttribute('title') || '';
            const excerpt = editor.getEditedPostAttribute('excerpt') || '';
            const dontSyndicate = meta._bluesky_dont_syndicate || '';

            return {
                meta,
                title,
                excerpt,
                dontSyndicate
            };
        }),
        withDispatch((dispatch) => {
            const { editPost } = dispatch('core/editor');
            return {
                updateMeta: (newMeta) => {
                    editPost({ meta: newMeta });
                }
            };
        })
    ])(BlueSkyDocumentPanel);

    // Register the plugin
    registerPlugin('bluesky-document-panel', {
        render: ConnectedPanel,
        icon: null
    });

})(window.wp);
