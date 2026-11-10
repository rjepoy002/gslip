<?php
const REMEMBER_COOKIE = 'gslip_remember';
const REMEMBER_SECONDS = 30 * 24 * 60 * 60;

function rememberCookieReceived(): bool
{
    static $received;
    return $received ??= isset($_COOKIE[REMEMBER_COOKIE]);
}

function rememberDiagnostic(string $event, array $details = []): void
{
    // Deliberately exclude URLs, selectors, hashes, session IDs and exception
    // messages: database errors can contain credential material.
    $safe = array_intersect_key($details, array_flip(['user_id','token_id','reason','cookie_path','secure']));
    error_log('GSLIP remember '.json_encode(['event'=>$event,
        'cookie_received'=>rememberCookieReceived(),
        'cookie_present'=>isset($_COOKIE[REMEMBER_COOKIE]),
        'session_identity_present'=>!empty($_SESSION['user_id'])] + $safe));
}

function rememberNavigationRequest(): bool
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET' || isset($_GET['logout'])) return false;
    $page = basename($_SERVER['SCRIPT_FILENAME'] ?? '');
    if (in_array($page, ['logout.php','maintenance.php','reset_password.php','forgotpass_handler.php','signup_handler.php'], true)) return false;
    if (preg_match('~/+(ajax|api)/~i', str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? ''))) return false;
    if (isset($_SERVER['HTTP_SEC_FETCH_MODE'])) return $_SERVER['HTTP_SEC_FETCH_MODE'] === 'navigate';
    return empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
        !str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') &&
        (!isset($_SERVER['HTTP_ACCEPT']) || str_contains($_SERVER['HTTP_ACCEPT'], 'text/html'));
}

function rememberCompleteSession(): bool
{
    return !empty($_SESSION['user_id']) && !empty($_SESSION['session_token']) &&
        isset($_SESSION['role'], $_SESSION['department_id'], $_SESSION['area']);
}

function rememberSessionUser(mysqli $conn): ?array
{
    if (empty($_SESSION['user_id']) || empty($_SESSION['session_token'])) return null;
    $s = $conn->prepare("SELECT * FROM users WHERE id=? AND session_token=? AND status='active'");
    $s->bind_param('is', $_SESSION['user_id'], $_SESSION['session_token']); $s->execute();
    $user = $s->get_result()->fetch_assoc(); $s->close();
    return $user;
}

function rememberClearSessionIdentity(): void
{
    foreach (['user_id','session_token','role','department_id','area','username','fullname',
        'designation','is_recommender','is_approver','is_private_approver'] as $key) unset($_SESSION[$key]);
}

function rememberBootstrap(mysqli $conn): void
{
    static $attempted = false;
    if ($attempted || PHP_SAPI === 'cli' || !rememberNavigationRequest()) return;
    $attempted = true;
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (!isset($_COOKIE[REMEMBER_COOKIE])) return;
    require_once __DIR__.'/login_session.php';
    rememberAutoLogin($conn);
}

function rememberCookieOptions(int $expires): array
{
    // Derive the application path from this file, including subdirectory installs.
    $root = str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT'] ?? '') ?: '');
    $app = str_replace('\\', '/', dirname(__DIR__));
    $path = $root && str_starts_with($app.'/', rtrim($root, '/').'/')
        ? substr($app, strlen(rtrim($root, '/'))).'/' : '/';
    return ['expires'=>$expires, 'path'=>$path, 'secure'=>
        (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['SERVER_PORT'] ?? '') == 443,
        'httponly'=>true, 'samesite'=>'Lax'];
}

function rememberClearCookie(): void
{
    if (!headers_sent()) setcookie(REMEMBER_COOKIE, '', rememberCookieOptions(time()-3600));
    unset($_COOKIE[REMEMBER_COOKIE]);
}

