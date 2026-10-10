<?php
declare(strict_types=1);

foreach (['pdo_sqlite', 'mbstring', 'fileinfo', 'curl'] as $ext) {
    if (!extension_loaded($ext)) { http_response_code(500); exit("إضافة PHP ناقصة: $ext. فعّلها من cPanel > Select PHP Version."); }
}

$CONFIG = require __DIR__ . '/../config.php';
date_default_timezone_set('Africa/Cairo');
mb_internal_encoding('UTF-8');

const POST_TYPES = [
    'service' => ['label' => 'الخدمات', 'one' => 'خدمة', 'path' => 'services'],
    'area'    => ['label' => 'المناطق', 'one' => 'منطقة', 'path' => 'areas'],
    'blog'    => ['label' => 'المقالات', 'one' => 'مقال', 'path' => 'blog'],
    'page'    => ['label' => 'الصفحات', 'one' => 'صفحة', 'path' => ''],
];
const ICONS = ['car' => 'عربية', 'truck' => 'نقل', 'bike' => 'موتوسيكل', 'tools' => 'عدة', 'alert' => 'تحذير', 'gear' => 'فتيس'];

function cfg(string $k) { global $CONFIG; return $CONFIG[$k] ?? null; }

function db(): PDO {
    static $pdo = null;
    if ($pdo) return $pdo;
    $path = cfg('db_path');
    if (!is_dir(dirname($path))) mkdir(dirname($path), 0750, true);
    $pdo = new PDO('sqlite:' . $path, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec('PRAGMA journal_mode = WAL; PRAGMA foreign_keys = ON; PRAGMA busy_timeout = 5000;');
    migrate($pdo);
    return $pdo;
}

function migrate(PDO $pdo): void {
    $pdo->exec("
    CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT NOT NULL UNIQUE,
        password_hash TEXT NOT NULL,
        created_at TEXT NOT NULL DEFAULT (datetime('now'))
    );
    CREATE TABLE IF NOT EXISTS posts (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        type TEXT NOT NULL CHECK (type IN ('service','area','blog','page')),
        slug TEXT NOT NULL,
        title TEXT NOT NULL,
        excerpt TEXT NOT NULL DEFAULT '',
        body TEXT NOT NULL DEFAULT '',
        meta_title TEXT NOT NULL DEFAULT '',
        meta_description TEXT NOT NULL DEFAULT '',
        image TEXT NOT NULL DEFAULT '',
        icon TEXT NOT NULL DEFAULT '',
        district TEXT NOT NULL DEFAULT '',
        place_name TEXT NOT NULL DEFAULT '',
        lat REAL, lng REAL,
        eta TEXT NOT NULL DEFAULT '',
        faqs TEXT NOT NULL DEFAULT '[]',
        published INTEGER NOT NULL DEFAULT 1,
        sort INTEGER NOT NULL DEFAULT 0,
        published_at TEXT NOT NULL DEFAULT (date('now')),
        updated_at TEXT NOT NULL DEFAULT (date('now')),
        UNIQUE (type, slug)
    );
    CREATE TABLE IF NOT EXISTS gallery (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        kind TEXT NOT NULL CHECK (kind IN ('image','video','youtube')),
        url TEXT NOT NULL DEFAULT '',
        thumb TEXT NOT NULL DEFAULT '',
        youtube_id TEXT NOT NULL DEFAULT '',
        title TEXT NOT NULL,
        caption TEXT NOT NULL DEFAULT '',
        area_slug TEXT NOT NULL DEFAULT '',
        sort INTEGER NOT NULL DEFAULT 0,
        created_at TEXT NOT NULL DEFAULT (datetime('now'))
    );
    CREATE TABLE IF NOT EXISTS settings (key TEXT PRIMARY KEY, value TEXT NOT NULL DEFAULT '');
    CREATE TABLE IF NOT EXISTS districts (key TEXT PRIMARY KEY, name TEXT NOT NULL, color TEXT NOT NULL DEFAULT '#0B4EA2', sort INTEGER NOT NULL DEFAULT 0);
    CREATE TABLE IF NOT EXISTS login_attempts (ip TEXT PRIMARY KEY, attempts INTEGER NOT NULL, last_at INTEGER NOT NULL);
    ");
}

function is_installed(): bool {
    return (int) db()->query('SELECT COUNT(*) FROM users')->fetchColumn() > 0;
}

/* ---------- Session / auth ---------- */
function start_session(): void {
    if (session_status() === PHP_SESSION_ACTIVE) return;
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    session_name('wt_admin');
    session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'secure' => $https, 'httponly' => true, 'samesite' => 'Lax']);
    session_start();
}

