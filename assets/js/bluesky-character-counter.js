/**
 * Bluesky Character Counter Utility
 * Provides accurate grapheme cluster counting for Bluesky's 300 character limit
 */

(function () {
    'use strict';

    /**
     * Count grapheme clusters in text
     * Uses Intl.Segmenter API when available, falls back to length
     *
     * @param {string} text - Text to count
     * @returns {number} Number of grapheme clusters
     */
    function countGraphemes(text) {
        // Handle empty/null/undefined
        if (!text || text === '') {
            return 0;
        }

        // Use Intl.Segmenter if available (modern browsers)
        if (typeof Intl !== 'undefined' && Intl.Segmenter) {
            const segmenter = new Intl.Segmenter(undefined, { granularity: 'grapheme' });
            const segments = segmenter.segment(text);
            return Array.from(segments).length;
        }

        // Fallback to simple length for older browsers
        return text.length;
    }

    /**
     * Get character count status with metadata
     *
     * @param {string} text - Text to analyze
     * @param {number} maxLength - Maximum allowed length (default: 300)
     * @returns {Object} Status object with count, max, remaining, isOverLimit, percentage
     */
    function getCountStatus(text, maxLength) {
        maxLength = maxLength || 300;
        const count = countGraphemes(text);
        const remaining = maxLength - count;
        const isOverLimit = count > maxLength;
        const percentage = maxLength > 0 ? Math.round((count / maxLength) * 100) : 0;

        return {
            count: count,
            max: maxLength,
            remaining: remaining,
            isOverLimit: isOverLimit,
            percentage: percentage
        };
    }

    // Expose globally
    window.BlueSkyCharacterCounter = {
        countGraphemes: countGraphemes,
        getCountStatus: getCountStatus
    };

})();
