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
                    title: __('Posts Display Options', 'bluesky-social')
                },
                    el(ToggleControl, {
                        key: 'embeds-toggle',
                        label: __('Display Embeds', 'bluesky-social'),
                        checked: attributes.displayEmbeds,
                        onChange: function(value) { 
                            setAttributes({ displayEmbeds: value });
                        }
                    }),
                    el(SelectControl, {
                        key: 'theme-select',
                        label: __('Theme', 'bluesky-social'),
                        value: attributes.theme,
                        options: [
                            { label: __('System Preference', 'bluesky-social'), value: 'system' },
                            { label: __('Light', 'bluesky-social'), value: 'light' },
                            { label: __('Dark', 'bluesky-social'), value: 'dark' }
                        ],
                        onChange: function(value) {
                            setAttributes({ theme: value });
                        }
                    }),
                    el(RangeControl, {
                        key: 'posts-number',
                        label: __('Number of Posts', 'bluesky-social'),
                        value: attributes.numberOfPosts,
                        onChange: function(value) {
                            setAttributes({ numberOfPosts: value });
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
        title: __('BlueSky Posts Feed', 'bluesky-social'),
        icon: 'rss',
        category: 'widgets',
        keywords: [
            __('social', 'bluesky-social'),
            __('feed', 'bluesky-social'),
            __('posts', 'bluesky-social')
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
            displayEmbeds: {
                type: 'boolean',
                default: true
            },
            theme: {
                type: 'string',
                default: 'system'
            },
            numberOfPosts: {
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