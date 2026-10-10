<?php
// التحكم الكامل في الصفحة الرئيسية: ترتيب الأقسام، إظهار وإخفاء، تعديل كل النصوص، وأقسام مخصصة
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/layout.php';
require __DIR__ . '/includes/editor.php';
require __DIR__ . '/includes/site_config.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? 'save';
    if ($action === 'reset') {
        set_setting('home_sections', '');
        $r = revalidate_site();
        flash('الصفحة الرئيسية رجعت للشكل الافتراضي.' . ($r['ok'] ? '' : ' ' . $r['msg']), $r['ok'] ? 'ok' : 'warn');
        redirect('home.php');
    }
    $posted = (array) ($_POST['s'] ?? []);
    $sections = []; $seen = [];
    foreach ((array) ($_POST['order'] ?? []) as $sid) {
        $in = $posted[$sid] ?? null;
        $type = (string) ($in['type'] ?? '');
        if (!$in || !isset(SECTION_FIELDS[$type])) continue;
        if ($type !== 'custom' && isset($seen[$type])) continue;
        $seen[$type] = true;
        $sections[] = clean_section($in, $type);
    }
    foreach (site_defaults()['home_sections'] as $d) {
        if (!isset($seen[$d['type']])) { $d['enabled'] = false; $sections[] = $d; }
    }
    set_setting('home_sections', json_encode($sections, JSON_UNESCAPED_UNICODE));
    if (isset($_POST['general_faqs'])) set_setting('general_faqs', json_encode(parse_faqs(post_str('general_faqs', 30000)), JSON_UNESCAPED_UNICODE));
    set_setting('home_meta_title', post_str('home_meta_title', 120));
    set_setting('home_meta_description', post_str('home_meta_description', 320));
    $r = revalidate_site();
    flash('اتحفظت الصفحة الرئيسية.' . ($r['ok'] ? ' والموقع اتحدث.' : ' ' . $r['msg']), $r['ok'] ? 'ok' : 'warn');
    redirect('home.php');
}

$sections = home_sections();
$areaSlugs = db()->query("SELECT slug, COALESCE(NULLIF(place_name,''), title) AS name FROM posts WHERE type='area' AND published=1 ORDER BY sort")->fetchAll();

function f_text(string $sid, string $f, string $label, $v, string $hint = '', bool $ltr = false): void {
    echo '<label>' . h($label) . ($hint ? ' <span class="hint">' . h($hint) . '</span>' : '') .
        '<input name="s[' . h($sid) . '][' . $f . ']" value="' . h((string) $v) . '"' . ($ltr ? ' dir="ltr"' : '') . ($f === 'title' ? ' data-title-input' : '') . '></label>';
}
function f_area(string $sid, string $f, string $label, $v, string $hint = '', int $rows = 3): void {
    echo '<label>' . h($label) . ($hint ? ' <span class="hint">' . h($hint) . '</span>' : '') .
        '<textarea name="s[' . h($sid) . '][' . $f . ']" rows="' . $rows . '">' . h((string) $v) . '</textarea></label>';
}
function f_num(string $sid, string $f, string $label, $v, string $hint = ''): void {
    echo '<label>' . h($label) . ($hint ? ' <span class="hint">' . h($hint) . '</span>' : '') .
        '<input type="number" min="0" max="2000" name="s[' . h($sid) . '][' . $f . ']" value="' . (int) $v . '"></label>';
}
function f_bg(string $sid, $v): void {
    echo '<label>لون الخلفية <select name="s[' . h($sid) . '][bg]">';
    foreach (SECTION_BGS as $k => $l) echo '<option value="' . $k . '"' . ($v === $k ? ' selected' : '') . '>' . h($l) . '</option>';
    echo '</select></label>';
}
function f_more(string $sid, array $s): void {
    echo '<div class="grid2">';
    f_text($sid, 'more_label', 'نص رابط «المزيد»', $s['more_label'] ?? '', 'سيبه فاضي عشان يختفي');
    f_text($sid, 'more_href', 'الرابط', $s['more_href'] ?? '', '', true);
    echo '</div>';
}

