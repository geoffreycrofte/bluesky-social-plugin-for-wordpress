"use strict";

(function(blocks, element, components, i18n, editor, serverSideRender) {
    const el = element.createElement;
    const { __ } = i18n;
    const { InspectorControls } = editor;
    const { PanelBody, ToggleControl, SelectControl, RangeControl } = components;
    const ServerSideRender = serverSideRender;

    const edit = function(props) {
        const { attributes, setAttributes } = props;

        return [
            el(InspectorControls, { key: 'inspector' },
                el(PanelBody, { 
                    key: 'formatting-options',
                    title: __('Posts Display Options', 'social-integration-for-bluesky')
                },
                    el(ToggleControl, {
                        key: 'embeds-toggle',
                        label: __('Display Embeds', 'social-integration-for-bluesky'),
                        checked: attributes.displayembeds,
                        onChange: function(value) { 
                            setAttributes({ displayembeds: value });
                        }
                    }),
                    el(ToggleControl, {
                        key: 'replied-toggle',
                        label: __('Hide Replies', 'social-integration-for-bluesky'),
                        checked: attributes.noreplies,
                        onChange: function(value) { 
                            setAttributes({ noreplies: value });
                        }
                    }),
                    el(SelectControl, {
                        key: 'theme-select',
                        label: __('Theme', 'social-integration-for-bluesky'),
                        value: attributes.theme,
                        options: [
                            { label: __('System Preference', 'social-integration-for-bluesky'), value: 'system' },
                            { label: __('Light', 'social-integration-for-bluesky'), value: 'light' },
                            { label: __('Dark', 'social-integration-for-bluesky'), value: 'dark' }
                        ],
                        onChange: function(value) {
                            setAttributes({ theme: value });
                        }
                    }),
                    el(RangeControl, {
                        key: 'posts-number',
                        label: __('Number of Posts', 'social-integration-for-bluesky'),
                        value: attributes.numberofposts,
                        onChange: function(value) {
                            setAttributes({ numberofposts: value });
                        },
                        min: 1,
                        max: 10
                    })
                )
            ),
            el(ServerSideRender, {
                block: 'bluesky-social/posts',
                attributes: attributes,
                className: props.className ?? 'bluesky-posts-block'
            })
        ];
    };

    blocks.registerBlockType('bluesky-social/posts', {
        title: __('BlueSky Posts Feed', 'social-integration-for-bluesky'),
        icon: 'rss',
        category: 'widgets',
        keywords: [
            __('social', 'social-integration-for-bluesky'),
            __('feed', 'social-integration-for-bluesky'),
            __('posts', 'social-integration-for-bluesky')
        ],
        styles: [
            {
                name: 'default',
                label: __('Default'),
                isDefault: true
            },
            {
                name: 'compact',
                label: __('Compact')
            },
            {
                name: 'expanded',
                label: __('Expanded')
            }
        ],
        attributes: {
            displayembeds: {
                type: 'boolean',
                default: true
            },
            noreplies: {
                type: 'boolean',
                default: true
            },
            theme: {
                type: 'string',
                default: 'system'
            },
            numberofposts: {
                type: 'integer',
                default: 5
            }
        },
        supports: {
            anchor: true,
            align: true,
            ariaLabel: true,
            customClassName: true
        },
        edit: edit,
        save: function() {
            return null;
        }
    });
}(
    window.wp.blocks,
    window.wp.element,
    window.wp.components,
    window.wp.i18n,
    window.wp.blockEditor,
    window.wp.serverSideRender
));