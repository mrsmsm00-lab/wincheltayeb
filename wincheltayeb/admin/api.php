<?php
// API عام للقراءة فقط، موقع Next.js بيسحب منه المحتوى
require __DIR__ . '/includes/bootstrap.php';

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, cfg('allowed_origins'), true)) { header('Access-Control-Allow-Origin: ' . $origin); header('Vary: Origin'); }
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=60');
header('X-Robots-Tag: noindex');
if ($_SERVER['REQUEST_METHOD'] !== 'GET') { http_response_code(405); exit('{"error":"method"}'); }
if (!is_installed()) { http_response_code(503); exit('{"error":"not installed"}'); }

$resource = $_GET['resource'] ?? 'all';
$out = [];
if (in_array($resource, ['all', 'settings'], true)) {
    $settings = [];
    $private = ['openai_api_key', 'openai_model', 'ai_daily_limit'];
    foreach (db()->query("SELECT key, value FROM settings WHERE key NOT LIKE '\\_%' ESCAPE '\\'") as $r) {
        if (!in_array($r['key'], $private, true)) $settings[$r['key']] = $r['value'];
    }
    $out['settings'] = $settings;
    $out['districts'] = districts();
}
if (in_array($resource, ['all', 'posts'], true)) {
    $posts = db()->query('SELECT type, slug, title, excerpt, body, format, meta_title, meta_description, image, icon, district, place_name, lat, lng, eta, faqs, published, sort, published_at, updated_at FROM posts WHERE published = 1 ORDER BY sort, id')->fetchAll();
    foreach ($posts as &$p) { $p['faqs'] = json_decode($p['faqs'], true) ?: []; $p['lat'] = $p['lat'] === null ? null : (float) $p['lat']; $p['lng'] = $p['lng'] === null ? null : (float) $p['lng']; }
    $out['posts'] = $posts;
}
if (in_array($resource, ['all', 'landing'], true)) {
    $out['landing'] = array_map(fn($r) => ['slug' => $r['slug'], 'template' => $r['template'], 'noindex' => (int) $r['noindex'], 'updated_at' => $r['updated_at'], 'data' => json_decode($r['data'], true) ?: []],
        db()->query('SELECT slug, template, noindex, data, updated_at FROM landing_pages WHERE published = 1')->fetchAll());
}
if ($resource === 'landing_preview') {
    require __DIR__ . '/includes/landing.php';
    $slug = clean_lp_path((string) ($_GET['slug'] ?? ''));
    if (!hash_equals(lp_preview_token($slug), (string) ($_GET['token'] ?? ''))) { http_response_code(403); exit('{"error":"token"}'); }
    $s = db()->prepare('SELECT slug, template, noindex, data, updated_at FROM landing_pages WHERE slug = ?'); $s->execute([$slug]);
    $r = $s->fetch();
    header('Cache-Control: no-store');
    echo json_encode($r ? ['landing' => ['slug' => $r['slug'], 'template' => $r['template'], 'noindex' => 1, 'updated_at' => $r['updated_at'], 'data' => json_decode($r['data'], true) ?: []]] : ['landing' => null], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
if (in_array($resource, ['all', 'gallery'], true)) {
    $out['gallery'] = db()->query('SELECT id, kind, url, thumb, youtube_id, title, caption, area_slug, created_at FROM gallery ORDER BY sort, id DESC')->fetchAll();
}
echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