function section_card(array $s, array $areaSlugs, bool $template = false): void {
    $sid = $template ? '__ID__' : (string) $s['id'];
    $type = $s['type'];
    $label = $s['title'] ?? ($s['text'] ?? '') ?: SECTION_TYPES[$type];
    $isCustom = $type === 'custom';
    ?>
  <li class="sec-card<?= empty($s['enabled']) ? ' is-off' : '' ?>" data-item>
    <input type="hidden" name="order[]" value="<?= h($sid) ?>">
    <input type="hidden" name="s[<?= h($sid) ?>][type]" value="<?= h($type) ?>">
    <input type="hidden" name="s[<?= h($sid) ?>][id]" value="<?= h($sid) ?>">
    <div class="sec-head">
      <span class="drag" title="اسحب عشان تغيّر الترتيب" aria-hidden="true">⠿</span>
      <label class="switch" title="إظهار / إخفاء"><input type="checkbox" name="s[<?= h($sid) ?>][enabled]" value="1" data-enabled<?= !empty($s['enabled']) ? ' checked' : '' ?>><span>ظاهر</span></label>
      <button type="button" class="sec-toggle" aria-expanded="false"><strong data-title-label><?= h($label) ?></strong> <span class="tag"><?= h(SECTION_TYPES[$type]) ?></span></button>
      <span class="sec-actions">
        <button type="button" data-move="up" title="لفوق" aria-label="حرّك لفوق">▲</button>
        <button type="button" data-move="down" title="لتحت" aria-label="حرّك لتحت">▼</button>
        <?php if ($isCustom): ?><button type="button" data-remove class="link-danger" title="امسح القسم">مسح</button><?php endif; ?>
      </span>
    </div>
    <div class="sec-body" hidden>
    <?php switch ($type):
      case 'hero':
        f_text($sid, 'kicker', 'السطر الصغير فوق العنوان', $s['kicker'] ?? '');
        f_text($sid, 'title', 'العنوان الرئيسي (H1)', $s['title'] ?? '', 'ده أهم عنوان في الصفحة لجوجل. خلي فيه «ونش إنقاذ السويس»');
        f_area($sid, 'lead', 'الفقرة جنب اللافتة', $s['lead'] ?? '');
        f_area($sid, 'facts', 'النقط تحت الزراير', $s['facts'] ?? '', 'كل نقطة في سطر');
        f_text($sid, 'sign_rows', 'المناطق اللي على اللافتة', $s['sign_rows'] ?? '', 'روابط المناطق مفصولة بفاصلة (أحسن 3 أو 4)', true);
        echo '<details class="hint"><summary>روابط المناطق المتاحة</summary><p dir="ltr">' . h(implode(', ', array_map(fn($a) => $a['slug'], $areaSlugs))) . '</p></details>';
        echo '<label class="check"><input type="checkbox" name="s[' . h($sid) . '][show_buttons]" value="1"' . (!empty($s['show_buttons']) ? ' checked' : '') . '> اعرض زراير الاتصال والواتساب</label>';
        break;
      case 'services': case 'articles': case 'gallery':
        f_text($sid, 'title', 'عنوان القسم (H2)', $s['title'] ?? '');
        if ($type !== 'services') f_num($sid, 'count', 'العدد اللي يظهر', $s['count'] ?? 3);
        f_more($sid, $s); f_bg($sid, $s['bg'] ?? 'white');
        break;
      case 'map':
        f_text($sid, 'title', 'عنوان القسم (H2)', $s['title'] ?? '');
        f_area($sid, 'lead', 'سطر الشرح', $s['lead'] ?? '', '', 2);
        f_num($sid, 'height', 'ارتفاع الخريطة بالبكسل', $s['height'] ?? 480);
        f_more($sid, $s); f_bg($sid, $s['bg'] ?? 'mist');
        break;
      case 'areas':
        f_text($sid, 'title', 'عنوان القسم (H2)', $s['title'] ?? '');
        f_num($sid, 'limit', 'عدد المناطق', $s['limit'] ?? 0, '0 = كل المناطق');
        f_bg($sid, $s['bg'] ?? 'white');
        break;
      case 'steps':
        f_text($sid, 'title', 'عنوان القسم (H2)', $s['title'] ?? '');
        f_area($sid, 'items', 'الخطوات', $s['items'] ?? '', 'كل خطوة في سطر: العنوان | الشرح. تقدر تكتب {phone} ويتحط مكانه الرقم', 5);
        f_bg($sid, $s['bg'] ?? 'ink');
        break;
      case 'faq':
        f_text($sid, 'title', 'عنوان القسم (H2)', $s['title'] ?? '');
        echo '<label>الأسئلة <span class="hint">س: للسؤال، وج: للإجابة. نفس الأسئلة اللي في الإعدادات</span><textarea name="general_faqs" rows="10">' . h(faqs_to_text(setting('general_faqs', '[]'))) . '</textarea></label>';
        f_bg($sid, $s['bg'] ?? 'mist');
        break;
      case 'cta':
        f_text($sid, 'text', 'الجملة', $s['text'] ?? '');
        f_bg($sid, $s['bg'] ?? 'blue');
        break;
      case 'custom':
        f_text($sid, 'title', 'عنوان القسم (H2)', $s['title'] ?? '', 'اختياري');
        echo '<div class="grid2">';
        f_bg($sid, $s['bg'] ?? 'white');
        echo '<label>العرض <select name="s[' . h($sid) . '][width]">';
        foreach (['normal' => 'عادي', 'narrow' => 'ضيق (للنصوص)', 'full' => 'عرض الشاشة'] as $k => $l) echo '<option value="' . $k . '"' . (($s['width'] ?? 'normal') === $k ? ' selected' : '') . '>' . $l . '</option>';
        echo '</select></label></div>';
        f_text($sid, 'anchor', 'اسم رابط داخلي للقسم (اختياري)', $s['anchor'] ?? '', 'حروف إنجليزي، مثلاً offers، ويبقى الرابط /#offers', true);
        editor_field("s[$sid][body]", (string) ($s['body'] ?? ''), (string) ($s['format'] ?? 'markdown'), "s[$sid][format]", "body-$sid", "home-$sid", 14, 'محتوى القسم');
        break;
    endswitch; ?>
    </div>
  </li>
<?php
}

