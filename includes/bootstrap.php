<?php
declare(strict_types=1);

/**
 * Shared application bootstrap.
 *
 * Keeps URL generation, session hardening, CSRF handling, and redirects
 * consistent for entry points and authenticated pages.
 */

if (!function_exists('appBasePath')) {
    function appBasePath(): string
    {
        static $basePath = null;

        if ($basePath !== null) {
            return $basePath;
        }

        $projectRoot = realpath(dirname(__DIR__));
        $documentRoot = isset($_SERVER['DOCUMENT_ROOT'])
            ? realpath((string) $_SERVER['DOCUMENT_ROOT'])
            : false;

        if ($projectRoot !== false && $documentRoot !== false) {
            $project = str_replace('\\', '/', $projectRoot);
            $document = rtrim(str_replace('\\', '/', $documentRoot), '/');
            $projectForCompare = PHP_OS_FAMILY === 'Windows' ? strtolower($project) : $project;
            $documentForCompare = PHP_OS_FAMILY === 'Windows' ? strtolower($document) : $document;

            if ($projectForCompare === $documentForCompare) {
                return $basePath = '';
            }

            if (str_starts_with($projectForCompare, $documentForCompare . '/')) {
                return $basePath = '/' . trim(substr($project, strlen($document)), '/');
            }
        }

        $scriptPath = parse_url((string) ($_SERVER['SCRIPT_NAME'] ?? ''), PHP_URL_PATH) ?: '';
        foreach (['/pages/', '/api/', '/includes/'] as $marker) {
            $position = strpos($scriptPath, $marker);
            if ($position !== false) {
                return $basePath = rtrim(substr($scriptPath, 0, $position), '/');
            }
        }

        $directory = str_replace('\\', '/', dirname($scriptPath));
        return $basePath = ($directory === '/' || $directory === '.') ? '' : rtrim($directory, '/');
    }

    function appUrl(string $path = ''): string
    {
        $base = appBasePath();
        $path = ltrim($path, '/');

        return ($base === '' ? '' : $base) . '/' . $path;
    }

    function appAssetUrl(string $path): string
    {
        $normalizedPath = ltrim(str_replace('\\', '/', $path), '/');
        $assetPath = dirname(__DIR__) . DIRECTORY_SEPARATOR
            . str_replace('/', DIRECTORY_SEPARATOR, $normalizedPath);
        $modifiedAt = is_file($assetPath) ? filemtime($assetPath) : false;
        $version = $modifiedAt === false ? '' : '?v=' . $modifiedAt;

        return appUrl($normalizedPath) . $version;
    }

    function appEscape(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    function appIsHttps(): bool
    {
        $https = strtolower((string) ($_SERVER['HTTPS'] ?? ''));
        return ($https !== '' && $https !== 'off') || (int) ($_SERVER['SERVER_PORT'] ?? 0) === 443;
    }

    function appStartSession(): void
    {
        if (session_status() !== PHP_SESSION_NONE) {
            return;
        }

        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.cookie_httponly', '1');
        ini_set('session.cookie_samesite', 'Lax');

        $cookiePath = appBasePath();
        $cookiePath = $cookiePath === '' ? '/' : rtrim($cookiePath, '/') . '/';
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => $cookiePath,
            'domain' => '',
            'secure' => appIsHttps(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        session_start();
    }

    function appCsrfToken(): string
    {
        if (!isset($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token']) || strlen($_SESSION['csrf_token']) < 32) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['csrf_token'];
    }

    function appVerifyCsrf(?string $token): bool
    {
        return is_string($token)
            && isset($_SESSION['csrf_token'])
            && is_string($_SESSION['csrf_token'])
            && hash_equals($_SESSION['csrf_token'], $token);
    }

    function appRotateCsrfToken(): string
    {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        return $_SESSION['csrf_token'];
    }

    function appSessionUserId(): ?int
    {
        $userId = filter_var($_SESSION['user_id'] ?? null, FILTER_VALIDATE_INT);
        return $userId !== false && $userId > 0 ? $userId : null;
    }

    function appSessionRole(): ?string
    {
        $role = $_SESSION['role'] ?? null;
        return is_string($role) && in_array($role, ['owner', 'staff'], true) ? $role : null;
    }

    function appIsAuthenticated(): bool
    {
        return appSessionUserId() !== null && appSessionRole() !== null;
    }

    function appRoleHome(?string $role = null): string
    {
        return appUrl(($role ?? appSessionRole()) === 'owner'
            ? 'pages/dashboard.php'
            : 'pages/pos.php');
    }

    function appRedirect(string $location, int $status = 302): never
    {
        header('Location: ' . $location, true, $status);
        exit;
    }

    function appSafeReturnPath(?string $target, string $role): ?string
    {
        if ($target === null || $target === '' || strlen($target) > 1024 || preg_match('/[\\x00-\\x1F\\x7F\\\\]/', $target)) {
            return null;
        }

        $parts = parse_url($target);
        if ($parts === false || isset($parts['scheme']) || isset($parts['host']) || isset($parts['user']) || isset($parts['pass'])) {
            return null;
        }

        $path = $parts['path'] ?? '';
        $allowed = [
            appUrl('pages/pos.php'),
            appUrl('pages/history.php'),
            appUrl('pages/settings.php'),
        ];

        if ($role === 'owner') {
            $allowed[] = appUrl('pages/dashboard.php');
            $allowed[] = appUrl('pages/stock.php');
            $allowed[] = appUrl('pages/report.php');
        }

        if (!in_array($path, $allowed, true)) {
            return null;
        }

        $query = isset($parts['query']) && $parts['query'] !== '' ? '?' . $parts['query'] : '';
        return $path . $query;
    }

    function appRememberIntendedPath(): void
    {
        if (!in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', ['GET', 'HEAD'], true)) {
            return;
        }

        $requestUri = (string) ($_SERVER['REQUEST_URI'] ?? '');
        $path = parse_url($requestUri, PHP_URL_PATH);
        if (!is_string($path) || !str_starts_with($path, appUrl('pages/'))) {
            return;
        }

        $_SESSION['return_to'] = substr($requestUri, 0, 1024);
    }

    function appTakeIntendedPath(string $role): ?string
    {
        $target = isset($_SESSION['return_to']) && is_string($_SESSION['return_to'])
            ? $_SESSION['return_to']
            : null;
        unset($_SESSION['return_to']);

        return appSafeReturnPath($target, $role);
    }

    function appResetSession(array $preserve = []): void
    {
        $_SESSION = [];
        session_regenerate_id(true);
        foreach ($preserve as $key => $value) {
            $_SESSION[$key] = $value;
        }
        appRotateCsrfToken();
    }

    function appDestroySession(): void
    {
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires' => time() - 42000,
                'path' => $params['path'],
                'domain' => $params['domain'],
                'secure' => (bool) $params['secure'],
                'httponly' => (bool) $params['httponly'],
                'samesite' => $params['samesite'] ?? 'Lax',
            ]);
        }

        session_destroy();
    }

    function appIndonesianDate(?DateTimeInterface $date = null): string
    {
        $date ??= new DateTimeImmutable();
        $days = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
        $months = [1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
            'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

        return $days[(int) $date->format('w')] . ', '
            . $date->format('d') . ' ' . $months[(int) $date->format('n')] . ' ' . $date->format('Y');
    }
}

appStartSession();
