"use strict";

(function (blocks, element, components, i18n, blockEditor, serverSideRender) {
  const el = element.createElement;
  const { __ } = i18n;
  const { InspectorControls, useBlockProps } = blockEditor;
  const { PanelBody, ToggleControl, SelectControl, RangeControl } = components;
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
            key: "formatting-options",
            title: __(
              "Posts Display Options",
              "social-integration-for-bluesky",
            ),
          },
          el(ToggleControl, {
            key: "embeds-toggle",
            label: __("Display Embeds", "social-integration-for-bluesky"),
            checked: attributes.displayembeds,
            onChange: function (value) {
              setAttributes({ displayembeds: value });
            },
          }),
          el(ToggleControl, {
            key: "replies-toggle",
            label: __("Hide Replies", "social-integration-for-bluesky"),
            checked: attributes.noreplies,
            onChange: function (value) {
              setAttributes({ noreplies: value });
            },
          }),
          el(ToggleControl, {
            key: "reposts-toggle",
            label: __("Hide Reposts", "social-integration-for-bluesky"),
            checked: attributes.noreposts,
            onChange: function (value) {
              setAttributes({ noreposts: value });
            },
          }),
          el(ToggleControl, {
            key: "counters-toggle",
            label: __("Hide Counters", "social-integration-for-bluesky"),
            checked: attributes.nocounters,
            onChange: function (value) {
              setAttributes({ nocounters: value });
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
          el(RangeControl, {
            key: "posts-number",
            label: __("Number of Posts", "social-integration-for-bluesky"),
            value: attributes.numberofposts,
            onChange: function (value) {
              setAttributes({ numberofposts: value });
            },
            min: 1,
            max: 10,
          }),
        ),
      ),
      el(ServerSideRender, {
        key: "server-side-render",
        block: "bluesky-social/posts",
        attributes: attributes,
      }),
    );
  };

  blocks.registerBlockType("bluesky-social/posts", {
    title: __("BlueSky Posts Feed", "social-integration-for-bluesky"),
    icon: "rss",
    category: "widgets",
    keywords: [
      __("social", "social-integration-for-bluesky"),
      __("feed", "social-integration-for-bluesky"),
      __("posts", "social-integration-for-bluesky"),
    ],
    styles: [
      {
        name: "default",
        label: __("Default"),
        isDefault: true,
      },
      {
        name: "compact",
        label: __("Compact"),
      },
      {
        name: "expanded",
        label: __("Expanded"),
      },
    ],
    attributes: {
      displayembeds: {
        type: "boolean",
        default: true,
      },
      noreplies: {
        type: "boolean",
        default: true,
      },
      noreposts: {
        type: "boolean",
        default: true,
      },
      nocounters: {
        type: "boolean",
        default: false,
      },
      theme: {
        type: "string",
        default: "system",
      },
      numberofposts: {
        type: "integer",
        default: 5,
      },
      accountId: {
        type: "string",
        default: "",
      },
    },
    supports: {
      anchor: true,
      align: true,
      ariaLabel: true,
      customClassName: true,
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
