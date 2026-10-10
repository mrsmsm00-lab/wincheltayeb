<?php
// صفحات الهبوط: القوالب والحقول والتنضيف
require_once __DIR__ . '/urls.php';

function lp_templates(): array {
    static $t = null;
    if ($t === null) { $t = []; foreach (json_decode((string) file_get_contents(__DIR__ . '/lp-templates.json'), true) as $x) $t[$x['id']] = $x; }
    return $t;
}
const LP_BUTTON_STYLES = ['' => 'زي القالب', 'sticky' => 'شريط ثابت تحت', 'floating' => 'أزرار عائمة في الركن', 'hero' => 'أزرار كبيرة في الواجهة', 'topbar' => 'شريط ثابت فوق', 'side' => 'زرار جانبي'];
const LP_FIELDS = [
    'badge' => 's', 'headline' => 's', 'subheadline' => 't', 'eta' => 's', 'area_name' => 's', 'keyword_default' => 's', 'hero_image' => 'u',
    'call_label' => 's', 'wa_label' => 's', 'wa_message' => 's', 'phone' => 's', 'whatsapp' => 's', 'button_style' => 'b',
    'trust' => 't', 'services' => 't', 'steps' => 't', 'body' => 'h', 'body_format' => 'f', 'images' => 't', 'faqs' => 'faq',
    'final_cta' => 's', 'meta_title' => 's', 'meta_description' => 't', 'primary_override' => 'c',
];

function lp_defaults(): array {
    return [
        'badge' => 'متاحين دلوقتي 24 ساعة', 'headline' => '{keyword} في {area} خلال دقايق', 'area_name' => 'السويس', 'keyword_default' => 'ونش إنقاذ',
        'subheadline' => 'عربيتك عطلت أو عملت حادثة؟ اتصل دلوقتي، هنقولك السعر النهائي ووقت الوصول قبل ما الونش يتحرك.',
        'eta' => 'بنوصلك في المتوسط خلال 30 دقيقة', 'hero_image' => '',
        'call_label' => 'اتصل الآن', 'wa_label' => 'ابعت لوكيشن واتساب', 'wa_message' => 'السلام عليكم، محتاج ونش إنقاذ في {area}. ده اللوكيشن:',
        'phone' => '', 'whatsapp' => '', 'button_style' => '',
        'trust' => "شغالين 24 ساعة حتى في الأعياد\nسعر نهائي في التليفون قبل الحركة\nسطحات هيدروليك للملاكي والأوتوماتيك\nبنصوّر العربية قدامك قبل التحميل",
        'services' => "ونش ملاكي سطحة | لكل العربيات الملاكي والأوتوماتيك\nونش نقل تقيل وميكروباص | ونشات للحمولات الكبيرة\nونش موتوسيكلات | تثبيت مخصوص من غير خدش\nخدمة طريق | بطارية، كاوتش، بنزين في مكانك\nإنقاذ حوادث | رفع العربيات المقلوبة والواقعة",
        'steps' => "اتصل أو ابعت واتساب | الرقم بيرد 24 ساعة\nابعت اللوكيشن | من الموبايل في ثانية\nالونش يتحرك | بالسعر اللي اتفقنا عليه",
        'body' => '', 'body_format' => 'markdown', 'images' => '',
        'faqs' => [['q' => 'الونش بيوصل في قد إيه؟', 'a' => 'في المتوسط خلال 30 دقيقة جوه السويس، وبنأكدلك الوقت في التليفون حسب مكانك.'],
                   ['q' => 'السعر بيتحدد إزاي؟', 'a' => 'حسب المسافة ونوع العربية، وبنقولك السعر النهائي قبل ما نتحرك ومفيش أي زيادة بعدها.'],
                   ['q' => 'بتنقلوا العربيات الأوتوماتيك؟', 'a' => 'أيوه على سطحة هيدروليك كاملة عشان الفتيس ميتأذيش.']],
        'final_cta' => 'واقف دلوقتي؟ متستناش', 'meta_title' => '', 'meta_description' => '', 'primary_override' => '',
    ];
}

