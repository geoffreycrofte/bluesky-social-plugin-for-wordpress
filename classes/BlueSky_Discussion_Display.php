<?php
// Prevent direct access to the plugin
if (!defined("ABSPATH")) {
    exit();
}

/**
 * BlueSky_Discussion_Display has been decomposed into:
 * - BlueSky_Discussion_Metabox (admin metabox + admin AJAX)
 * - BlueSky_Discussion_Renderer (pure HTML rendering)
 * - BlueSky_Discussion_Frontend (frontend injection + scripts)
 *
 * This file is kept for reference. It can be deleted.
 * Refactored in Phase 2, Plan 3 (02-03).
 */
