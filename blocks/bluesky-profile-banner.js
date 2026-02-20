"use strict";

(function (blocks, element, components, i18n, blockEditor, serverSideRender) {
  const el = element.createElement;
  const { __ } = i18n;
  const { InspectorControls, useBlockProps } = blockEditor;
  const { PanelBody, SelectControl } = components;
  const ServerSideRender = serverSideRender;

  const edit = function (props) {
    const { attributes, setAttributes } = props;
    const blockProps = useBlockProps();

    // Check if we should show account selector
    const showAccountSelector =
      window.blueskyBlockData &&
      window.blueskyBlockData.accounts &&
      window.blueskyBlockData.accounts.length > 1;

    return el(
      "div",
      blockProps,
      el(
        InspectorControls,
        { key: "inspector" },
        showAccountSelector
          ? el(
              PanelBody,
              {
                key: "account-options",
                title: __("Account", "social-integration-for-bluesky"),
              },
              el(SelectControl, {
                key: "account-select",
                label: __("Display Account", "social-integration-for-bluesky"),
                value: attributes.accountId,
                options: window.blueskyBlockData.accounts,
                onChange: function (value) {
                  setAttributes({ accountId: value });
                },
              }),
            )
          : null,
        el(
          PanelBody,
          {
            key: "banner-options",
            title: __(
              "Banner Display Options",
              "social-integration-for-bluesky",
            ),
          },
          el(SelectControl, {
            key: "layout-select",
            label: __("Layout", "social-integration-for-bluesky"),
            value: attributes.layout,
            options: [
              {
                label: __("Full Banner", "social-integration-for-bluesky"),
                value: "full",
              },
              {
                label: __("Compact Card", "social-integration-for-bluesky"),
                value: "compact",
              },
            ],
            onChange: function (value) {
              setAttributes({ layout: value });
            },
          }),
          el(SelectControl, {
            key: "theme-select",
            label: __("Theme", "social-integration-for-bluesky"),
            value: attributes.theme,
            options: [
              {
                label: __(
                  "System Preference",
                  "social-integration-for-bluesky",
                ),
                value: "system",
              },
              {
                label: __("Light", "social-integration-for-bluesky"),
                value: "light",
              },
              {
                label: __("Dark", "social-integration-for-bluesky"),
                value: "dark",
              },
            ],
            onChange: function (value) {
              setAttributes({ theme: value });
            },
          }),
        ),
      ),
      el(ServerSideRender, {
        key: "server-side-render",
        block: "bluesky-social/profile-banner",
        attributes: attributes,
      }),
    );
  };

  blocks.registerBlockType("bluesky-social/profile-banner", {
    title: __("Bluesky Profile Banner", "social-integration-for-bluesky"),
    icon: "admin-users",
    category: "widgets",
    keywords: [
      __("social", "social-integration-for-bluesky"),
      __("banner", "social-integration-for-bluesky"),
      __("header", "social-integration-for-bluesky"),
    ],
    attributes: {
      layout: {
        type: "string",
        default: "full",
      },
      accountId: {
        type: "string",
        default: "",
      },
      theme: {
        type: "string",
        default: "system",
      },
    },
    supports: {
      anchor: true,
      align: true,
      ariaLabel: true,
      customClassName: true,
      html: false,
    },
    edit: edit,

    save: function () {
      return null;
    },
  });
})(
  window.wp.blocks,
  window.wp.element,
  window.wp.components,
  window.wp.i18n,
  window.wp.blockEditor,
  window.wp.serverSideRender,
);
