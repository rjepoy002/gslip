<?php
// Shared by public authentication forms; does not change PHP session lifetime.
function authCsrf(): string
{
    return $_SESSION['auth_csrf'] ??= bin2hex(random_bytes(32));
}

function authRequireCsrf(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' ||
        !is_string($_POST['csrf'] ?? null) ||
        !hash_equals(authCsrf(), $_POST['csrf'])) {
        http_response_code(403);
        exit('Invalid request. Reload the page and try again.');
    }
}