function current_user(): ?array { start_session(); return $_SESSION['user'] ?? null; }

function require_login(): array {
    if (!is_installed()) redirect('install.php');
    $u = current_user();
    if (!$u) redirect('login.php');
    if (time() - ($_SESSION['last_seen'] ?? 0) > 60 * 60 * 8) { session_destroy(); redirect('login.php'); }
    $_SESSION['last_seen'] = time();
    return $u;
}

function client_ip(): string { return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'; }

function login_blocked(): bool {
    $r = db()->prepare('SELECT attempts, last_at FROM login_attempts WHERE ip = ?');
    $r->execute([client_ip()]);
    $row = $r->fetch();
    return $row && $row['attempts'] >= 5 && time() - $row['last_at'] < 900;
}
function login_failed(): void {
    db()->prepare("INSERT INTO login_attempts (ip, attempts, last_at) VALUES (?, 1, ?)
        ON CONFLICT(ip) DO UPDATE SET attempts = CASE WHEN ? - last_at > 900 THEN 1 ELSE attempts + 1 END, last_at = excluded.last_at")
        ->execute([client_ip(), time(), time()]);
}
function login_reset(): void { db()->prepare('DELETE FROM login_attempts WHERE ip = ?')->execute([client_ip()]); }

/* ---------- CSRF / helpers ---------- */
function csrf_token(): string {
    start_session();
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}
function csrf_field(): string { return '<input type="hidden" name="csrf" value="' . h(csrf_token()) . '">'; }
function csrf_check(): void {
    start_session();
    if (!hash_equals($_SESSION['csrf'] ?? '', (string) ($_POST['csrf'] ?? ''))) {
        http_response_code(400);
        exit('انتهت صلاحية الصفحة. ارجع وحدّث الصفحة وجرب تاني.');
    }
}
function h($s): string { return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function redirect(string $to): never { header('Location: ' . $to); exit; }
function flash(?string $msg = null, string $kind = 'ok') {
    start_session();
    if ($msg !== null) { $_SESSION['flash'] = [$msg, $kind]; return null; }
    $f = $_SESSION['flash'] ?? null; unset($_SESSION['flash']); return $f;
}
function post_str(string $k, int $max = 100000): string { return mb_substr(trim((string) ($_POST[$k] ?? '')), 0, $max); }

function slugify(string $s): string {
    $s = mb_strtolower(trim($s));
    $s = preg_replace('/[^\p{L}\p{N}]+/u', '-', $s);
    return trim($s, '-') ?: 'item-' . time();
}

/** "س: سؤال" / "ج: إجابة" → [{q,a}] */
function parse_faqs(string $text): array {
    $out = []; $q = null;
    foreach (preg_split('/\R/u', $text) as $line) {
        $line = trim($line);
        if ($line === '') continue;
        if (preg_match('/^(س|q)\s*[:：]\s*(.+)$/iu', $line, $m)) { $q = $m[2]; continue; }
        if (preg_match('/^(ج|a)\s*[:：]\s*(.+)$/iu', $line, $m) && $q !== null) { $out[] = ['q' => $q, 'a' => $m[2]]; $q = null; continue; }
        if ($out && $q === null) $out[count($out) - 1]['a'] .= ' ' . $line;
    }
    return $out;
}
function faqs_to_text($faqs): string {
    if (is_string($faqs)) $faqs = json_decode($faqs, true) ?: [];
    return implode("\n\n", array_map(fn($f) => "س: {$f['q']}\nج: {$f['a']}", $faqs));
}

function setting(string $k, string $default = ''): string {
    $s = db()->prepare('SELECT value FROM settings WHERE key = ?'); $s->execute([$k]);
    $v = $s->fetchColumn(); return $v === false ? $default : (string) $v;
}
function set_setting(string $k, string $v): void {
    db()->prepare('INSERT INTO settings (key, value) VALUES (?, ?) ON CONFLICT(key) DO UPDATE SET value = excluded.value')->execute([$k, $v]);
}
function districts(): array {
    $out = [];
    foreach (db()->query('SELECT * FROM districts ORDER BY sort, key') as $d) $out[$d['key']] = ['name' => $d['name'], 'color' => $d['color']];
    return $out;
}

/** بيبلغ موقع Next.js إن المحتوى اتغير */
function revalidate_site(): array {
    $url = cfg('revalidate_url'); $secret = cfg('revalidate_secret');
    if (!$url || !$secret) { $r = ['ok' => false, 'msg' => 'revalidate_secret مش متظبط في config.local.php']; }
    else {
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 8,
            CURLOPT_HTTPHEADER => ['x-revalidate-secret: ' . $secret, 'Content-Type: application/json'], CURLOPT_POSTFIELDS => '{}']);
        $body = curl_exec($ch); $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE); $err = curl_error($ch); curl_close($ch);
        $r = $code === 200 ? ['ok' => true, 'msg' => 'الموقع اتحدث'] : ['ok' => false, 'msg' => "فشل تحديث الموقع ($code) $err"];
    }
    set_setting('_last_revalidate', json_encode($r + ['at' => date('Y-m-d H:i:s')], JSON_UNESCAPED_UNICODE));
    return $r;
}