function lp_clean(array $in): array {
    $out = [];
    foreach (LP_FIELDS as $f => $k) {
        $v = $in[$f] ?? '';
        $out[$f] = match ($k) {
            's' => mb_substr(trim((string) $v), 0, 300),
            't' => mb_substr(str_replace("\r\n", "\n", trim((string) $v)), 0, 6000),
            'h' => mb_substr(str_replace("\r\n", "\n", (string) $v), 0, 200000),
            'u' => preg_match('~^(https?://|/)~', trim((string) $v)) ? mb_substr(trim((string) $v), 0, 500) : '',
            'b' => array_key_exists((string) $v, LP_BUTTON_STYLES) ? (string) $v : '',
            'f' => clean_format($v),
            'c' => preg_match('/^#[0-9a-fA-F]{6}$/', (string) $v) ? (string) $v : '',
            'faq' => is_array($v) ? $v : parse_faqs((string) $v),
        };
    }
    return $out;
}

function lp_preview_token(string $slug): string { return substr(hash_hmac('sha256', 'lp|' . $slug, (string) cfg('revalidate_secret')), 0, 20); }
function lp_url(string $slug): string { return rtrim((string) cfg('site_url'), '/') . '/' . $slug; }

/** كارت معاينة صغير للقالب بألوانه */
function lp_swatch(array $t): string {
    $c = $t['colors'];
    return '<span class="lp-swatch lp-hero-' . h($t['hero']) . '" style="--p:' . h($c['primary']) . ';--op:' . h($c['onPrimary']) . ';--a:' . h($c['accent']) . ';--bg:' . h($c['bg']) . ';--hb:' . h($c['heroBg']) . ';--ht:' . h($c['heroText']) . '">'
        . '<i class="sw-hero"><b></b><b></b><em></em><em class="sw-wa"></em></i><i class="sw-body"><b></b><b></b><b></b></i><i class="sw-btn sw-' . h($t['buttons']) . '"></i></span>';
}

/* ================= أقسام صفحة الهبوط (بناء حر) ================= */
const LP_BG = ['' => 'عادي', 'alt' => 'لون هادي', 'primary' => 'لون القالب', 'dark' => 'غامق'];
const LP_BLOCKS = [
    'hero' => ['label' => 'الواجهة', 'single' => true, 'fields' => ['badge' => 's', 'headline' => 's', 'subheadline' => 't', 'eta' => 's', 'image' => 'u', 'show_phone' => 'b']],
    'trust' => ['label' => 'نقط الثقة', 'fields' => ['items' => 't', 'bg' => 'bg']],
    'services' => ['label' => 'الخدمات', 'fields' => ['title' => 's', 'items' => 't', 'bg' => 'bg']],
    'cta' => ['label' => 'أزرار اتصال', 'fields' => ['title' => 's', 'text' => 't', 'buttons' => 'btns', 'size' => 'size', 'bg' => 'bg']],
    'steps' => ['label' => 'خطوات الطلب', 'fields' => ['title' => 's', 'items' => 't', 'bg' => 'bg']],
    'testimonials' => ['label' => 'آراء العملاء', 'fields' => ['title' => 's', 'items' => 't', 'bg' => 'bg']],
    'image_text' => ['label' => 'صورة وكلام', 'fields' => ['title' => 's', 'text' => 't', 'image' => 'u', 'side' => 'side', 'button' => 'b', 'bg' => 'bg']],
    'gallery' => ['label' => 'معرض صور', 'fields' => ['title' => 's', 'images' => 't', 'bg' => 'bg']],
    'video' => ['label' => 'فيديو يوتيوب', 'fields' => ['title' => 's', 'url' => 'u', 'bg' => 'bg']],
    'counters' => ['label' => 'أرقام', 'fields' => ['items' => 't', 'bg' => 'bg']],
    'text' => ['label' => 'محتوى حر (نص، HTML)', 'fields' => ['title' => 's', 'body' => 'h', 'format' => 'f', 'bg' => 'bg']],
    'faq' => ['label' => 'أسئلة شائعة', 'fields' => ['title' => 's', 'items' => 't', 'bg' => 'bg']],
    'final' => ['label' => 'الشريط الأخير', 'single' => true, 'fields' => ['text' => 's']],
];
const LP_GLOBALS = ['logo' => 'u', 'area_name' => 's', 'keyword_default' => 's', 'call_label' => 's', 'wa_label' => 's', 'wa_message' => 's',
    'phone' => 's', 'whatsapp' => 's', 'button_style' => 'b', 'primary_override' => 'c', 'meta_title' => 's', 'meta_description' => 't', 'og_image' => 'u'];

