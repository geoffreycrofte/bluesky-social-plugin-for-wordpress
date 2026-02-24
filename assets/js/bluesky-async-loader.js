/**
 * Bluesky Async Loader
 * Finds skeleton placeholders and replaces them with real content via AJAX.
 */
(function () {
    "use strict";

    if (typeof blueskyAsync === "undefined") {
        return;
    }

    var ajaxUrl = blueskyAsync.ajaxUrl;
    var nonce = blueskyAsync.nonce;

    function loadElement(el) {
        var type = el.getAttribute("data-bluesky-async");
        var paramsRaw = el.getAttribute("data-bluesky-params") || "{}";
        var action;
        var body;

        if (type === "posts") {
            action = "bluesky_async_posts";
        } else if (type === "profile") {
            action = "bluesky_async_profile";
        } else if (type === "auth") {
            action = "bluesky_async_auth";
        } else {
            return;
        }

        body = new FormData();
        body.append("action", action);
        body.append("nonce", nonce);

        if (type !== "auth") {
            body.append("params", paramsRaw);
        } else {
            // For auth type, extract account_id from params if present
            var params = JSON.parse(paramsRaw);
            if (params.account_id) {
                body.append("account_id", params.account_id);
            }
        }

        fetch(ajaxUrl, {
            method: "POST",
            credentials: "same-origin",
            body: body,
        })
            .then(function (response) {
                return response.json();
            })
            .then(function (data) {
                if (!data.success) {
                    showError(el, type);
                    return;
                }

                if (type === "auth") {
                    handleAuth(el, data.data);
                } else {
                    // Replace skeleton with server-rendered HTML.
                    // This HTML comes from our own PHP render methods
                    // (same trusted source as initial page render).
                    replaceWithServerHtml(el, data.data.html);
                }
            })
            .catch(function () {
                showError(el, type);
            });
    }

    /**
     * Replace a skeleton element with server-rendered HTML.
     * The HTML originates from our own PHP rendering pipeline
     * (BlueSky_Render_Front methods), the same code that produces
     * the initial page markup — it is a trusted source.
     */
    function replaceWithServerHtml(el, html) {
        if (!html) return;
        var range = document.createRange();
        range.selectNode(el);
        var fragment = range.createContextualFragment(html);
        el.parentNode.replaceChild(fragment, el);
    }

    function getAuthErrorMessage(error) {
        if (!error || !error.code) {
            return blueskyAsync.i18n.connectionFailed;
        }

        switch (error.code) {
            case "MissingCredentials":
                return blueskyAsync.i18n.missingCredentials;
            case "NetworkError":
                return (
                    blueskyAsync.i18n.networkError + " " +
                    (error.message || "network error") +
                    "."
                );
            case "RateLimitExceeded":
                var msg = blueskyAsync.i18n.rateLimitExceeded;
                if (error.ratelimit_reset) {
                    var resetDate = new Date(
                        parseInt(error.ratelimit_reset, 10) * 1000
                    );
                    msg +=
                        " " + blueskyAsync.i18n.rateLimitResetsAt + " " +
                        resetDate.toLocaleTimeString() +
                        ".";
                }
                return msg;
            case "AuthFactorTokenRequired":
                return blueskyAsync.i18n.authFactorRequired;
            case "AccountTakedown":
                return blueskyAsync.i18n.accountTakedown;
            case "AuthenticationRequired":
                return blueskyAsync.i18n.invalidCredentials;
            default:
                // Show the API error code and message for any other error
                var detail = error.code;
                if (error.message) {
                    detail += " \u2014 " + error.message;
                }
                if (error.status) {
                    detail += " (HTTP " + error.status + ")";
                }
                return blueskyAsync.i18n.connectionFallback + " " + detail;
        }
    }

    function handleAuth(el, data) {
        el.classList.remove("bluesky-async-placeholder");

        // Clear existing content
        while (el.firstChild) {
            el.removeChild(el.firstChild);
        }

        var p = document.createElement("p");

        if (data.authenticated) {
            el.className =
                "description bluesky-connection-check notice-success";

            p.textContent = blueskyAsync.i18n.connectionSuccess;
            p.appendChild(document.createElement("br"));

            var logoutUrl =
                el.getAttribute("data-bluesky-logout-url") || "#";
            var a = document.createElement("a");
            a.className = "bluesky-logout-link";
            a.href = logoutUrl;
            a.textContent = blueskyAsync.i18n.logoutLink;
            p.appendChild(a);
        } else {
            el.className = "description bluesky-connection-check notice-error";
            p.textContent = getAuthErrorMessage(data.error);
        }

        el.appendChild(p);
    }

    function showError(el, type) {
        el.classList.remove("bluesky-async-placeholder");
        if (type === "auth") {
            el.className = "description bluesky-connection-check notice-error";
            while (el.firstChild) {
                el.removeChild(el.firstChild);
            }
            var p = document.createElement("p");
            p.textContent = blueskyAsync.i18n.connectionCheckFailed;
            el.appendChild(p);
        } else {
            el.textContent = blueskyAsync.i18n.contentLoadFailed;
        }
    }

    document.addEventListener("DOMContentLoaded", function () {
        var elements = document.querySelectorAll("[data-bluesky-async]");
        for (var i = 0; i < elements.length; i++) {
            loadElement(elements[i]);
        }
    });
})();
