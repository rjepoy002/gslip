/*
 * e-GSlip Dark Mode
 * Visual-only feature. Does not modify application/business logic.
 */

(function () {
    "use strict";

    const STORAGE_KEY = "egslip-dark-mode";

    function applyDarkMode(enabled) {
        // Apply dark mode to the page
        document.body.classList.toggle("dark-mode", enabled);
        document.documentElement.classList.remove("dark-mode-preload");

        // Get dark mode toggle button
        const toggle = document.getElementById("darkModeToggle");

        if (!toggle) {
            return;
        }

        // Update icon
        const icon = toggle.querySelector("i");

        if (icon) {
            icon.className = enabled
                ? "bi bi-sun-fill"
                : "bi bi-moon-fill";
        }

        // Update text label
        // const label = toggle.querySelector(".dark-mode-label");

        // if (label) {
        //     label.textContent = enabled
        //         ? "Light Mode"
        //         : "Dark Mode";
        // }

        // Update accessibility label
        toggle.setAttribute(
            "aria-label",
            enabled
                ? "Switch to light mode"
                : "Switch to dark mode"
        );

        // Update tooltip
        toggle.setAttribute(
            "title",
            enabled
                ? "Light Mode"
                : "Dark Mode"
        );
    }

    function initDarkMode() {
        // Get saved preference
        const savedMode = localStorage.getItem(STORAGE_KEY);

        const isDarkMode = savedMode === "true";

        // Apply saved preference
        applyDarkMode(isDarkMode);

        // Get toggle button
        const toggle = document.getElementById("darkModeToggle");

        if (!toggle) {
            return;
        }

        // Prevent duplicate event handlers
        if (toggle.dataset.darkModeInitialized === "true") {
            return;
        }

        toggle.dataset.darkModeInitialized = "true";

        // Toggle dark mode
        toggle.addEventListener("click", function () {
            const nextMode =
                !document.body.classList.contains("dark-mode");

            // Save preference
            localStorage.setItem(
                STORAGE_KEY,
                String(nextMode)
            );

            // Apply new mode
            applyDarkMode(nextMode);
        });
    }

    // Initialize after DOM is ready
    if (document.readyState === "loading") {
        document.addEventListener(
            "DOMContentLoaded",
            initDarkMode
        );
    } else {
        initDarkMode();
    }

})();