function public_url(string $type, string $slug): string {
    $p = POST_TYPES[$type]['path'];
    return rtrim(cfg('site_url'), '/') . ($p ? "/$p/" : '/') . rawurlencode($slug);
}

/* ---------- Uploads ---------- */
const UPLOAD_TYPES = [
    'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif',
    'video/mp4' => 'mp4', 'video/webm' => 'webm',
];
function handle_upload(string $field, array $allowedKinds = ['image', 'video']): ?string {
    if (empty($_FILES[$field]) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) return null;
    $f = $_FILES[$field];
    if ($f['error'] !== UPLOAD_ERR_OK) throw new RuntimeException('الرفع فشل (كود ' . $f['error'] . '). ممكن الملف أكبر من حد السيرفر.');
    if ($f['size'] > cfg('max_upload_mb') * 1024 * 1024) throw new RuntimeException('الملف أكبر من ' . cfg('max_upload_mb') . ' ميجا.');
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
    $ext = UPLOAD_TYPES[$mime] ?? null;
    if (!$ext || !in_array(explode('/', $mime)[0], $allowedKinds, true)) throw new RuntimeException('نوع الملف مش مسموح: ' . $mime);
    $dir = 'uploads/' . date('Y/m');
    if (!is_dir(__DIR__ . '/../' . $dir)) mkdir(__DIR__ . '/../' . $dir, 0755, true);
    $name = bin2hex(random_bytes(10)) . '.' . $ext;
    if (!move_uploaded_file($f['tmp_name'], __DIR__ . '/../' . $dir . '/' . $name)) throw new RuntimeException('مقدرتش أحفظ الملف على السيرفر.');
    return rtrim(cfg('admin_url'), '/') . '/' . $dir . '/' . $name;
}
function youtube_id(string $url): ?string {
    if (preg_match('~(?:youtu\.be/|youtube\.com/(?:watch\?(?:.*&)?v=|embed/|shorts/|live/))([A-Za-z0-9_-]{11})~', $url, $m)) return $m[1];
    return preg_match('~^[A-Za-z0-9_-]{11}$~', $url) ? $url : null;
}