function lp_block_defaults(string $type): array {
    return match ($type) {
        'hero' => ['badge' => 'متاحين دلوقتي 24 ساعة', 'headline' => '{keyword} في {area} خلال دقايق', 'subheadline' => 'عربيتك عطلت أو عملت حادثة؟ اتصل دلوقتي، هنقولك السعر النهائي ووقت الوصول قبل ما الونش يتحرك.', 'eta' => 'بنوصلك في المتوسط خلال 30 دقيقة', 'image' => '', 'show_phone' => true],
        'trust' => ['items' => lp_defaults()['trust'], 'bg' => 'alt'],
        'services' => ['title' => 'خدماتنا في {area}', 'items' => lp_defaults()['services'], 'bg' => ''],
        'cta' => ['title' => 'محتاج ونش دلوقتي؟', 'text' => 'كلمنا أو ابعت لوكيشن، والونش يتحرك على طول.', 'buttons' => 'both', 'size' => 'big', 'bg' => 'alt'],
        'steps' => ['title' => 'تطلب الونش إزاي؟', 'items' => lp_defaults()['steps'], 'bg' => 'alt'],
        'testimonials' => ['title' => 'عملاء اتعاملوا معانا', 'items' => '', 'bg' => ''],
        'image_text' => ['title' => '', 'text' => '', 'image' => '', 'side' => 'start', 'button' => true, 'bg' => ''],
        'gallery' => ['title' => 'من شغلنا', 'images' => '', 'bg' => ''],
        'video' => ['title' => '', 'url' => '', 'bg' => ''],
        'counters' => ['items' => '', 'bg' => 'primary'],
        'text' => ['title' => '', 'body' => '', 'format' => 'markdown', 'bg' => ''],
        'faq' => ['title' => 'أسئلة سريعة', 'items' => faqs_to_text(lp_defaults()['faqs']), 'bg' => 'alt'],
        'final' => ['text' => 'واقف دلوقتي؟ متستناش'],
        default => [],
    };
}

/** الأقسام الافتراضية لصفحة جديدة */
function lp_default_blocks(): array {
    $b = [];
    foreach (['hero', 'trust', 'services', 'cta', 'steps', 'testimonials', 'faq', 'final'] as $t) {
        $b[] = ['id' => $t, 'type' => $t, 'enabled' => $t !== 'testimonials'] + lp_block_defaults($t);
    }
    return $b;
}