layout_start('الصفحة الرئيسية', 'home');
editor_data();
?>
<div class="head-row">
  <h1>الصفحة الرئيسية</h1>
  <a class="btn btn-ghost" href="<?= h(cfg('site_url')) ?>/" target="_blank" rel="noopener">شوف الصفحة على الموقع</a>
</div>
<p class="muted">اسحب الأقسام من علامة ⠿ أو استخدم الأسهم عشان ترتّبها، واقفل «ظاهر» عشان تخفي أي قسم، ودوس على اسم القسم عشان تعدّل كلامه. ومتنساش تدوس «احفظ» في الآخر.</p>

<form method="post" id="home-form" data-dirty-guard>
  <?= csrf_field() ?><input type="hidden" name="action" value="save">
  <section class="box">
    <h2>سيو الصفحة الرئيسية</h2>
    <label>عنوان جوجل <input name="home_meta_title" id="meta_title" maxlength="120" value="<?= h(setting('home_meta_title')) ?>" placeholder="ونش إنقاذ السويس 24 ساعة | ونش إنقاذ الطيب 01000529729"><span class="counter" data-for="meta_title" data-max="60"></span></label>
    <label>وصف جوجل <textarea name="home_meta_description" id="meta_description" rows="3" maxlength="320" placeholder="سيبه فاضي عشان يستخدم الوصف الافتراضي"><?= h(setting('home_meta_description')) ?></textarea><span class="counter" data-for="meta_description" data-max="160"></span></label>
  </section>

  <ul class="sec-list" data-sortable id="sections">
    <?php foreach ($sections as $s) section_card($s, $areaSlugs); ?>
  </ul>
  <template id="tpl-custom"><?php section_card(['type' => 'custom', 'enabled' => true, 'title' => '', 'body' => '', 'format' => 'markdown', 'bg' => 'white'], $areaSlugs, true); ?></template>

  <div class="sticky-save">
    <button type="button" class="btn btn-ghost" data-add="tpl-custom" data-add-to="sections">+ أضف قسم مخصص (نص، صور، فيديو، HTML)</button>
    <button class="btn">احفظ الصفحة الرئيسية</button>
  </div>
</form>

<form method="post" class="reset-form" data-confirm="هترجع الصفحة الرئيسية للشكل الافتراضي وهتتمسح الأقسام المخصصة. متأكد؟">
  <?= csrf_field() ?><input type="hidden" name="action" value="reset">
  <button class="link-danger">رجّع الصفحة الرئيسية للشكل الافتراضي</button>
</form>
<?php layout_end(editor_scripts());
