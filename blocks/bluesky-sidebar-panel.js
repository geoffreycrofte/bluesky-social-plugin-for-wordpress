/**
 * Bluesky Sidebar Panel
 * Unified syndication controls in Gutenberg document settings:
 * - Don't syndicate toggle
 * - Account selection (multi-account, category-aware)
 * - Editable syndication text with character counter
 */

(function (wp) {
    'use strict';

    const { registerPlugin } = wp.plugins;
    const { PluginDocumentSettingPanel } = wp.editPost;
    const { Component } = wp.element;
    const { withSelect, withDispatch } = wp.data;
    const { compose } = wp.compose;
    const { CheckboxControl } = wp.components;
    const { __ } = wp.i18n;

    /**
     * Check if an account matches the post's categories based on its category_rules.
     * Returns true if the account should syndicate.
     */
    function accountMatchesCategories(account, postCategoryIds) {
        var rules = account.category_rules || { include: [], exclude: [] };
        var includeRules = rules.include || [];
        var excludeRules = rules.exclude || [];

        // No rules = syndicate everything
        if (includeRules.length === 0 && excludeRules.length === 0) {
            return true;
        }

        // Check exclude rules first (higher priority)
        if (excludeRules.length > 0) {
            for (var i = 0; i < postCategoryIds.length; i++) {
                if (excludeRules.indexOf(postCategoryIds[i]) !== -1) {
                    return false;
                }
            }
        }

        // Check include rules (OR logic)
        if (includeRules.length > 0) {
            if (postCategoryIds.length === 0) {
                return false;
            }
            for (var j = 0; j < postCategoryIds.length; j++) {
                if (includeRules.indexOf(postCategoryIds[j]) !== -1) {
                    return true;
                }
            }
            return false;
        }

        // Only exclude rules exist and post passed the check
        return true;
    }

    /**
     * Get which accounts should be selected based on category rules.
     */
    function getMatchingAccountIds(accounts, postCategoryIds) {
        return accounts
            .filter(function(account) {
                return account.auto_syndicate && accountMatchesCategories(account, postCategoryIds);
            })
            .map(function(account) { return account.id; });
    }

    class BlueSkyDocumentPanel extends Component {
        constructor(props) {
            super(props);
            this._lastCategoryKey = '';
            this.handleTextChange = this.handleTextChange.bind(this);
            this.handleReset = this.handleReset.bind(this);
            this.handleDontSyndicateChange = this.handleDontSyndicateChange.bind(this);
        }

        componentDidMount() {
            this.syncAccountsWithCategories();
        }

        componentDidUpdate(prevProps) {
            // Re-evaluate when categories change
            var prevCats = (prevProps.postCategories || []).slice().sort().join(',');
            var currCats = (this.props.postCategories || []).slice().sort().join(',');
            if (prevCats !== currCats) {
                this.syncAccountsWithCategories();
            }
        }

        /**
         * Auto-select accounts based on post categories and category rules.
         * Only runs when user hasn't manually overridden the selection.
         */
        syncAccountsWithCategories() {
            var accounts = window.blueskyPrePublishData && window.blueskyPrePublishData.accounts || [];
            if (accounts.length === 0) {
                return;
            }

            var postCategories = this.props.postCategories || [];
            var matching = getMatchingAccountIds(accounts, postCategories);

            this.props.updateMeta({
                _bluesky_syndication_accounts: JSON.stringify(matching)
            });
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

        handleTextChange(event) {
            this.props.updateMeta({
                _bluesky_syndication_text: event.target.value
            });
        }

        handleReset(event) {
            event.preventDefault();
            this.props.updateMeta({
                _bluesky_syndication_text: ''
            });
        }

        handleDontSyndicateChange(checked) {
            this.props.updateMeta({
                _bluesky_dont_syndicate: checked ? '1' : ''
            });
        }

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

        renderAccountSelection() {
            const multiAccountEnabled = window.blueskyPrePublishData && window.blueskyPrePublishData.multiAccountEnabled;
            const accounts = window.blueskyPrePublishData && window.blueskyPrePublishData.accounts || [];

            if (!multiAccountEnabled || accounts.length === 0) {
                return null;
            }

            const { meta, updateMeta, postCategories } = this.props;
            const dontSyndicate = meta._bluesky_dont_syndicate === '1';
            const selectedAccountsJson = meta._bluesky_syndication_accounts || '';
            let selectedAccounts = [];

            try {
                selectedAccounts = selectedAccountsJson ? JSON.parse(selectedAccountsJson) : [];
            } catch (e) {
                selectedAccounts = [];
            }

            // If no selection yet, compute from category rules
            if (selectedAccounts.length === 0 && !selectedAccountsJson) {
                selectedAccounts = getMatchingAccountIds(accounts, postCategories || []);
            }

            // Only show auto-syndicate accounts
            var visibleAccounts = accounts.filter(function(account) {
                return account.auto_syndicate;
            });

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
                visibleAccounts.map(function(account) {
                    var isChecked = selectedAccounts.includes(account.id);
                    var matchesRules = accountMatchesCategories(account, postCategories || []);
                    var hasRules = (account.category_rules && (
                        (account.category_rules.include && account.category_rules.include.length > 0) ||
                        (account.category_rules.exclude && account.category_rules.exclude.length > 0)
                    ));

                    return wp.element.createElement('div', { key: account.id },
                        wp.element.createElement(CheckboxControl, {
                            label: account.name + ' (@' + account.handle + ')',
                            checked: isChecked,
                            disabled: dontSyndicate,
                            onChange: function(checked) {
                                var newSelection = selectedAccounts.slice();
                                if (checked) {
                                    if (!newSelection.includes(account.id)) {
                                        newSelection.push(account.id);
                                    }
                                } else {
                                    newSelection = newSelection.filter(function(id) { return id !== account.id; });
                                }
                                updateMeta({
                                    _bluesky_syndication_accounts: JSON.stringify(newSelection)
                                });
                            }
                        }),
                        // Show category mismatch warning
                        hasRules && !matchesRules && isChecked && wp.element.createElement('p', {
                            style: { color: '#dba617', fontSize: '12px', marginTop: '-8px', marginBottom: '8px', marginLeft: '28px' }
                        }, __('Category rules will prevent syndication to this account.', 'social-integration-for-bluesky'))
                    );
                })
            );
        }

        render() {
            const { meta } = this.props;
            const dontSyndicate = meta._bluesky_dont_syndicate === '1';
            const customText = meta._bluesky_syndication_text || '';
            const displayText = customText || this.getDefaultText();
            const isUsingDefault = !customText;

            return wp.element.createElement(
                PluginDocumentSettingPanel,
                {
                    name: 'bluesky-syndication-panel',
                    title: __('Bluesky Syndication', 'social-integration-for-bluesky'),
                    className: 'bluesky-sidebar-panel'
                },

                // Global pause warning
                window.blueskyPrePublishData && window.blueskyPrePublishData.globalPaused && wp.element.createElement('div', {
                    className: 'bluesky-global-pause-warning'
                },
                    wp.element.createElement('strong', null,
                        __('Syndication is globally paused.', 'social-integration-for-bluesky')
                    ),
                    wp.element.createElement('a', {
                        href: window.blueskyPrePublishData.settingsUrl,
                        className: 'bluesky-global-pause-link'
                    }, __('Manage in Settings', 'social-integration-for-bluesky') + ' \u2192')
                ),

                // Don't syndicate toggle
                wp.element.createElement(CheckboxControl, {
                    label: __("Don't syndicate this post", 'social-integration-for-bluesky'),
                    checked: dontSyndicate,
                    onChange: this.handleDontSyndicateChange
                }),

                // Account selection (multi-account only)
                !dontSyndicate && this.renderAccountSelection(),

                // Editable syndication text (hidden when syndication disabled)
                !dontSyndicate && wp.element.createElement('div', {
                    style: {
                        marginTop: '12px',
                        paddingTop: '12px',
                        borderTop: '1px solid #ddd'
                    }
                },
                    wp.element.createElement('p', {
                        style: { fontWeight: 600, marginBottom: '4px', fontSize: '13px' }
                    }, __('Post text:', 'social-integration-for-bluesky')),

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
    var ConnectedPanel = compose([
        withSelect(function(select) {
            var editor = select('core/editor');
            var meta = editor.getEditedPostAttribute('meta') || {};
            var title = editor.getEditedPostAttribute('title') || '';
            var excerpt = editor.getEditedPostAttribute('excerpt') || '';
            var postCategories = editor.getEditedPostAttribute('categories') || [];

            return {
                meta: meta,
                title: title,
                excerpt: excerpt,
                postCategories: postCategories
            };
        }),
        withDispatch(function(dispatch) {
            var editPost = dispatch('core/editor').editPost;
            return {
                updateMeta: function(newMeta) {
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