function rememberParse($cookie): ?array
{
    return is_string($cookie) && preg_match('/\A([a-f0-9]{32}):([a-f0-9]{64})\z/D', $cookie, $m)
        ? [$m[1], $m[2]] : null;
}

function rememberAvailable(mysqli $conn): bool
{
    return $conn->query("SHOW TABLES LIKE 'remember_tokens'")->num_rows === 1;
}

function rememberRevokeBrowser(mysqli $conn): void
{
    $parts = rememberParse($_COOKIE[REMEMBER_COOKIE] ?? null);
    if ($parts && rememberAvailable($conn)) {
        // Selector alone is enough to revoke, never to authenticate.
        $s = $conn->prepare('UPDATE remember_tokens SET revoked_at=UTC_TIMESTAMP() WHERE selector=? AND revoked_at IS NULL');
        $s->bind_param('s', $parts[0]); $s->execute(); $s->close();
    }
    rememberClearCookie();
}

function rememberMaintenanceLock(mysqli $conn): void
{
    if ((int)$conn->query("SELECT GET_LOCK('gslip_maintenance_event',10)")->fetch_row()[0] !== 1)
        throw new RuntimeException('Authentication is busy. Please try again.');
}

function rememberUnlock(mysqli $conn): void
{
    $conn->query("SELECT RELEASE_LOCK('gslip_maintenance_event')");
}

// Call inside the locked users transaction after successful password validation.
function rememberIssue(mysqli $conn, int $userId): array
{
    $selector = bin2hex(random_bytes(16));
    $verifier = bin2hex(random_bytes(32));
    $hash = hash('sha256', $verifier);
    $expires = time()+REMEMBER_SECONDS;
    $until = gmdate('Y-m-d H:i:s', $expires);
    $s = $conn->prepare('INSERT INTO remember_tokens(user_id,selector,verifier_hash,expires_at,created_at) VALUES(?,?,?,?,UTC_TIMESTAMP())');
    $s->bind_param('isss', $userId, $selector, $hash, $until); $s->execute(); $s->close();
    rememberDiagnostic('token_prepared', ['user_id'=>$userId, 'token_id'=>(int)$conn->insert_id]);
    // Caller commits before sending this cookie.
    return [$selector.':'.$verifier, $expires];
}

function rememberSendIssuedCookie(?array $issued): void
{
    if ($issued) {
        [$value, $expires] = $issued;
        $options = rememberCookieOptions($expires);
        if (headers_sent() || !setcookie(REMEMBER_COOKIE, $value, $options))
            throw new RuntimeException('Persistent cookie could not be queued.');
        // Prevent another connection in this request from consuming the old value.
        $_COOKIE[REMEMBER_COOKIE] = $value;
        rememberDiagnostic('cookie_queued', ['cookie_path'=>$options['path'], 'secure'=>$options['secure']]);
    }
}

