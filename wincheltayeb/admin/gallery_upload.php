<?php
// رفع جماعي للمعرض (ملف واحد في كل طلب) + وصف تلقائي بالذكاء الاصطناعي
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/ai.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function gout(array $d, int $code = 200): never { http_response_code($code); echo json_encode($d, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); exit; }

start_session();
if (!current_user()) gout(['ok' => false, 'error' => 'انتهت الجلسة. سجّل دخول تاني.'], 401);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') gout(['ok' => false, 'error' => 'method'], 405);
if (!hash_equals($_SESSION['csrf'] ?? '', (string) ($_POST['csrf'] ?? ''))) gout(['ok' => false, 'error' => 'حدّث الصفحة وجرب تاني.'], 400);
session_write_close();
@set_time_limit(120);

$action = $_POST['action'] ?? 'upload';

if ($action === 'update') {
    $id = (int) ($_POST['id'] ?? 0);
    $title = post_str('title', 200);
    if ($title === '') gout(['ok' => false, 'error' => 'العنوان مطلوب.'], 400);
    $st = db()->prepare('UPDATE gallery SET title = ?, caption = ?, area_slug = ? WHERE id = ?');
    $st->execute([$title, post_str('caption', 1000), post_str('area_slug', 120), $id]);
    revalidate_site();
    gout(['ok' => true]);
}

if ($action === 'finish') {
    $r = revalidate_site();
    gout(['ok' => true, 'revalidate' => $r['msg'], 'revalidate_ok' => $r['ok']]);
}

// upload
$areaSlug = post_str('area_slug', 120);
$areaName = '';
if ($areaSlug !== '') {
    $s = db()->prepare("SELECT COALESCE(NULLIF(place_name,''), title) FROM posts WHERE type = 'area' AND slug = ?");
    $s->execute([$areaSlug]);
    $areaName = (string) ($s->fetchColumn() ?: '');
}
try {
    $url = handle_upload('file', ['image', 'video']);
    if (!$url) gout(['ok' => false, 'error' => 'مفيش ملف.'], 400);
} catch (RuntimeException $e) {
    gout(['ok' => false, 'error' => $e->getMessage()], 400);
}
$kind = preg_match('/\.(mp4|webm)$/i', $url) ? 'video' : 'image';
$localPath = __DIR__ . '/uploads/' . substr($url, strlen(rtrim(cfg('admin_url'), '/') . '/uploads/'));
$base = pathinfo((string) ($_FILES['file']['name'] ?? ''), PATHINFO_FILENAME);
$fallback = trim(preg_replace('/[_\-\.]+/u', ' ', $base));
if ($fallback === '' || preg_match('/^(img|image|dsc|photo|pxl|vid|video|whatsapp)?[\s\d]*$/i', $fallback) || preg_match('/^\d/', $fallback)) {
    $fallback = ($kind === 'video' ? 'فيديو' : 'صورة') . ' من شغل ونش الطيب' . ($areaName ? " في $areaName" : '');
}
$title = $fallback; $caption = ''; $aiUsed = false; $aiError = ''; $privacy = false; $relevant = true;

if ($kind === 'image' && !empty($_POST['ai'])) {
    if (ai_key() === '') $aiError = 'مفتاح OpenAI مش متظبط.';
    elseif (ai_usage_today() >= ai_daily_limit()) $aiError = 'وصلت للحد اليومي للـ AI.';
    else {
        try {
            $mime = (new finfo(FILEINFO_MIME_TYPE))->file($localPath);
            $d = ai_describe_image($localPath, $mime, $areaName);
            ai_bump_usage();
            if ($d['title'] !== '') { $title = $d['title']; $caption = $d['caption']; $aiUsed = true; }
            $privacy = $d['privacy']; $relevant = $d['relevant'];
        } catch (RuntimeException $e) { $aiError = $e->getMessage(); }
    }
}

$st = db()->prepare('INSERT INTO gallery (kind, url, thumb, youtube_id, title, caption, area_slug, sort) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
$st->execute([$kind, $url, '', '', $title, $caption, $areaSlug, (int) ($_POST['sort'] ?? 0)]);
gout(['ok' => true, 'item' => ['id' => (int) db()->lastInsertId(), 'kind' => $kind, 'url' => $url, 'title' => $title, 'caption' => $caption],
    'ai_used' => $aiUsed, 'ai_error' => $aiError, 'privacy' => $privacy, 'relevant' => $relevant, 'usage' => ai_usage_today()]);
