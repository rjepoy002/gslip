<?php
/*
 * e-GSlip Dark Mode Preload
 * Applies the saved Dark Mode preference before the page is rendered.
 * Visual-only feature. Does not modify application/business logic.
 */
?>

<script>
    if (localStorage.getItem("egslip-dark-mode") === "true") {
        document.documentElement.classList.add("dark-mode-preload");
    }
</script>
