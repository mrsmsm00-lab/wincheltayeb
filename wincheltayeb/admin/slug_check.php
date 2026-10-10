<?php
// فحص فوري للرابط وإنت بتكتبه
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/urls.php';
header('Content-Type: application/json; charset=utf-8');
start_session();
if (!current_user()) { http_response_code(401); exit('{"ok":false}'); }
$kind = ($_GET['kind'] ?? 'post') === 'landing' ? 'landing' : 'post';
$type = (string) ($_GET['type'] ?? '');
if ($kind === 'post' && !isset(POST_TYPES[$type])) $type = 'blog';
$id = (int) ($_GET['id'] ?? 0);
$raw = (string) ($_GET['slug'] ?? '');
$slug = $kind === 'landing' ? clean_lp_path($raw) : slugify($raw);
if (trim($raw) === '') { echo json_encode(['ok' => false, 'slug' => '', 'msg' => 'اكتب الرابط.'], JSON_UNESCAPED_UNICODE); exit; }
$c = url_conflict($kind, $slug, $type, $id);
$path = $kind === 'landing' ? "/$slug" : (POST_TYPES[$type]['path'] ? '/' . POST_TYPES[$type]['path'] . "/$slug" : "/$slug");
echo json_encode(['ok' => $c === null, 'slug' => $slug, 'url' => rtrim(cfg('site_url'), '/') . $path, 'msg' => $c ?? 'الرابط متاح ✓',
    'suggest' => $c ? url_suggest($kind, $slug, $type, $id) : null], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
