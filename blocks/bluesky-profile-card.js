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
                    title: __('Profile Display Options', 'social-integration-for-bluesky')
                },
                    el(ToggleControl, {
                        key: 'banner-toggle',
                        label: __('Display Banner', 'social-integration-for-bluesky'),
                        checked: attributes.displaybanner,
                        onChange: function(value) { 
                            setAttributes({ displaybanner: value });
                        }
                    }),
                    el(ToggleControl, {
                        key: 'avatar-toggle',
                        label: __('Display Avatar', 'social-integration-for-bluesky'),
                        checked: attributes.displayavatar,
                        onChange: function(value) {
                            setAttributes({ displayavatar: value });
                        }
                    }),
                    el(ToggleControl, {
                        key: 'counters-toggle',
                        label: __('Display Counters', 'social-integration-for-bluesky'),
                        checked: attributes.displaycounters,
                        onChange: function(value) {
                            setAttributes({ displaycounters: value });
                        }
                    }),
                    el(ToggleControl, {
                        key: 'bio-toggle',
                        label: __('Display Bio', 'social-integration-for-bluesky'),
                        checked: attributes.displaybio,
                        onChange: function(value) {
                            setAttributes({ displaybio: value });
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
                            console.log(props);
                        }
                    })
                )
            ),
            el(ServerSideRender, {
                block: 'bluesky-social/profile',
                attributes: attributes,
                className: props.classname ?? 'bluesky-posts-block'
            })
        ];
    };

    blocks.registerBlockType('bluesky-social/profile', {
        title: __('BlueSky Profile Card', 'social-integration-for-bluesky'),
        icon: 'admin-users',
        category: 'widgets',
        keywords: [
            __( 'social', 'social-integration-for-bluesky' ),
            __( 'account', 'social-integration-for-bluesky' ),
            __( 'card', 'social-integration-for-bluesky' )
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
            displaybanner: {
                type: 'boolean',
                default: true
            },
            displayavatar: {
                type: 'boolean',
                default: true
            },
            displaycounters: {
                type: 'boolean',
                default: true
            },
            displaybio: {
                type: 'boolean',
                default: true
            },
            theme: {
                type: 'string',
                default: 'system'
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