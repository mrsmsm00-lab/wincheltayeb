<?php
// إعدادات الصفحة الرئيسية والقايمة: القيم الافتراضية + القراءة والتنضيف

const SECTION_TYPES = [
    'hero' => 'الواجهة (اللافتة)', 'services' => 'الخدمات', 'map' => 'خريطة التغطية', 'areas' => 'المناطق',
    'steps' => 'خطوات الطلب', 'articles' => 'آخر المقالات', 'gallery' => 'صور وفيديوهات', 'faq' => 'الأسئلة الشائعة',
    'cta' => 'شريط الاتصال', 'custom' => 'قسم مخصص',
];
const SECTION_BGS = ['white' => 'أبيض', 'mist' => 'رمادي فاتح', 'ink' => 'أسود', 'blue' => 'أزرق'];
const SECTION_FIELDS = [
    'hero' => ['kicker' => 's', 'title' => 's', 'lead' => 't', 'facts' => 't', 'sign_rows' => 's', 'show_buttons' => 'b'],
    'services' => ['title' => 's', 'more_label' => 's', 'more_href' => 'u', 'bg' => 'bg'],
    'map' => ['title' => 's', 'lead' => 't', 'height' => 'i', 'more_label' => 's', 'more_href' => 'u', 'bg' => 'bg'],
    'areas' => ['title' => 's', 'limit' => 'i', 'bg' => 'bg'],
    'steps' => ['title' => 's', 'items' => 't', 'bg' => 'bg'],
    'articles' => ['title' => 's', 'count' => 'i', 'more_label' => 's', 'more_href' => 'u', 'bg' => 'bg'],
    'gallery' => ['title' => 's', 'count' => 'i', 'more_label' => 's', 'more_href' => 'u', 'bg' => 'bg'],
    'faq' => ['title' => 's', 'bg' => 'bg'],
    'cta' => ['text' => 's', 'bg' => 'bg'],
    'custom' => ['title' => 's', 'body' => 'h', 'format' => 'f', 'bg' => 'bg', 'width' => 'w', 'anchor' => 'a'],
];

function site_defaults(): array {
    static $d = null;
    return $d ??= json_decode((string) file_get_contents(__DIR__ . '/site-defaults.json'), true);
}

function safe_href(string $u): string {
    $u = trim($u);
    if ($u === '') return '';
    if (preg_match('~^(/|#|https?://|tel:|mailto:)~i', $u) && !preg_match('~^\s*javascript:~i', $u)) return mb_substr($u, 0, 500);
    return '';
}

function clean_section(array $in, string $type): array {
    $out = ['id' => preg_replace('/[^a-z0-9_-]/i', '', (string) ($in['id'] ?? '')) ?: 'c_' . bin2hex(random_bytes(4)), 'type' => $type, 'enabled' => !empty($in['enabled'])];
    foreach (SECTION_FIELDS[$type] as $f => $kind) {
        $v = $in[$f] ?? null;
        $out[$f] = match ($kind) {
            's' => mb_substr(trim((string) $v), 0, 300),
            't' => mb_substr(str_replace("\r\n", "\n", trim((string) $v)), 0, 5000),
            'h' => mb_substr(str_replace("\r\n", "\n", (string) $v), 0, 200000),
            'i' => max(0, min(2000, (int) $v)),
            'b' => !empty($v),
            'u' => safe_href((string) $v),
            'bg' => array_key_exists((string) $v, SECTION_BGS) ? (string) $v : 'white',
            'f' => clean_format($v),
            'w' => $v === 'narrow' ? 'narrow' : ($v === 'full' ? 'full' : 'normal'),
            'a' => preg_replace('/[^a-z0-9-]/', '', strtolower((string) $v)),
        };
    }
    return $out;
}

/** الأقسام المحفوظة، ولو فيه نوع أساسي جديد مش موجود بيتضاف في الآخر مخفي */
function home_sections(): array {
    $saved = json_decode(setting('home_sections', ''), true);
    $list = is_array($saved) && $saved ? $saved : site_defaults()['home_sections'];
    $have = array_column($list, 'type');
    foreach (site_defaults()['home_sections'] as $d) {
        if (!in_array($d['type'], $have, true)) { $d['enabled'] = false; $list[] = $d; }
    }
    return array_values(array_filter($list, fn($s) => isset(SECTION_TYPES[$s['type'] ?? ''])));
}

function nav_menu(): array {
    $saved = json_decode(setting('nav_menu', ''), true);
    return is_array($saved) && $saved ? $saved : site_defaults()['nav_menu'];
}

function clean_menu_item(array $in): ?array {
    $label = mb_substr(trim((string) ($in['label'] ?? '')), 0, 60);
    $href = safe_href((string) ($in['href'] ?? ''));
    if ($label === '' || $href === '') return null;
    return ['label' => $label, 'href' => $href, 'enabled' => !empty($in['enabled']), 'new_tab' => !empty($in['new_tab']),
        'style' => ($in['style'] ?? '') === 'button' ? 'button' : 'normal'];
}
