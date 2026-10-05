<?php
declare(strict_types=1);

/**
 * ตรวจสอบ UP Account — คืนค่าข้อมูลผู้ใช้ หรือ null ถ้าไม่ผ่าน
 * @return array{login:string,name:string,department:string,phone:string,position:string}|null
 */
function authenticate(string $username, string $password): ?array
{
    $username = trim($username);
    if ($username === '' || $password === '' || !preg_match('/^[A-Za-z0-9._@-]{1,100}$/', $username)) {
        return null;
    }

    return match (config('auth.mode')) {
        'ldap'  => auth_ldap($username, $password),
        'dev'   => ['login' => strtolower($username), 'name' => '', 'department' => '', 'phone' => '', 'position' => ''],
        default => throw new RuntimeException('Unknown auth.mode in config.php'),
    };
}

function auth_ldap(string $username, string $password): ?array
{
    if (!function_exists('ldap_connect')) {
        throw new RuntimeException('PHP ldap extension is not enabled (php.ini: extension=ldap)');
    }
    $c = config('auth.ldap');
    $conn = ldap_connect($c['uri']);
    if (!$conn) {
        return null;
    }
    ldap_set_option($conn, LDAP_OPT_PROTOCOL_VERSION, 3);
    ldap_set_option($conn, LDAP_OPT_REFERRALS, 0);
    ldap_set_option($conn, LDAP_OPT_NETWORK_TIMEOUT, 10);

    $bindRdn = sprintf($c['bind_format'], ldap_escape($username, '', LDAP_ESCAPE_DN));
    if (!@ldap_bind($conn, $bindRdn, $password)) {
        ldap_unbind($conn);
        return null;
    }

    $user = ['login' => strtolower($username), 'name' => '', 'department' => '', 'phone' => '', 'position' => ''];
    $attrs = array_filter([
        'name'       => $c['attr_name'] ?? '',
        'department' => $c['attr_department'] ?? '',
        'phone'      => $c['attr_phone'] ?? '',
        'position'   => $c['attr_position'] ?? '',
    ]);
    if ($attrs && !empty($c['base_dn'])) {
        $filter = sprintf($c['search_filter'], ldap_escape($username, '', LDAP_ESCAPE_FILTER));
        $sr = @ldap_search($conn, $c['base_dn'], $filter, array_values($attrs));
        $entries = $sr ? ldap_get_entries($conn, $sr) : [];
        if (!empty($entries['count'])) {
            foreach ($attrs as $key => $attr) {
                $user[$key] = (string)($entries[0][strtolower($attr)][0] ?? '');
            }
        }
    }
    ldap_unbind($conn);
    return $user;
}

function current_user(): ?array
{
    start_session();
    return $_SESSION['user'] ?? null;
}

function require_login(): array
{
    $user = current_user();
    if (!$user) {
        $back = $_SERVER['REQUEST_URI'] ?? '';
        redirect('login.php' . ($back ? '?next=' . urlencode($back) : ''));
    }
    return $user;
}

function login_user(array $user): void
{
    start_session();
    session_regenerate_id(true);
    $_SESSION['user'] = $user;
}

function logout_user(): void
{
    start_session();
    $_SESSION = [];
    session_destroy();
}
