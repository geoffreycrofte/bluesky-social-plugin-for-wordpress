"use strict";

(function(blocks, element, components, i18n, editor, serverSideRender) {
    const el = element.createElement;
    const { __ } = i18n;
    const { InspectorControls } = editor;
    const { PanelBody, ToggleControl, SelectControl } = components;
    const ServerSideRender = serverSideRender;

    const edit = function(props) {
        const { attributes, setAttributes } = props;

        return [
            el(InspectorControls, { key: 'inspector' },
                el(PanelBody, { 
                    key: 'formatting-options',
                    title: __('Profile Display Options', 'bluesky-social')
                },
                    el(ToggleControl, {
                        key: 'banner-toggle',
                        label: __('Display Banner', 'bluesky-social'),
                        checked: attributes.displayBanner,
                        onChange: function(value) { 
                            setAttributes({ displayBanner: value });
                        }
                    }),
                    el(ToggleControl, {
                        key: 'avatar-toggle',
                        label: __('Display Avatar', 'bluesky-social'),
                        checked: attributes.displayAvatar,
                        onChange: function(value) {
                            setAttributes({ displayAvatar: value });
                        }
                    }),
                    el(ToggleControl, {
                        key: 'counters-toggle',
                        label: __('Display Counters', 'bluesky-social'),
                        checked: attributes.displayCounters,
                        onChange: function(value) {
                            setAttributes({ displayCounters: value });
                        }
                    }),
                    el(ToggleControl, {
                        key: 'bio-toggle',
                        label: __('Display Bio', 'bluesky-social'),
                        checked: attributes.displayBio,
                        onChange: function(value) {
                            setAttributes({ displayBio: value });
                        }
                    }),
                    el(SelectControl, {
                        key: 'theme-select',
                        label: __('Theme', 'bluesky-social'),
                        value: attributes.theme,
                        options: [
                            { label: __('Light', 'bluesky-social'), value: 'light' },
                            { label: __('Dark', 'bluesky-social'), value: 'dark' }
                        ],
                        onChange: function(value) {
                            setAttributes({ theme: value });
                            console.log(props);
                        }
                    })
                )
            ),
            el(ServerSideRender, {
                block: 'bluesky-social/profile',
                attributes: attributes,
                className: props.className ?? 'bluesky-posts-block'
            })
        ];
    };

    blocks.registerBlockType('bluesky-social/profile', {
        title: __('BlueSky Profile Card', 'bluesky-social'),
        icon: 'admin-users',
        category: 'widgets',
        keywords: [
            __( 'social', 'bluesky-social' ),
            __( 'account', 'bluesky-social' ),
            __( 'card', 'bluesky-social' )
        ],
        styles: [
            // Mark style as default.
            {
                name: 'default',
                label: __( 'Rounded' ),
                isDefault: true
            },
            {
                name: 'outline',
                label: __( 'Outline' )
            },
            {
                name: 'squared',
                label: __( 'Squared' )
            },
        ],
        attributes: {
            displayBanner: {
                type: 'boolean',
                default: true
            },
            displayAvatar: {
                type: 'boolean',
                default: true
            },
            displayCounters: {
                type: 'boolean',
                default: true
            },
            displayBio: {
                type: 'boolean',
                default: true
            },
            theme: {
                type: 'string',
                default: 'light'
            }
        },
        supports: {
            anchor: true,
            align: true,
            ariaLabel: true,
            customClassName: true,
            html: false,
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