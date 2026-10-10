<?php
// التحقق من الروابط: مفيش رابطين في الموقع يدخلوا في بعض

/** مسارات محجوزة للموقع نفسه */
const RESERVED_PATHS = ['services', 'areas', 'blog', 'gallery', 'about', 'contact', 'suez-map', 'api', 'admin', '_next',
    'sitemap.xml', 'video-sitemap.xml', 'robots.txt', 'llms.txt', 'llms-full.txt', 'feed.xml', 'indexnow.txt',
    'manifest.webmanifest', 'icon.svg', 'logo.svg', 'og.png', 'favicon.ico', 'search', 'login', 'wp-admin', 'index'];

/** بينضّف مسار صفحة هبوط: حرف أو رقم أو - ، ولحد مستويين (ads/sokhna) */
function clean_lp_path(string $p): string {
    $p = mb_strtolower(trim($p));
    $p = preg_replace('~^https?://[^/]+~', '', $p);
    $parts = array_values(array_filter(array_map(fn($x) => trim(preg_replace('/[^\p{L}\p{N}]+/u', '-', $x), '-'), explode('/', $p)), 'strlen'));
    return implode('/', array_slice($parts, 0, 2));
}

/**
 * هل الرابط ده مستخدم؟
 * $kind: 'post' (مع $type) أو 'landing'. $exceptId: العنصر اللي بنعدّله نفسه
 * بيرجع null لو متاح، أو رسالة توضح مين مستخدمه
 */
function url_conflict(string $kind, string $slug, string $type = '', int $exceptId = 0): ?string {
    if ($slug === '') return 'الرابط فاضي.';
    $first = explode('/', $slug)[0];
    $rootLevel = $kind === 'landing' || $type === 'page';
    if ($rootLevel && in_array($first, RESERVED_PATHS, true)) return "«/{$first}» محجوز لقسم في الموقع. اختار اسم تاني.";
    if ($kind === 'post' && $type !== 'page') {
        $s = db()->prepare('SELECT title FROM posts WHERE type = ? AND slug = ? AND id != ?');
        $s->execute([$type, $slug, $kind === 'post' ? $exceptId : 0]);
        if ($t = $s->fetchColumn()) return "الرابط ده مستخدم في «{$t}».";
        return null;
    }
    // المستوى الأول من الدومين: صفحات + صفحات هبوط
    $s = db()->prepare("SELECT title FROM posts WHERE type = 'page' AND slug = ? AND id != ?");
    $s->execute([$slug, $kind === 'post' ? $exceptId : 0]);
    if ($t = $s->fetchColumn()) return "الرابط ده مستخدم في صفحة «{$t}».";
    $s = db()->prepare('SELECT name FROM landing_pages WHERE slug = ? AND id != ?');
    $s->execute([$slug, $kind === 'landing' ? $exceptId : 0]);
    if ($t = $s->fetchColumn()) return "الرابط ده مستخدم في صفحة الهبوط «{$t}».";
    return null;
}

function url_suggest(string $kind, string $slug, string $type = '', int $exceptId = 0): string {
    for ($i = 2; $i < 50; $i++) { $c = "$slug-$i"; if (!url_conflict($kind, $c, $type, $exceptId)) return $c; }
    return $slug . '-' . substr(bin2hex(random_bytes(3)), 0, 4);
}
