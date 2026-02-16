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
            return "Connection to BlueSky failed. Please check your credentials.";
        }

        switch (error.code) {
            case "MissingCredentials":
                return "Handle or app password is not configured.";
            case "NetworkError":
                return (
                    "Could not reach BlueSky servers: " +
                    (error.message || "network error") +
                    "."
                );
            case "RateLimitExceeded":
                var msg =
                    "BlueSky rate limit exceeded. Please wait a few minutes before trying again.";
                if (error.ratelimit_reset) {
                    var resetDate = new Date(
                        parseInt(error.ratelimit_reset, 10) * 1000
                    );
                    msg +=
                        " Resets at " +
                        resetDate.toLocaleTimeString() +
                        ".";
                }
                return msg;
            case "AuthFactorTokenRequired":
                return "BlueSky requires email 2FA verification. Use an App Password instead to bypass 2FA.";
            case "AccountTakedown":
                return "This BlueSky account has been taken down.";
            case "AuthenticationRequired":
                return "Invalid handle or password. Please check your credentials.";
            default:
                // Show the API error code and message for any other error
                var detail = error.code;
                if (error.message) {
                    detail += " \u2014 " + error.message;
                }
                if (error.status) {
                    detail += " (HTTP " + error.status + ")";
                }
                return "Connection failed: " + detail;
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

            p.textContent = "Connection to BlueSky successful!";
            p.appendChild(document.createElement("br"));

            var logoutUrl =
                el.getAttribute("data-bluesky-logout-url") || "#";
            var a = document.createElement("a");
            a.className = "bluesky-logout-link";
            a.href = logoutUrl;
            a.textContent = "Log out from this account";
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
            p.textContent = "Could not check connection status.";
            el.appendChild(p);
        } else {
            el.textContent = "Unable to load Bluesky content.";
        }
    }

    document.addEventListener("DOMContentLoaded", function () {
        var elements = document.querySelectorAll("[data-bluesky-async]");
        for (var i = 0; i < elements.length; i++) {
            loadElement(elements[i]);
        }
    });
})();
