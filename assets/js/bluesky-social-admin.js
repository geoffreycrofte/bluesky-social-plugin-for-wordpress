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
     * Multi-account progressive disclosure
     */
    const multiAccountToggle = document.getElementById("bluesky-enable-multi-account");
    const multiAccountSection = document.getElementById("bluesky-multi-account-section");

    if (multiAccountToggle && multiAccountSection) {
      // Set initial state
      if (multiAccountToggle.checked) {
        multiAccountSection.style.display = "block";
      } else {
        multiAccountSection.style.display = "none";
      }

      // Toggle on change
      multiAccountToggle.addEventListener("change", function () {
        if (this.checked) {
          $(multiAccountSection).slideDown(200);
        } else {
          $(multiAccountSection).slideUp(200);
        }
      });
    }

    /**
     * Remove account confirmation
     */
    const removeAccountButtons = document.querySelectorAll(".bluesky-remove-account-btn");

    removeAccountButtons.forEach(function (button) {
      button.addEventListener("click", function (e) {
        const confirmed = confirm(
          "Remove this account? Discussion threads for posts syndicated with this account will no longer load.",
        );
        if (!confirmed) {
          e.preventDefault();
        }
      });
    });

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
