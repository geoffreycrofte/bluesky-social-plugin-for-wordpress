(function ($) {
  if (document.querySelector(".bluesky-social-integration-admin")) {
    /**
     * Navigation menu
     */
    const navItems = document.querySelectorAll("#bluesky-main-nav-tabs a");
    const tabContents = document.querySelectorAll(
      ".bluesky-social-integration-admin-content",
    );
    const hideTabs = function () {
      tabContents.forEach((tab) => {
        tab.setAttribute("hidden", "true");
        tab.setAttribute("aria-hidden", "true");
      });
      navItems.forEach((item) => {
        item.classList.remove("active");
        item.setAttribute("aria-current", "false");
      });
    };

    const showCurrent = function (currentNavItem) {
      currentNavItem.classList.add("active");
      currentNavItem.setAttribute("aria-current", "true");

      let target = document.querySelector(
        `#${currentNavItem.getAttribute("aria-controls")}`,
      );
      target.removeAttribute("hidden");
      target.setAttribute("aria-hidden", "false");

      localStorage.setItem(
        "bluesky-social-integration-admin-tab",
        currentNavItem.getAttribute("aria-controls"),
      );
    };

    navItems.forEach((item) => {
      item.addEventListener("click", function (e) {
        e.preventDefault();
        hideTabs();
        showCurrent(this);
      });
    });

    if (localStorage.getItem("bluesky-social-integration-admin-tab")) {
      document
        .querySelector(
          '[aria-controls="' +
            localStorage.getItem("bluesky-social-integration-admin-tab") +
            '"]',
        )
        .click();
    } else {
      navItems[0].click();
    }

    /**
     * Customisation Editor
     */
    const units = document.querySelectorAll(".bluesky-custom-unit");
    const styles = document.querySelector(".bluesky-custom-styles-output");

    units.forEach((unit) => {
      unit.addEventListener("change", (e) => {
        let styleID = "bluesky" + e.target.dataset.var;
        let type = e.target.name.split("][")[1]; // e.g. "posts", "profile"

        if (!document.getElementById(styleID)) {
          let style = document.createElement("style");
          style.id = styleID;
          styles.prepend(style);
        }

        switch (type) {
          case "profile":
            document.getElementById(styleID).innerHTML =
              ".bluesky-social-integration-profile-card{" +
              e.target.dataset.var +
              ": " +
              e.target.value +
              "px}";
            break;

          case "posts":
            document.getElementById(styleID).innerHTML =
              ".bluesky-social-integration-last-post{" +
              e.target.dataset.var +
              ": " +
              e.target.value +
              "px}";
            break;

          default:
            break;
        }

        if (e.target.value < 10 || typeof "e.target.value" === "null") {
          document.getElementById(styleID).remove();
        }
      });
    });

    /**
     * Debug bar menu
     */
    const debugbtn = document.querySelector(".bluesky-open-button");
    const sidebar = document.querySelector(".bluesky-debug-sidebar");
    const sidebarContent = sidebar.querySelector(
      ".bluesky-debug-sidebar-content",
    );
    const closeclass = "is-collapsed";
    const closeSidebar = () => {
      sidebar.classList.add(closeclass);
      debugbtn.setAttribute("aria-expanded", "false");
      sidebarContent.setAttribute("aria-hidden", "true");
    };
    const openSidebar = () => {
      sidebar.classList.remove(closeclass);
      debugbtn.setAttribute("aria-expanded", "true");
      sidebarContent.setAttribute("aria-hidden", "false");
    };

    if (debugbtn) {
      debugbtn.addEventListener("click", (e) => {
        if (sidebar.classList.contains(closeclass)) {
          openSidebar();
        } else {
          closeSidebar();
        }
      });

      window.addEventListener("keydown", (e) => {
        if (!sidebar.classList.contains(closeclass) && e.key === "Escape") {
          closeSidebar();
        }
      });
    }

    /**
     * Donate sidebar
     */
    const donatebtn = document.querySelector(".bluesky-donate-button");
    const donateSidebar = document.querySelector(".bluesky-donate-sidebar");

    if (donatebtn && donateSidebar) {
      const donateContent = donateSidebar.querySelector(
        ".bluesky-donate-sidebar-content",
      );
      const closeDonate = () => {
        donateSidebar.classList.add("is-collapsed");
        donatebtn.setAttribute("aria-expanded", "false");
        donateContent.setAttribute("aria-hidden", "true");
      };
      const openDonate = () => {
        donateSidebar.classList.remove("is-collapsed");
        donatebtn.setAttribute("aria-expanded", "true");
        donateContent.setAttribute("aria-hidden", "false");
      };

      donatebtn.addEventListener("click", () => {
        if (donateSidebar.classList.contains("is-collapsed")) {
          openDonate();
        } else {
          closeDonate();
        }
      });

      window.addEventListener("keydown", (e) => {
        if (
          !donateSidebar.classList.contains("is-collapsed") &&
          e.key === "Escape"
        ) {
          closeDonate();
        }
      });
    }

    /**
     * Multi-account progressive disclosure
     */
    const multiAccountToggle = document.getElementById("bluesky-enable-multi-account");
    const multiAccountSection = document.getElementById("bluesky-multi-account-section");

    if (multiAccountToggle && multiAccountSection) {
      // Legacy login fields (handle + password rows)
      var handleField = document.getElementById("bluesky_settings_handle");
      var passwordField = document.getElementById("bluesky_settings_app_password");
      var legacyRows = [];
      if (handleField) {
        var row = handleField.closest("tr");
        if (row) legacyRows.push(row);
      }
      if (passwordField) {
        var row = passwordField.closest("tr");
        if (row) legacyRows.push(row);
      }
      // Also include the connection check div
      var connectionCheck = document.getElementById("bluesky-connection-test");
      if (connectionCheck) {
        var row = connectionCheck.closest("tr");
        if (row && legacyRows.indexOf(row) === -1) legacyRows.push(row);
      }

      function toggleLegacyFields(enabled) {
        legacyRows.forEach(function (row) {
          row.style.opacity = enabled ? "0.4" : "1";
          row.style.pointerEvents = enabled ? "none" : "";
        });
        if (handleField) handleField.readOnly = enabled;
        if (passwordField) passwordField.readOnly = enabled;
      }

      // Set initial state
      if (multiAccountToggle.checked) {
        multiAccountSection.style.display = "block";
        toggleLegacyFields(true);
      } else {
        multiAccountSection.style.display = "none";
        toggleLegacyFields(false);
      }

      // Toggle on change
      multiAccountToggle.addEventListener("change", function () {
        if (this.checked) {
          $(multiAccountSection).slideDown(200);
          toggleLegacyFields(true);
        } else {
          $(multiAccountSection).slideUp(200);
          toggleLegacyFields(false);
        }
      });
    }

    /**
     * Account action form submission helper.
     * Since account action controls live inside the main Settings API form,
     * they cannot use nested <form> elements (HTML spec forbids it).
     * Instead, we collect inputs from the parent .bluesky-account-action div
     * and submit them via a dynamically created form outside the main form.
     */
    function submitAccountAction(container, extraFields) {
      var form = document.createElement("form");
      form.method = "post";
      form.style.display = "none";

      // Copy all hidden inputs from the container
      container
        .querySelectorAll('input[type="hidden"]')
        .forEach(function (input) {
          form.appendChild(input.cloneNode(true));
        });

      // Copy text/password inputs (for add account form)
      container
        .querySelectorAll('input[type="text"], input[type="password"]')
        .forEach(function (input) {
          form.appendChild(input.cloneNode(true));
        });

      // Copy checked checkboxes (for auto-syndicate toggle)
      container
        .querySelectorAll('input[type="checkbox"]:checked')
        .forEach(function (input) {
          var hidden = document.createElement("input");
          hidden.type = "hidden";
          hidden.name = input.name;
          hidden.value = input.value;
          form.appendChild(hidden);
        });

      // Copy select values
      container.querySelectorAll("select").forEach(function (select) {
        var hidden = document.createElement("input");
        hidden.type = "hidden";
        hidden.name = select.name;
        hidden.value = select.value;
        form.appendChild(hidden);
      });

      // Add extra fields (e.g., button name)
      if (extraFields) {
        Object.keys(extraFields).forEach(function (key) {
          var hidden = document.createElement("input");
          hidden.type = "hidden";
          hidden.name = key;
          hidden.value = extraFields[key];
          form.appendChild(hidden);
        });
      }

      document.body.appendChild(form);
      form.submit();
    }

    /**
     * Handle account action button clicks (Remove, Make Active)
     */
    document.querySelectorAll(".bluesky-action-btn").forEach(function (button) {
      button.addEventListener("click", function (e) {
        var container = this.closest(".bluesky-account-action");
        if (!container) return;

        // Remove account confirmation
        if (this.name === "bluesky_remove_account") {
          var confirmed = confirm(
            "Remove this account? Discussion threads for posts syndicated with this account will no longer load.",
          );
          if (!confirmed) return;
        }

        var extra = {};
        if (this.name) {
          extra[this.name] = this.value || "1";
        }

        submitAccountAction(container, extra);
      });
    });

    /**
     * Add Account repeater — adds input fields to the form (saved with Save Changes)
     */
    const addAccountBtn = document.getElementById("bluesky-add-account-btn");
    const addAccountHint = document.getElementById("bluesky-add-account-hint");
    const newAccountsList = document.getElementById("bluesky-new-accounts-list");
    const newAccountTemplate = document.getElementById(
      "bluesky-new-account-template",
    );
    let newAccountIndex = 0;

    if (addAccountBtn && newAccountTemplate && newAccountsList) {
      addAccountBtn.addEventListener("click", function () {
        let templateContent = newAccountTemplate.content.cloneNode(true);
        let row = templateContent.querySelector(".bluesky-new-account-row");

        // Replace __INDEX__ in all input name attributes using safe DOM manipulation
        row.querySelectorAll("input[name]").forEach(function (input) {
          input.name = input.name.replace("__INDEX__", newAccountIndex);
          input.id = input.id.replace("__INDEX__", newAccountIndex);
        });
        row.querySelectorAll("label[for]").forEach(function (label) {
          console.log(label);
          label.setAttribute('for', label.getAttribute('for').replace("__INDEX__", newAccountIndex) );
        });
        newAccountIndex++;

        newAccountsList.appendChild(row);

        // Show hint
        if (addAccountHint) {
          addAccountHint.style.display = "block";
        }

        // Bind cancel button on the newly added row
        let lastRow = newAccountsList.querySelector(
          ".bluesky-new-account-row:last-child",
        );
        let cancelBtn = lastRow
          ? lastRow.querySelector(".bluesky-remove-new-account")
          : null;
        if (cancelBtn) {
          cancelBtn.addEventListener("click", function () {
            this.closest(".bluesky-new-account-row").remove();
            // Hide hint if no more new accounts
            if (!newAccountsList.querySelector(".bluesky-new-account-row")) {
              if (addAccountHint) addAccountHint.style.display = "none";
            }
          });
        }

        // Focus the first field of the newly added row.
        lastRow.querySelectorAll('input')[0].focus();
      });
    }

    /**
     * Handle auto-syndicate checkbox changes
     */
    document
      .querySelectorAll(".bluesky-auto-syndicate-toggle")
      .forEach(function (checkbox) {
        checkbox.addEventListener("change", function () {
          let container = this.closest(".bluesky-account-action");
          if (!container) return;

          // For unchecked state, we need to ensure auto_syndicate is not sent
          // The hidden field bluesky_toggle_auto_syndicate triggers the handler
          submitAccountAction(container, {});
        });
      });

    /**
     * Handle discussion account dropdown changes
     * Saves via AJAX without page reload
     */
    let discussionSelect = document.querySelector(
      ".bluesky-discussion-account-select",
    );
    if (discussionSelect) {
      discussionSelect.addEventListener("change", function () {
        let container = this.closest(".bluesky-account-action");
        if (!container) return;
        let nonceField = container.querySelector(
          '[name="_bluesky_discussion_nonce"]',
        );
        if (!nonceField) return;

        let formData = new FormData();
        formData.append("action", "bluesky_set_discussion_account");
        formData.append("bluesky_discussion_account", this.value);
        formData.append("_bluesky_discussion_nonce", nonceField.value);

        this.disabled = true;
        fetch(ajaxurl, { method: "POST", body: formData })
          .then(function (response) {
            return response.json();
          })
          .then(function (data) {
            discussionSelect.disabled = false;
            if (data.success) {
              let notice = document.createElement("div");
              notice.className = "notice notice-success is-dismissible";
              let p = document.createElement("p");
              p.textContent = data.data || "Discussion account updated.";
              notice.appendChild(p);
              let wrap = document.querySelector(".wrap");
              if (wrap) wrap.insertBefore(notice, wrap.children[1]);
            }
          })
          .catch(function () {
            discussionSelect.disabled = false;
          });
      });
    }

    /**
     * Discussion settings enable/disable toggle
     */
    const enableDiscussionsCheckbox = document.getElementById(
      "bluesky_settings_enable_discussions",
    );

    if (enableDiscussionsCheckbox) {
      const discussionFields = [
        "bluesky_settings_discussions_show_nested",
        "bluesky_settings_discussions_nested_collapsed",
        "bluesky_settings_discussions_show_stats",
        "bluesky_settings_discussions_show_reply_link",
        "bluesky_settings_discussions_show_media",
      ];

      const toggleDiscussionFields = function () {
        const isEnabled = enableDiscussionsCheckbox.checked;
        const showNestedCheckbox = document.getElementById(
          "bluesky_settings_discussions_show_nested",
        );

        discussionFields.forEach((fieldId) => {
          const field = document.getElementById(fieldId);
          if (field) {
            field.disabled = !isEnabled;

            // Add visual feedback
            const label = field.closest("tr");
            if (label) {
              if (isEnabled) {
                label.style.opacity = "1";
              } else {
                label.style.opacity = "0.5";
              }
            }
          }
        });

        // Handle nested collapsed dependency on show nested
        if (showNestedCheckbox && isEnabled) {
          const nestedCollapsedCheckbox = document.getElementById(
            "bluesky_settings_discussions_nested_collapsed",
          );
          if (nestedCollapsedCheckbox) {
            nestedCollapsedCheckbox.disabled = !showNestedCheckbox.checked;
            const label = nestedCollapsedCheckbox.closest("tr");
            if (label) {
              label.style.opacity = showNestedCheckbox.checked ? "1" : "0.5";
            }
          }
        }
      };

      const toggleNestedCollapsed = function () {
        const showNestedCheckbox = document.getElementById(
          "bluesky_settings_discussions_show_nested",
        );
        const nestedCollapsedCheckbox = document.getElementById(
          "bluesky_settings_discussions_nested_collapsed",
        );

        if (
          showNestedCheckbox &&
          nestedCollapsedCheckbox &&
          enableDiscussionsCheckbox.checked
        ) {
          nestedCollapsedCheckbox.disabled = !showNestedCheckbox.checked;
          const label = nestedCollapsedCheckbox.closest("tr");
          if (label) {
            label.style.opacity = showNestedCheckbox.checked ? "1" : "0.5";
          }
        }
      };

      // Initialize state on page load
      toggleDiscussionFields();

      // Add event listeners
      enableDiscussionsCheckbox.addEventListener(
        "change",
        toggleDiscussionFields,
      );

      const showNestedCheckbox = document.getElementById(
        "bluesky_settings_discussions_show_nested",
      );
      if (showNestedCheckbox) {
        showNestedCheckbox.addEventListener("change", toggleNestedCollapsed);
      }
    }
  }
})(jQuery);
