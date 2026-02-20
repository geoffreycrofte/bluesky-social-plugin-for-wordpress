(function() {
    'use strict';

    /**
     * Adjust RGB color brightness by a percentage
     * @param {number} r - Red value (0-255)
     * @param {number} g - Green value (0-255)
     * @param {number} b - Blue value (0-255)
     * @param {number} percent - Percentage to brighten (positive) or darken (negative)
     * @returns {Array} - Adjusted [r, g, b] values
     */
    function adjustBrightness(r, g, b, percent) {
        var amount = Math.round(2.55 * percent);
        return [
            Math.min(255, Math.max(0, r + amount)),
            Math.min(255, Math.max(0, g + amount)),
            Math.min(255, Math.max(0, b + amount))
        ];
    }

    /**
     * Generate deterministic RGB colors from a string (avatar URL)
     * Used as fallback when CORS blocks Color Thief extraction
     * @param {string} str - String to hash (avatar URL)
     * @returns {Array} - [r, g, b] values
     */
    function hashStringToRGB(str) {
        var hash = 0;
        for (var i = 0; i < str.length; i++) {
            hash = str.charCodeAt(i) + ((hash << 5) - hash);
            hash = hash & hash; // Convert to 32-bit integer
        }

        // Generate colors with reasonable saturation and brightness
        var r = (hash & 0xFF0000) >> 16;
        var g = (hash & 0x00FF00) >> 8;
        var b = hash & 0x0000FF;

        // Ensure colors aren't too dark (minimum brightness)
        r = Math.max(60, r);
        g = Math.max(60, g);
        b = Math.max(60, b);

        return [r, g, b];
    }

    /**
     * Apply gradient to banner element
     * @param {HTMLElement} element - Banner element with data-avatar-url
     */
    function applyGradientFallback(element) {
        var avatarUrl = element.getAttribute('data-avatar-url');
        if (!avatarUrl) {
            element.classList.remove('bluesky-banner-gradient-pending');
            return;
        }

        // Check if ColorThief is available
        if (typeof ColorThief === 'undefined') {
            console.warn('ColorThief library not loaded, using hash-based gradient fallback');
            applyHashGradient(element, avatarUrl);
            return;
        }

        // Create offscreen image for color extraction
        var img = new Image();
        img.crossOrigin = 'Anonymous'; // Required for Color Thief canvas access

        img.onload = function() {
            try {
                var colorThief = new ColorThief();
                var dominantColor = colorThief.getColor(img);

                if (dominantColor && dominantColor.length === 3) {
                    var r = dominantColor[0];
                    var g = dominantColor[1];
                    var b = dominantColor[2];

                    // Create gradient with brightened second color
                    var brightened = adjustBrightness(r, g, b, 20);
                    var gradient = 'linear-gradient(135deg, rgb(' + r + ',' + g + ',' + b + '), rgb(' + brightened[0] + ',' + brightened[1] + ',' + brightened[2] + '))';

                    // Apply gradient to the appropriate element
                    var targetElement = element;
                    if (element.classList.contains('bluesky-profile-banner-full')) {
                        // For full variant, apply to header section
                        var header = element.querySelector('.bluesky-profile-banner-header');
                        if (header) {
                            targetElement = header;
                        }
                    }

                    targetElement.style.setProperty('--bluesky-banner-gradient', gradient);
                    element.classList.remove('bluesky-banner-gradient-pending');
                } else {
                    // Color extraction failed, use hash fallback
                    applyHashGradient(element, avatarUrl);
                }
            } catch (error) {
                console.warn('Color Thief extraction failed, using hash-based gradient:', error);
                applyHashGradient(element, avatarUrl);
            }
        };

        img.onerror = function() {
            // CORS error or network failure, use hash-based gradient
            console.warn('Image load failed (likely CORS), using hash-based gradient');
            applyHashGradient(element, avatarUrl);
        };

        img.src = avatarUrl;
    }

    /**
     * Apply hash-based gradient when Color Thief unavailable or fails
     * @param {HTMLElement} element - Banner element
     * @param {string} avatarUrl - Avatar URL to hash
     */
    function applyHashGradient(element, avatarUrl) {
        var color1 = hashStringToRGB(avatarUrl);
        var color2 = hashStringToRGB(avatarUrl + '_alt'); // Different hash for second color

        var gradient = 'linear-gradient(135deg, rgb(' + color1[0] + ',' + color1[1] + ',' + color1[2] + '), rgb(' + color2[0] + ',' + color2[1] + ',' + color2[2] + '))';

        // Apply gradient to the appropriate element
        var targetElement = element;
        if (element.classList.contains('bluesky-profile-banner-full')) {
            var header = element.querySelector('.bluesky-profile-banner-header');
            if (header) {
                targetElement = header;
            }
        }

        targetElement.style.setProperty('--bluesky-banner-gradient', gradient);
        element.classList.remove('bluesky-banner-gradient-pending');
    }

    /**
     * Initialize gradient fallback on DOMContentLoaded
     */
    function init() {
        var pendingElements = document.querySelectorAll('.bluesky-banner-gradient-pending');

        if (pendingElements.length === 0) {
            return;
        }

        // Process each banner element
        for (var i = 0; i < pendingElements.length; i++) {
            applyGradientFallback(pendingElements[i]);
        }
    }

    // Run on DOMContentLoaded
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        // DOM already loaded
        init();
    }
})();
