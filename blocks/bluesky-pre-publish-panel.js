/**
 * Bluesky Pre-Publish Panel
 * Adds a preview panel to the Gutenberg pre-publish checklist
 */

(function (wp) {
    const { registerPlugin } = wp.plugins;
    const { PluginPrePublishPanel } = wp.editPost;
    const { Component, Fragment } = wp.element;
    const { withSelect, withDispatch } = wp.data;
    const { Spinner, CheckboxControl } = wp.components;
    const { __ } = wp.i18n;
    const { compose } = wp.compose;

    class BlueSkyPrePublishPanel extends Component {
        constructor(props) {
            super(props);
            this.state = {
                preview: null,
                loading: true,
                error: null
            };
            this.loadPreview = this.loadPreview.bind(this);
            this.handleDontSyndicateChange = this.handleDontSyndicateChange.bind(this);
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

            // Auto-refresh when pre-publish panel opens
            if (this.props.isPublishSidebarOpened && !prevProps.isPublishSidebarOpened) {
                if (currentDontSyndicate !== '1') {
                    this.setState({ loading: true, error: null });
                    this.loadPreview();
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

        renderAccountSelection() {
            const multiAccountEnabled = window.blueskyPrePublishData && window.blueskyPrePublishData.multiAccountEnabled;
            const accounts = window.blueskyPrePublishData && window.blueskyPrePublishData.accounts || [];

            if (!multiAccountEnabled || accounts.length === 0) {
                return null;
            }

            const { meta, updateMeta, postCategories } = this.props;
            const selectedAccountsJson = meta._bluesky_syndication_accounts || '';
            let selectedAccounts = [];

            try {
                selectedAccounts = selectedAccountsJson ? JSON.parse(selectedAccountsJson) : [];
            } catch (e) {
                selectedAccounts = [];
            }

            const dontSyndicate = meta._bluesky_dont_syndicate === '1';

            // Only show auto-syndicate accounts
            const visibleAccounts = accounts.filter(account => account.auto_syndicate);

            return wp.element.createElement('div', {
                className: 'bluesky-account-selection',
                style: {
                    marginTop: '12px',
                    paddingTop: '12px',
                    borderTop: '1px solid #ddd',
                    opacity: dontSyndicate ? 0.5 : 1
                }
            },
                wp.element.createElement('p', {
                    style: { fontWeight: 600, marginBottom: '8px', fontSize: '13px' }
                }, __('Syndicate to:', 'social-integration-for-bluesky')),
                visibleAccounts.map(account => {
                    const isChecked = selectedAccounts.includes(account.id);
                    const rules = account.category_rules || { include: [], exclude: [] };
                    const hasRules = (rules.include && rules.include.length > 0) || (rules.exclude && rules.exclude.length > 0);
                    const matchesRules = this.accountMatchesCategories(account, postCategories || []);

                    return wp.element.createElement('div', { key: account.id },
                        wp.element.createElement(CheckboxControl, {
                            label: `${account.name} (@${account.handle})`,
                            checked: isChecked,
                            disabled: dontSyndicate,
                            onChange: (checked) => {
                                let newSelection = [...selectedAccounts];
                                if (checked) {
                                    if (!newSelection.includes(account.id)) {
                                        newSelection.push(account.id);
                                    }
                                } else {
                                    newSelection = newSelection.filter(id => id !== account.id);
                                }
                                updateMeta({
                                    _bluesky_syndication_accounts: JSON.stringify(newSelection)
                                });
                            }
                        }),
                        hasRules && !matchesRules && isChecked && wp.element.createElement('p', {
                            style: { color: '#dba617', fontSize: '12px', marginTop: '-8px', marginBottom: '8px', marginLeft: '28px' }
                        }, __('Category rules will prevent syndication to this account.', 'social-integration-for-bluesky'))
                    );
                })
            );
        }

        accountMatchesCategories(account, postCategoryIds) {
            const rules = account.category_rules || { include: [], exclude: [] };
            const includeRules = rules.include || [];
            const excludeRules = rules.exclude || [];

            if (includeRules.length === 0 && excludeRules.length === 0) {
                return true;
            }

            if (excludeRules.length > 0) {
                for (let i = 0; i < postCategoryIds.length; i++) {
                    if (excludeRules.indexOf(postCategoryIds[i]) !== -1) {
                        return false;
                    }
                }
            }

            if (includeRules.length > 0) {
                if (postCategoryIds.length === 0) {
                    return false;
                }
                for (let j = 0; j < postCategoryIds.length; j++) {
                    if (includeRules.indexOf(postCategoryIds[j]) !== -1) {
                        return true;
                    }
                }
                return false;
            }

            return true;
        }

        renderEditableSyndicationText() {
            const { meta, updateMeta, title, excerpt } = this.props;
            const customText = meta._bluesky_syndication_text || '';

            // Generate default text if custom text is empty
            let displayText = customText;
            if (!customText) {
                // Same logic as sidebar panel - generate from title + excerpt
                const postTitle = title || '';
                const postExcerpt = excerpt || '';
                displayText = postTitle;
                if (postExcerpt) {
                    displayText += '\n\n' + postExcerpt;
                }
            }

            // Get character count status
            const countStatus = window.BlueSkyCharacterCounter
                ? window.BlueSkyCharacterCounter.getCountStatus(displayText, 300)
                : { count: displayText.length, isOverLimit: displayText.length > 300 };

            const countColor = countStatus.isOverLimit ? '#d63638' : '#757575';

            return wp.element.createElement('div', {
                style: {
                    borderTop: '1px solid #ddd',
                    marginTop: '12px',
                    paddingTop: '12px'
                }
            },
                wp.element.createElement('p', {
                    style: { fontWeight: 600, fontSize: '13px', marginBottom: '8px' }
                }, __('Post text:', 'social-integration-for-bluesky')),
                wp.element.createElement('textarea', {
                    value: customText,
                    onChange: (e) => {
                        updateMeta({ _bluesky_syndication_text: e.target.value });
                    },
                    placeholder: __('Auto-generated from title and excerpt', 'social-integration-for-bluesky'),
                    rows: 4,
                    style: {
                        width: '100%',
                        padding: '8px',
                        fontSize: '13px',
                        borderRadius: '2px',
                        border: '1px solid #8c8f94'
                    }
                }),
                wp.element.createElement('div', {
                    style: {
                        display: 'flex',
                        justifyContent: 'space-between',
                        alignItems: 'center',
                        marginTop: '4px'
                    }
                },
                    wp.element.createElement('span', {
                        style: { color: countColor, fontSize: '12px' }
                    }, `${countStatus.count} / 300`),
                    customText && wp.element.createElement('button', {
                        type: 'button',
                        className: 'components-button is-link is-small',
                        onClick: () => {
                            updateMeta({ _bluesky_syndication_text: '' });
                        },
                        style: { fontSize: '12px' }
                    }, __('Reset to default', 'social-integration-for-bluesky'))
                )
            );
        }

        handleDontSyndicateChange(checked) {
            this.props.updateMeta({
                _bluesky_dont_syndicate: checked ? '1' : ''
            });
        }

        render() {
            const { loading, preview, error } = this.state;
            const dontSyndicate = this.props.meta._bluesky_dont_syndicate === '1';

            const blueskyIcon = wp.element.createElement('svg', {
                width: 24,
                height: 24,
                viewBox: '0 0 166 146',
                xmlns: 'http://www.w3.org/2000/svg'
            }, wp.element.createElement('path', {
                d: 'M36.454 10.4613C55.2945 24.5899 75.5597 53.2368 83 68.6104C90.4409 53.238 110.705 24.5896 129.546 10.4613C143.14 0.26672 165.167 -7.6213 165.167 17.4788C165.167 22.4916 162.289 59.5892 160.602 65.6118C154.736 86.5507 133.361 91.8913 114.348 88.6589C147.583 94.3091 156.037 113.025 137.779 131.74C103.101 167.284 87.9374 122.822 84.05 111.429C83.3377 109.34 83.0044 108.363 82.9995 109.194C82.9946 108.363 82.6613 109.34 81.949 111.429C78.0634 122.822 62.8997 167.286 28.2205 131.74C9.96137 113.025 18.4158 94.308 51.6513 88.6589C32.6374 91.8913 11.2622 86.5507 5.39715 65.6118C3.70956 59.5886 0.832367 22.4911 0.832367 17.4788C0.832367 -7.6213 22.8593 0.26672 36.453 10.4613H36.454Z',
                fill: '#1185FE'
            }));

            return wp.element.createElement(
                PluginPrePublishPanel,
                {
                    title: __('Bluesky Syndication', 'social-integration-for-bluesky'),
                    icon: blueskyIcon,
                    className: 'bluesky-pre-publish-panel',
                    initialOpen: true
                },
                wp.element.createElement('div', { className: 'bluesky-pre-publish-content' },

                    // Don't syndicate toggle
                    wp.element.createElement(CheckboxControl, {
                        label: __("Don't syndicate this post", 'social-integration-for-bluesky'),
                        checked: dontSyndicate,
                        onChange: this.handleDontSyndicateChange
                    }),

                    // Account selection (hidden when syndication disabled)
                    !dontSyndicate && this.renderAccountSelection(),

                    // Editable syndication text (hidden when syndication disabled)
                    !dontSyndicate && this.renderEditableSyndicationText(),

                    // Preview section (hidden when syndication disabled)
                    !dontSyndicate && loading && wp.element.createElement('div', {
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

                    !dontSyndicate && error && wp.element.createElement('div', {
                        className: 'bluesky-preview-error',
                        style: {
                            color: '#d63638',
                            padding: '10px',
                            textAlign: 'center'
                        }
                    }, error),

                    !dontSyndicate && !loading && !error && preview && wp.element.createElement('div', {
                        className: 'bluesky-preview-wrapper',
                        dangerouslySetInnerHTML: { __html: preview }
                    }),

                    !dontSyndicate && !loading && !error && wp.element.createElement('div', {
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
    const BlueSkyPrePublishPanelWithData = compose([
        withSelect((select) => {
            const editor = select('core/editor');
            const editPost = select('core/edit-post');
            const postId = editor.getCurrentPostId();
            const title = editor.getEditedPostAttribute('title');
            const content = editor.getEditedPostAttribute('content');
            const excerpt = editor.getEditedPostAttribute('excerpt');
            const meta = editor.getEditedPostAttribute('meta') || {};
            const postCategories = editor.getEditedPostAttribute('categories') || [];
            const isPublishSidebarOpened = editPost ? editPost.isPublishSidebarOpened() : false;

            return {
                postId,
                title,
                content,
                excerpt,
                meta,
                postCategories,
                isPublishSidebarOpened
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
    ])(BlueSkyPrePublishPanel);

    // Register the plugin
    registerPlugin('bluesky-pre-publish-panel', {
        render: BlueSkyPrePublishPanelWithData,
        icon: null
    });

})(window.wp);