// Shared document-navigation bootstrap; never called by background polls,
// POST, logout, recovery or maintenance pages.
function rememberAutoLogin(mysqli $conn): bool
{
    if (!isset($_COOKIE[REMEMBER_COOKIE])) return false;
    $existing = rememberSessionUser($conn);
    if ($existing && rememberCompleteSession()) return false;
    rememberClearSessionIdentity();
    rememberDiagnostic('restore_attempt');
    $parts = rememberParse($_COOKIE[REMEMBER_COOKIE]);
    if (!$parts || !rememberAvailable($conn)) {
        rememberDiagnostic('restore_rejected', ['reason'=>$parts ? 'table_unavailable' : 'malformed_cookie']);
        rememberClearCookie(); return false;
    }
    $locked = false;
    try {
        rememberMaintenanceLock($conn); $locked = true;
        if (maintenanceState($conn)['phase'] === 'active') {
            // Checking availability is not an explicit logout. Existing event
            // activation/acknowledgement policies remain responsible for revocation.
            rememberDiagnostic('restore_blocked', ['reason'=>'maintenance']); return false;
        }
        if (headers_sent()) throw new RuntimeException('Authentication headers already sent.');
        $s = $conn->prepare('SELECT user_id FROM remember_tokens WHERE selector=?');
        $s->bind_param('s', $parts[0]); $s->execute();
        $id = $s->get_result()->fetch_assoc()['user_id'] ?? null; $s->close();
        if (!$id) {
            rememberDiagnostic('restore_rejected', ['reason'=>'unknown_selector']);
            rememberClearCookie(); return false;
        }
        $conn->begin_transaction();
        // All authentication locks users before tokens, matching the trigger.
        $s = $conn->prepare('SELECT * FROM users WHERE id=? FOR UPDATE');
        $s->bind_param('i', $id); $s->execute(); $user = $s->get_result()->fetch_assoc(); $s->close();
        $s = $conn->prepare('SELECT *, expires_at>UTC_TIMESTAMP() AS valid_time FROM remember_tokens WHERE selector=? FOR UPDATE');
        $s->bind_param('s', $parts[0]); $s->execute(); $token = $s->get_result()->fetch_assoc(); $s->close();
        if (!$user || $user['status'] !== 'active' || !$token || $token['revoked_at'] !== null ||
            !$token['valid_time'] || !hash_equals($token['verifier_hash'], hash('sha256', $parts[1]))) {
            $reason = !$user || $user['status'] !== 'active' ? 'account_ineligible' :
                (!$token ? 'unknown_selector' : ($token['revoked_at'] !== null ? 'revoked' :
                (!$token['valid_time'] ? 'expired' : 'verifier_mismatch')));
            rememberDiagnostic('restore_rejected', ['reason'=>$reason, 'user_id'=>(int)$id]);
            rememberRevokeBrowser($conn);
            // Replay/tampering invalidates the session established with this device.
            if ($user && $token && $token['revoked_at'] === null) {
                $s = $conn->prepare('UPDATE users SET session_token=NULL WHERE id=?');
                $s->bind_param('i', $id); $s->execute(); $s->close();
            }
            $conn->commit(); return false;
        }
        $selector = bin2hex(random_bytes(16));
        $verifier = bin2hex(random_bytes(32)); $hash = hash('sha256', $verifier);
        // Save a separate replacement before retiring the presented credential.
        // Retain the initial deadline rather than granting another 30 days.
        $s = $conn->prepare('INSERT INTO remember_tokens(user_id,selector,verifier_hash,expires_at,created_at,last_used_at) VALUES(?,?,?,?,UTC_TIMESTAMP(),UTC_TIMESTAMP())');
        $s->bind_param('isss', $id, $selector, $hash, $token['expires_at']); $s->execute();
        $replacementId = $conn->insert_id; $s->close();
        $sessionToken = bin2hex(random_bytes(32));
        $s = $conn->prepare('UPDATE users SET session_token=?,last_login=NOW() WHERE id=?');
        $s->bind_param('si', $sessionToken, $id); $s->execute(); $s->close();
        establishLoginSession($conn, $user, $sessionToken);
        $expires = (new DateTimeImmutable($token['expires_at'], new DateTimeZone('UTC')))->getTimestamp();
        rememberSendIssuedCookie([$selector.':'.$verifier, $expires]);
        $s = $conn->prepare('UPDATE remember_tokens SET last_used_at=UTC_TIMESTAMP(),revoked_at=UTC_TIMESTAMP() WHERE id=? AND revoked_at IS NULL');
        $s->bind_param('i', $token['id']); $s->execute(); $s->close();
        $conn->commit();
        rememberDiagnostic('restore_success', ['user_id'=>(int)$id, 'token_id'=>(int)$replacementId]);
        return true;
    } catch (Throwable $e) {
        $conn->rollback(); rememberClearSessionIdentity(); rememberClearCookie();
        rememberDiagnostic('restore_failed', ['reason'=>'storage_or_cookie_failure']); return false;
    } finally { if ($locked) rememberUnlock($conn); }
}
