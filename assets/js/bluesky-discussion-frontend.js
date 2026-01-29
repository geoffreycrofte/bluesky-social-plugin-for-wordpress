/**
 * Bluesky Discussion Frontend JavaScript
 * Handles tabs and collapsible nested replies
 */

(function() {
    'use strict';

    /**
     * Initialize tabs functionality
     */
    function initTabs() {
        const tabButtons = document.querySelectorAll('.bluesky-tab-button');

        if (!tabButtons.length) {
            return;
        }

        tabButtons.forEach(button => {
            button.addEventListener('click', function() {
                const tabName = this.getAttribute('data-tab');

                // Remove active class from all buttons and contents
                tabButtons.forEach(btn => btn.classList.remove('active'));
                document.querySelectorAll('.bluesky-tab-content').forEach(content => {
                    content.classList.remove('active');
                });

                // Add active class to clicked button and corresponding content
                this.classList.add('active');
                const activeContent = document.querySelector(`.bluesky-tab-content[data-content="${tabName}"]`);
                if (activeContent) {
                    activeContent.classList.add('active');
                }
            });
        });
    }

    /**
     * Initialize collapsible replies functionality
     */
    function initCollapsibleReplies() {
        const toggleButtons = document.querySelectorAll('.bluesky-toggle-replies');

        if (!toggleButtons.length) {
            return;
        }

        toggleButtons.forEach(button => {
            button.addEventListener('click', function() {
                const parent = this.closest('.bluesky-reply-children');

                if (!parent) {
                    return;
                }

                const isCollapsed = parent.classList.contains('collapsed');
                const collapsedText = this.getAttribute('data-collapsed-text');
                const expandedText = this.getAttribute('data-expanded-text');

                if (isCollapsed) {
                    // Expand
                    parent.classList.remove('collapsed');
                    this.textContent = expandedText;
                    this.setAttribute('aria-expanded', 'true');
                } else {
                    // Collapse
                    parent.classList.add('collapsed');
                    this.textContent = collapsedText;
                    this.setAttribute('aria-expanded', 'false');
                }
            });

            // Set initial aria-expanded state
            const parent = button.closest('.bluesky-reply-children');
            if (parent) {
                const isCollapsed = parent.classList.contains('collapsed');
                button.setAttribute('aria-expanded', !isCollapsed);
            }
        });
    }

    /**
     * Smooth scroll to discussion section if hash is present
     */
    function handleHashScroll() {
        if (window.location.hash === '#bluesky-discussion') {
            const discussionSection = document.querySelector('.bluesky-discussion-section');
            if (discussionSection) {
                setTimeout(() => {
                    discussionSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }, 100);
            }
        }
    }

    /**
     * Add keyboard navigation for tabs
     */
    function initKeyboardNavigation() {
        const tabButtons = document.querySelectorAll('.bluesky-tab-button');

        if (!tabButtons.length) {
            return;
        }

        tabButtons.forEach((button, index) => {
            button.addEventListener('keydown', function(e) {
                let targetIndex;

                if (e.key === 'ArrowRight') {
                    e.preventDefault();
                    targetIndex = index + 1;
                    if (targetIndex >= tabButtons.length) {
                        targetIndex = 0;
                    }
                    tabButtons[targetIndex].focus();
                    tabButtons[targetIndex].click();
                } else if (e.key === 'ArrowLeft') {
                    e.preventDefault();
                    targetIndex = index - 1;
                    if (targetIndex < 0) {
                        targetIndex = tabButtons.length - 1;
                    }
                    tabButtons[targetIndex].focus();
                    tabButtons[targetIndex].click();
                } else if (e.key === 'Home') {
                    e.preventDefault();
                    tabButtons[0].focus();
                    tabButtons[0].click();
                } else if (e.key === 'End') {
                    e.preventDefault();
                    tabButtons[tabButtons.length - 1].focus();
                    tabButtons[tabButtons.length - 1].click();
                }
            });
        });
    }

    /**
     * Initialize lazy loading for avatars
     */
    function initLazyLoadAvatars() {
        if ('IntersectionObserver' in window) {
            const avatarObserver = new IntersectionObserver((entries, observer) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const img = entry.target;
                        if (img.dataset.src) {
                            img.src = img.dataset.src;
                            img.removeAttribute('data-src');
                        }
                        observer.unobserve(img);
                    }
                });
            }, {
                rootMargin: '50px'
            });

            document.querySelectorAll('.bluesky-reply-avatar[data-src]').forEach(img => {
                avatarObserver.observe(img);
            });
        }
    }

    /**
     * Add expand/collapse all functionality
     */
    function addExpandCollapseAll() {
        const discussionSection = document.querySelector('.bluesky-discussion-section');

        if (!discussionSection) {
            return;
        }

        const toggleButtons = discussionSection.querySelectorAll('.bluesky-toggle-replies');

        if (toggleButtons.length === 0) {
            return;
        }

        // Create control buttons
        const controlsDiv = document.createElement('div');
        controlsDiv.className = 'bluesky-discussion-controls';
        controlsDiv.style.cssText = 'margin-bottom: 15px; display: flex; gap: 10px;';

        const expandAllBtn = document.createElement('button');
        expandAllBtn.textContent = 'Expand All Replies';
        expandAllBtn.className = 'bluesky-control-button';
        expandAllBtn.style.cssText = 'padding: 6px 12px; background: #f0f8ff; border: 1px solid #1185FE; color: #1185FE; border-radius: 4px; cursor: pointer; font-size: 13px; font-weight: 600;';

        const collapseAllBtn = document.createElement('button');
        collapseAllBtn.textContent = 'Collapse All Replies';
        collapseAllBtn.className = 'bluesky-control-button';
        collapseAllBtn.style.cssText = 'padding: 6px 12px; background: #f0f8ff; border: 1px solid #1185FE; color: #1185FE; border-radius: 4px; cursor: pointer; font-size: 13px; font-weight: 600;';

        expandAllBtn.addEventListener('click', function() {
            toggleButtons.forEach(button => {
                const parent = button.closest('.bluesky-reply-children');
                if (parent && parent.classList.contains('collapsed')) {
                    button.click();
                }
            });
        });

        collapseAllBtn.addEventListener('click', function() {
            toggleButtons.forEach(button => {
                const parent = button.closest('.bluesky-reply-children');
                if (parent && !parent.classList.contains('collapsed')) {
                    button.click();
                }
            });
        });

        controlsDiv.appendChild(expandAllBtn);
        controlsDiv.appendChild(collapseAllBtn);

        // Insert before the discussion thread
        const discussionThread = discussionSection.querySelector('.bluesky-discussion-thread');
        if (discussionThread) {
            discussionThread.insertBefore(controlsDiv, discussionThread.firstChild);
        }
    }

    /**
     * Handle external links
     */
    function handleExternalLinks() {
        const discussionSection = document.querySelector('.bluesky-discussion-section');

        if (!discussionSection) {
            return;
        }

        discussionSection.querySelectorAll('a[target="_blank"]').forEach(link => {
            // Add aria-label for screen readers
            if (!link.getAttribute('aria-label')) {
                const text = link.textContent.trim();
                link.setAttribute('aria-label', `${text} (opens in new tab)`);
            }
        });
    }

    /**
     * Initialize all functionality when DOM is ready
     */
    function init() {
        initTabs();
        initCollapsibleReplies();
        initKeyboardNavigation();
        initLazyLoadAvatars();
        addExpandCollapseAll();
        handleExternalLinks();
        handleHashScroll();
    }

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    // Re-initialize on hash change
    window.addEventListener('hashchange', handleHashScroll);

})();