/** بيحوّل صفحات الهبوط القديمة (حقول ثابتة) لأقسام */
function lp_blocks_from_flat(array $d): array {
    if (!empty($d['blocks']) && is_array($d['blocks'])) return $d['blocks'];
    $faqText = is_array($d['faqs'] ?? null) ? faqs_to_text($d['faqs']) : (string) ($d['faqs'] ?? '');
    $b = [['id' => 'hero', 'type' => 'hero', 'enabled' => true, 'badge' => $d['badge'] ?? '', 'headline' => $d['headline'] ?? '', 'subheadline' => $d['subheadline'] ?? '', 'eta' => $d['eta'] ?? '', 'image' => $d['hero_image'] ?? '', 'show_phone' => true]];
    if (!empty($d['trust'])) $b[] = ['id' => 'trust', 'type' => 'trust', 'enabled' => true, 'items' => $d['trust'], 'bg' => 'alt'];
    if (!empty($d['services'])) $b[] = ['id' => 'services', 'type' => 'services', 'enabled' => true, 'title' => 'خدماتنا في {area}', 'items' => $d['services'], 'bg' => ''];
    if (($d['button_style'] ?? '') === 'hero') $b[] = ['id' => 'cta1', 'type' => 'cta', 'enabled' => true, 'title' => '', 'text' => '', 'buttons' => 'both', 'size' => 'big', 'bg' => 'alt'];
    if (!empty($d['steps'])) $b[] = ['id' => 'steps', 'type' => 'steps', 'enabled' => true, 'title' => 'تطلب الونش إزاي؟', 'items' => $d['steps'], 'bg' => 'alt'];
    if (!empty($d['body'])) $b[] = ['id' => 'text1', 'type' => 'text', 'enabled' => true, 'title' => '', 'body' => $d['body'], 'format' => $d['body_format'] ?? 'markdown', 'bg' => ''];
    if (!empty($d['images'])) $b[] = ['id' => 'gallery1', 'type' => 'gallery', 'enabled' => true, 'title' => '', 'images' => $d['images'], 'bg' => ''];
    if (trim($faqText) !== '') $b[] = ['id' => 'faq', 'type' => 'faq', 'enabled' => true, 'title' => 'أسئلة سريعة', 'items' => $faqText, 'bg' => 'alt'];
    $b[] = ['id' => 'final', 'type' => 'final', 'enabled' => true, 'text' => $d['final_cta'] ?? 'واقف دلوقتي؟ متستناش'];
    return $b;
}

function lp_clean_value(string $kind, $v) {
    return match ($kind) {
        's' => mb_substr(trim((string) $v), 0, 300),
        't' => mb_substr(str_replace("\r\n", "\n", trim((string) $v)), 0, 8000),
        'h' => mb_substr(str_replace("\r\n", "\n", (string) $v), 0, 200000),
        'u' => preg_match('~^(https?://|/)~', trim((string) $v)) && !preg_match('~[\s"<>]~', trim((string) $v)) ? mb_substr(trim((string) $v), 0, 500) : '',
        'b' => !empty($v) && $v !== '0',
        'bg' => array_key_exists((string) $v, LP_BG) ? (string) $v : '',
        'btns' => in_array($v, ['both', 'call', 'wa'], true) ? $v : 'both',
        'size' => in_array($v, ['big', 'normal', 'banner'], true) ? $v : 'big',
        'side' => $v === 'end' ? 'end' : 'start',
        'f' => clean_format($v),
        'c' => preg_match('/^#[0-9a-fA-F]{6}$/', (string) $v) ? (string) $v : '',
        default => '',
    };
}

/** بيبني بيانات الصفحة من الفورم: الإعدادات العامة + الأقسام بترتيبها */
function lp_from_post(array $post): array {
    $g = (array) ($post['g'] ?? []);
    $out = [];
    foreach (LP_GLOBALS as $f => $k) $out[$f] = $f === 'button_style' ? (array_key_exists((string) ($g[$f] ?? ''), LP_BUTTON_STYLES) ? (string) $g[$f] : '') : lp_clean_value($k, $g[$f] ?? '');
    $blocks = []; $seen = [];
    foreach ((array) ($post['order'] ?? []) as $bid) {
        $in = $post['b'][$bid] ?? null;
        $type = (string) ($in['type'] ?? '');
        if (!$in || !isset(LP_BLOCKS[$type])) continue;
        if (!empty(LP_BLOCKS[$type]['single']) && isset($seen[$type])) continue;
        $seen[$type] = true;
        $blk = ['id' => preg_replace('/[^a-z0-9_]/i', '', (string) $bid) ?: 'b' . bin2hex(random_bytes(3)), 'type' => $type, 'enabled' => !empty($in['enabled'])];
        foreach (LP_BLOCKS[$type]['fields'] as $f => $k) $blk[$f] = lp_clean_value($k, $in[$f] ?? '');
        $blocks[] = $blk;
    }
    $out['blocks'] = $blocks;
    return $out;
}
