<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/layout.php';
require __DIR__ . '/includes/editor.php';
require __DIR__ . '/includes/landing.php';
require_login();

$id = (int) ($_GET['id'] ?? 0);
$s = db()->prepare('SELECT * FROM landing_pages WHERE id = ?'); $s->execute([$id]);
$lp = $s->fetch();
if (!$lp) { flash('صفحة الهبوط دي مش موجودة.', 'err'); redirect('landing.php'); }
$raw = json_decode($lp['data'], true) ?: [];
$g = array_merge(lp_defaults(), $raw);
$blocks = lp_blocks_from_flat($raw ?: ['blocks' => lp_default_blocks()]);
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $slug = clean_lp_path((string) ($_POST['slug'] ?? ''));
    $name = post_str('name', 120) ?: 'صفحة هبوط';
    $tpl = isset(lp_templates()[$_POST['template'] ?? '']) ? $_POST['template'] : $lp['template'];
    $new = lp_from_post($_POST);
    if ($slug === '') $errors[] = 'اكتب رابط للصفحة.';
    elseif ($c = url_conflict('landing', $slug, '', $id)) $errors[] = $c . ' اقتراح: ' . url_suggest('landing', $slug, '', $id);
    $hero = array_values(array_filter($new['blocks'], fn($b) => $b['type'] === 'hero'))[0] ?? null;
    if (($new['mode'] ?? 'blocks') === 'blocks' && (!$hero || trim($hero['headline']) === '')) $errors[] = 'العنوان الرئيسي في قسم «الواجهة» مطلوب.';
    if (($new['mode'] ?? '') === 'custom' && trim((string) $new['custom_html']) === '') $errors[] = 'اكتب كود الـ HTML للصفحة، أو ارجع لطريقة «قالب بالأقسام».';
    if (!$errors) {
        db()->prepare("UPDATE landing_pages SET slug = ?, name = ?, template = ?, published = ?, noindex = ?, data = ?, updated_at = datetime('now') WHERE id = ?")
            ->execute([$slug, $name, $tpl, isset($_POST['published']) ? 1 : 0, isset($_POST['noindex']) ? 1 : 0, json_encode($new, JSON_UNESCAPED_UNICODE), $id]);
        $r = revalidate_site();
        flash('اتحفظت صفحة الهبوط.' . ($r['ok'] ? ' والموقع اتحدث.' : ' ' . $r['msg']), $r['ok'] ? 'ok' : 'warn');
        redirect("landing_edit.php?id=$id");
    }
    $lp = array_merge($lp, ['slug' => $slug, 'name' => $name, 'template' => $tpl, 'published' => isset($_POST['published']) ? 1 : 0, 'noindex' => isset($_POST['noindex']) ? 1 : 0]);
    $g = array_merge($g, $new); $blocks = $new['blocks'];
}

/* ---------- أدوات الحقول ---------- */
function img_input(string $name, string $value, string $label, string $hint = ''): void {
    echo '<div class="img-field"><label>' . h($label) . ($hint ? ' <span class="hint">' . h($hint) . '</span>' : '') .
        '<span class="img-row"><input name="' . h($name) . '" value="' . h($value) . '" dir="ltr" list="gallery-images" placeholder="https://..." data-img-input>' .
        '<button type="button" class="btn btn-ghost" data-upload>ارفع صورة</button></span></label>' .
        '<img class="img-prev" src="' . h($value) . '" alt=""' . ($value ? '' : ' hidden') . '></div>';
}
function b_text(string $bid, string $f, string $label, $v, string $hint = ''): void {
    echo '<label>' . h($label) . ($hint ? ' <span class="hint">' . h($hint) . '</span>' : '') . '<input name="b[' . h($bid) . '][' . $f . ']" value="' . h((string) $v) . '"' . ($f === 'title' || $f === 'headline' ? ' data-title-input' : '') . '></label>';
}
function b_area(string $bid, string $f, string $label, $v, string $hint = '', int $rows = 4, bool $ltr = false): void {
    echo '<label>' . h($label) . ($hint ? ' <span class="hint">' . h($hint) . '</span>' : '') . '<textarea name="b[' . h($bid) . '][' . $f . ']" rows="' . $rows . '"' . ($ltr ? ' dir="ltr"' : '') . '>' . h((string) $v) . '</textarea></label>';
}
function b_select(string $bid, string $f, string $label, array $opts, $v): void {
    echo '<label>' . h($label) . ' <select name="b[' . h($bid) . '][' . $f . ']">';
    foreach ($opts as $k => $l) echo '<option value="' . h($k) . '"' . ((string) $v === (string) $k ? ' selected' : '') . '>' . h($l) . '</option>';
    echo '</select></label>';
}
function b_check(string $bid, string $f, string $label, $v): void {
    echo '<label class="check"><input type="hidden" name="b[' . h($bid) . '][' . $f . ']" value="0"><input type="checkbox" name="b[' . h($bid) . '][' . $f . ']" value="1"' . (!empty($v) ? ' checked' : '') . '> ' . h($label) . '</label>';
}

function block_card(array $b, bool $template = false): void {
    $bid = $template ? '__ID__' : (string) $b['id'];
    $type = $b['type'];
    $def = LP_BLOCKS[$type];
    $label = trim((string) ($b['title'] ?? $b['headline'] ?? $b['text'] ?? '')) ?: $def['label'];
    $single = !empty($def['single']);
    ?>
  <li class="sec-card<?= empty($b['enabled']) ? ' is-off' : '' ?>" data-item>
    <input type="hidden" name="order[]" value="<?= h($bid) ?>">
    <input type="hidden" name="b[<?= h($bid) ?>][type]" value="<?= h($type) ?>">
    <div class="sec-head">
      <span class="drag" title="اسحب عشان تغيّر الترتيب" aria-hidden="true">⠿</span>
      <label class="switch"><input type="checkbox" name="b[<?= h($bid) ?>][enabled]" value="1" data-enabled<?= !empty($b['enabled']) ? ' checked' : '' ?>><span>ظاهر</span></label>
      <button type="button" class="sec-toggle" aria-expanded="<?= $template ? 'true' : 'false' ?>"><strong data-title-label><?= h(mb_substr($label, 0, 70)) ?></strong> <span class="tag"><?= h($def['label']) ?></span></button>
      <span class="sec-actions">
        <button type="button" data-move="up" aria-label="لفوق">▲</button>
        <button type="button" data-move="down" aria-label="لتحت">▼</button>
        <?php if (!$single): ?><button type="button" data-remove class="link-danger">مسح</button><?php endif; ?>
      </span>
    </div>
    <div class="sec-body"<?= $template ? '' : ' hidden' ?>>
    <?php
    $bg = fn() => b_select($bid, 'bg', 'الخلفية', LP_BG, $b['bg'] ?? '');
    switch ($type):
      case 'hero':
        b_text($bid, 'badge', 'الشارة الصغيرة', $b['badge'] ?? '');
        b_text($bid, 'headline', 'العنوان الرئيسي (H1)', $b['headline'] ?? '', 'استخدم {keyword} و{area}');
        b_area($bid, 'subheadline', 'الجملة اللي تحته', $b['subheadline'] ?? '', '', 2);
        b_text($bid, 'eta', 'سطر وقت الوصول', $b['eta'] ?? '');
        img_input("b[$bid][image]", (string) ($b['image'] ?? ''), 'صورة الواجهة', 'صورتك بتحل محل رسمة الونش أو الخلفية الافتراضية');
        echo '<div class="grid2">';
        b_select($bid, 'image_position', 'مكان الصورة', LP_DESIGN_OPTIONS['img_pos'], $b['image_position'] ?? 'auto');
        echo '<label>تغميق الصورة لما تبقى خلفية <span class="hint">0 = من غير تغميق، 90 = غامق جداً</span><input type="range" min="0" max="90" step="5" name="b[' . h($bid) . '][overlay]" value="' . (int) ($b['overlay'] ?? 55) . '" oninput="this.nextElementSibling.textContent=this.value+\'%\'"><output>' . (int) ($b['overlay'] ?? 55) . '%</output></label>';
        echo '</div>';
        b_check($bid, 'show_phone', 'اعرض الرقم كبير في الواجهة (في الأشكال اللي بتدعم ده)', $b['show_phone'] ?? true);
        b_check($bid, 'show_art', 'اعرض رسمة الونش لما مفيش صورة', $b['show_art'] ?? true);
        break;
      case 'trust': b_area($bid, 'items', 'النقط', $b['items'] ?? '', 'كل نقطة في سطر'); $bg(); break;
      case 'services': b_text($bid, 'title', 'العنوان', $b['title'] ?? ''); b_area($bid, 'items', 'الخدمات', $b['items'] ?? '', 'كل خدمة في سطر: الاسم | الوصف | رابط صورة (اختياري)', 6); $bg(); break;
      case 'steps': b_text($bid, 'title', 'العنوان', $b['title'] ?? ''); b_area($bid, 'items', 'الخطوات', $b['items'] ?? '', 'كل خطوة: العنوان | الشرح', 4); $bg(); break;
      case 'cta':
        b_text($bid, 'title', 'العنوان (اختياري)', $b['title'] ?? '');
        b_area($bid, 'text', 'جملة (اختياري)', $b['text'] ?? '', '', 2);
        echo '<div class="grid2">';
        b_select($bid, 'buttons', 'الأزرار', ['both' => 'اتصال + واتساب', 'call' => 'اتصال بس', 'wa' => 'واتساب بس'], $b['buttons'] ?? 'both');
        b_select($bid, 'size', 'الشكل', ['big' => 'أزرار كبيرة', 'normal' => 'أزرار عادية', 'banner' => 'شريط عريض بالرقم'], $b['size'] ?? 'big');
        echo '</div>'; $bg(); break;
      case 'testimonials':
        b_text($bid, 'title', 'العنوان', $b['title'] ?? '');
        b_area($bid, 'items', 'الآراء', $b['items'] ?? '', 'كل رأي في سطر: الاسم | المنطقة | الكلام | التقييم من 5. مثال: أحمد م. | الأربعين | وصلوا في 20 دقيقة وكانوا محترمين | 5', 6);
        echo '<p class="hint warn-text">اكتب آراء حقيقية من عملاء فعلاً. الآراء المزيفة ممنوعة في سياسات Google Ads، وممكن توقف الحساب.</p>';
        $bg(); break;
      case 'image_text':
        b_text($bid, 'title', 'العنوان', $b['title'] ?? '');
        b_area($bid, 'text', 'الكلام', $b['text'] ?? '', 'تقدر تستخدم **عريض** و- للقوايم', 5);
        img_input("b[$bid][image]", (string) ($b['image'] ?? ''), 'الصورة');
        echo '<div class="grid2">';
        b_select($bid, 'side', 'مكان الصورة', ['start' => 'يمين', 'end' => 'شمال'], $b['side'] ?? 'start');
        echo '<div>'; b_check($bid, 'button', 'حط أزرار اتصال تحت الكلام', $b['button'] ?? true); echo '</div></div>';
        $bg(); break;
      case 'gallery':
        b_text($bid, 'title', 'العنوان', $b['title'] ?? '');
        b_area($bid, 'images', 'الصور', $b['images'] ?? '', 'رابط صورة في كل سطر', 4, true);
        echo '<p><button type="button" class="btn btn-ghost" data-upload-multi>ارفع صور (كذا صورة مرة واحدة)</button> <button type="button" class="btn btn-ghost" data-pick-open>اختار من المعرض</button></p>';
        $bg(); break;
      case 'video': b_text($bid, 'title', 'العنوان', $b['title'] ?? ''); echo '<label>رابط يوتيوب <input name="b[' . h($bid) . '][url]" value="' . h((string) ($b['url'] ?? '')) . '" dir="ltr" placeholder="https://youtu.be/..."></label>'; $bg(); break;
      case 'counters': b_area($bid, 'items', 'الأرقام', $b['items'] ?? '', 'كل رقم في سطر: الرقم | الوصف. مثال: 24 | ساعة شغالين. اكتب أرقام حقيقية بس', 4); $bg(); break;
      case 'text':
        b_text($bid, 'title', 'العنوان (اختياري)', $b['title'] ?? '');
        editor_field("b[$bid][body]", (string) ($b['body'] ?? ''), (string) ($b['format'] ?? 'markdown'), "b[$bid][format]", "lpb-$bid", '', 10, 'المحتوى');
        $bg(); break;
      case 'faq': b_text($bid, 'title', 'العنوان', $b['title'] ?? ''); b_area($bid, 'items', 'الأسئلة', $b['items'] ?? '', 'س: للسؤال وج: للإجابة', 8); $bg(); break;
      case 'final': b_text($bid, 'text', 'الجملة', $b['text'] ?? ''); break;
    endswitch; ?>
    </div>
  </li>
<?php
}

$gallery = db()->query("SELECT url, title FROM gallery WHERE kind = 'image' ORDER BY id DESC LIMIT 80")->fetchAll();
$previewUrl = lp_url($lp['slug']) . ($lp['published'] ? '' : '?preview=' . lp_preview_token($lp['slug']));
$gf = fn($f, $label, $hint = '', $ltr = false) => print('<label>' . h($label) . ($hint ? ' <span class="hint">' . h($hint) . '</span>' : '') . '<input name="g[' . $f . ']" value="' . h((string) ($g[$f] ?? '')) . '"' . ($ltr ? ' dir="ltr"' : '') . '></label>');

layout_start('تعديل صفحة هبوط', 'landing');
editor_data();
?>
<div class="head-row">
  <h1><?= h($lp['name']) ?></h1>
  <span>
    <a class="btn btn-ghost" href="<?= h($previewUrl) ?>" target="_blank" rel="noopener"><?= $lp['published'] ? 'افتح الصفحة' : 'معاينة (مسودة)' ?></a>
    <a class="btn btn-ghost" href="stats.php?tab=overview&scope=all&range=30d&landing=<?= h(rawurlencode('/' . $lp['slug'])) ?>">إحصائياتها</a>
  </span>
</div>
<?php foreach ($errors as $e): ?><div class="flash flash-err"><?= h($e) ?></div><?php endforeach; ?>
<datalist id="gallery-images"><?php foreach ($gallery as $gi): ?><option value="<?= h($gi['url']) ?>"><?= h($gi['title']) ?></option><?php endforeach; ?></datalist>

<form method="post" class="editor" id="editor" data-dirty-guard>
  <?= csrf_field() ?>
  <div class="editor-main">
    <section class="box">
      <h2>الأساسيات</h2>
      <label>اسم داخلي <input name="name" value="<?= h($lp['name']) ?>"></label>
      <label>رابط الصفحة <span class="hint">مثلاً ads أو ads/sokhna. ده اللي هتحطه في الإعلان</span>
        <div class="slug-row"><span dir="ltr" class="muted"><?= h(rtrim(cfg('site_url'), '/')) ?>/</span><input name="slug" value="<?= h($lp['slug']) ?>" dir="ltr" required data-slug-check="landing" data-id="<?= (int) $id ?>"></div>
        <span class="slug-status" aria-live="polite"></span></label>
      <fieldset class="tpl-pick"><legend>القالب (نقطة البداية للألوان والشكل)</legend>
        <?php foreach (lp_templates() as $t): ?>
          <label class="tpl-opt"><input type="radio" name="template" value="<?= h($t['id']) ?>"<?= $lp['template'] === $t['id'] ? ' checked' : '' ?>><?= lp_swatch($t) ?><span><?= h($t['name']) ?></span></label>
        <?php endforeach; ?>
      </fieldset>
      <div class="grid2">
        <label>الأزرار الثابتة <span class="hint">بتفضل ظاهرة طول ما الزائر بيقلّب</span><select name="g[button_style]"><?php foreach (LP_BUTTON_STYLES as $k => $l): ?><option value="<?= h($k) ?>"<?= ($g['button_style'] ?? '') === $k ? ' selected' : '' ?>><?= h($l) ?></option><?php endforeach; ?></select></label>
        <?php $gf('primary_override', 'لون أساسي مخصص (اختياري)', 'زي #0B4EA2', true); ?>
      </div>
      <?php img_input('g[logo]', (string) ($g['logo'] ?? ''), 'شعار مختلف للصفحة دي (اختياري)', 'سيبه فاضي عشان يستخدم شعار الموقع من الإعدادات'); ?>
    </section>

    <section class="box" id="design">
      <h2>التصميم</h2>
      <fieldset class="mode-pick"><legend>طريقة التصميم</legend>
        <?php foreach (LP_DESIGN_OPTIONS['mode'] as $k => $l): ?>
          <label class="mode-opt"><input type="radio" name="g[mode]" value="<?= h($k) ?>"<?= ($g['mode'] ?? 'blocks') === $k ? ' checked' : '' ?> data-mode-radio><span><strong><?= h($l) ?></strong><small><?= $k === 'blocks' ? 'اختار قالب وأقسام، وتحكم في الألوان والشعار والصور' : 'اكتب أو الزق تصميمك HTML وCSS بالكامل' ?></small></span></label>
        <?php endforeach; ?>
      </fieldset>

      <div data-show-mode="blocks">
        <div class="grid2">
          <?php $sel = function ($f, $label, $optKey, $hint = '') use ($g) {
              echo '<label>' . h($label) . ($hint ? ' <span class="hint">' . h($hint) . '</span>' : '') . '<select name="g[' . $f . ']">';
              foreach (LP_DESIGN_OPTIONS[$optKey] as $k => $l) echo '<option value="' . h($k) . '"' . ((string) ($g[$f] ?? array_key_first(LP_DESIGN_OPTIONS[$optKey])) === (string) $k ? ' selected' : '') . '>' . h($l) . '</option>';
              echo '</select></label>';
          };
          $sel('hero_layout', 'شكل الواجهة', 'layout', 'بيغلب على شكل القالب');
          $sel('header_style', 'الهيدر', 'header');
          $sel('logo_position', 'مكان الشعار', 'logo_pos');
          $sel('logo_size', 'حجم الشعار', 'logo_size');
          $sel('radius', 'شكل الأزرار والكروت', 'radius'); ?>
          <div><label class="check"><input type="hidden" name="g[show_name]" value="0"><input type="checkbox" name="g[show_name]" value="1"<?= ($g['show_name'] ?? true) ? ' checked' : '' ?>> اعرض اسم الموقع جنب الشعار</label></div>
        </div>
        <h3>الألوان <span class="hint">سيب أي لون فاضي عشان ياخد لون القالب</span></h3>
        <div class="color-grid">
          <?php foreach (LP_COLOR_LABELS as $f => $l): $v = (string) ($g[$f] ?? ''); ?>
            <label class="color-field"><span><?= h($l) ?></span>
              <span class="color-row"><input type="color" value="<?= h($v ?: '#ffffff') ?>" data-color-for="c-<?= h($f) ?>"<?= $v ? '' : ' data-empty' ?>><input name="g[<?= h($f) ?>]" id="c-<?= h($f) ?>" value="<?= h($v) ?>" dir="ltr" placeholder="زي القالب" maxlength="7"><button type="button" class="link-btn" data-color-clear="c-<?= h($f) ?>" title="رجّع لون القالب">↺</button></span></label>
          <?php endforeach; ?>
        </div>
        <details<?= trim((string) ($g['custom_css'] ?? '')) !== '' ? ' open' : '' ?>><summary><strong>CSS إضافي (للمحترفين)</strong></summary>
          <p class="hint">بيتطبق فوق التصميم. الصفحة كلها جوه <code>.lp</code>، والألوان متغيرات زي <code>var(--lp-primary)</code>. أمثلة: <code>.lp h1{font-size:4rem}</code> أو <code>.lp-btn{border-radius:0}</code></p>
          <textarea name="g[custom_css]" rows="8" dir="ltr" class="code" spellcheck="false"><?= h((string) ($g['custom_css'] ?? '')) ?></textarea>
        </details>
      </div>

      <div data-show-mode="custom">
        <p class="hint">اكتب صفحتك بالكامل HTML و&lt;style&gt;. الأكواد دي بتتبدل تلقائي:
          <code>{phone}</code> الرقم • <code>{tel}</code> رابط الاتصال • <code>{whatsapp_link}</code> رابط الواتساب • <code>{call_button}</code> زرار اتصال جاهز • <code>{wa_button}</code> زرار واتساب جاهز • <code>{logo}</code> رابط الشعار • <code>{keyword}</code> • <code>{area}</code>.
          أي رابط <code>tel:</code> أو <code>wa.me</code> بيتسجل في الإحصائيات وتحويلات Google Ads لوحده. السكريبتات (&lt;script&gt;) بتتشال للأمان.</p>
        <p><button type="button" class="btn btn-ghost" data-custom-starter>ابدأ من نموذج جاهز</button> <button type="button" class="btn btn-ghost" data-custom-refresh>حدّث المعاينة</button></p>
        <div class="custom-split">
          <textarea name="g[custom_html]" rows="26" dir="ltr" class="code" spellcheck="false" id="custom-html"><?= h((string) ($g['custom_html'] ?? '')) ?></textarea>
          <iframe id="custom-preview" title="معاينة" sandbox="allow-same-origin"></iframe>
        </div>
        <label class="check"><input type="hidden" name="g[custom_header]" value="0"><input type="checkbox" name="g[custom_header]" value="1"<?= !empty($g['custom_header']) ? ' checked' : '' ?>> حط الهيدر الجاهز (الشعار والرقم) فوق تصميمي</label>
        <label class="check"><input type="hidden" name="g[custom_sticky]" value="0"><input type="checkbox" name="g[custom_sticky]" value="1"<?= ($g['custom_sticky'] ?? true) ? ' checked' : '' ?>> حط شريط الاتصال والواتساب الثابت على الموبايل (مقترح)</label>
      </div>
    </section>

    <section class="box">
      <h2>الاتصال والكلمات</h2>
      <div class="grid2">
        <?php $gf('area_name', 'المنطقة {area}'); ?>
        <?php $gf('keyword_default', '{keyword} لو الإعلان مبعتش كلمة'); ?>
        <?php $gf('call_label', 'كلام زرار الاتصال'); ?>
        <?php $gf('wa_label', 'كلام زرار الواتساب'); ?>
        <?php $gf('phone', 'رقم مختلف للصفحة (اختياري)', '', true); ?>
        <?php $gf('whatsapp', 'واتساب مختلف (اختياري)', 'بصيغة 2010xxxxxxxx', true); ?>
      </div>
      <?php $gf('wa_message', 'رسالة الواتساب الجاهزة'); ?>
    </section>

    <div data-show-mode="blocks">
    <h2>أقسام الصفحة</h2>
    <p class="muted">رتّب الأقسام بالسحب أو الأسهم، وضيف أي قسم بأي عدد. ضيف «أزرار اتصال» في أي مكان عايز فيه الزائر يلاقي الزرار قدامه.</p>
    <ul class="sec-list" data-sortable id="blocks">
      <?php foreach ($blocks as $b) if (isset(LP_BLOCKS[$b['type']])) block_card($b); ?>
    </ul>
    <?php foreach (LP_BLOCKS as $type => $def): if (!empty($def['single'])) continue; ?>
      <template id="tpl-b-<?= h($type) ?>"><?php block_card(['type' => $type, 'enabled' => true] + lp_block_defaults($type), true); ?></template>
    <?php endforeach; ?>
    <div class="add-blocks">
      <strong>أضف قسم:</strong>
      <?php foreach (LP_BLOCKS as $type => $def): if (!empty($def['single'])) continue; ?>
        <button type="button" class="btn btn-ghost" data-add="tpl-b-<?= h($type) ?>" data-add-to="blocks">+ <?= h($def['label']) ?></button>
      <?php endforeach; ?>
    </div>
    </div>
  </div>

  <aside class="editor-side">
    <div class="box">
      <label class="check"><input type="checkbox" name="published"<?= $lp['published'] ? ' checked' : '' ?>> منشورة</label>
      <label class="check"><input type="checkbox" name="noindex"<?= $lp['noindex'] ? ' checked' : '' ?>> اخفيها عن نتايج جوجل (noindex)</label>
      <p class="hint">لو سبتها ظاهرة، جوجل ممكن يأرشفها ويجيبلك منها زوار ببلاش كمان. والإعلانات شغالة عليها في الحالتين.</p>
      <button class="btn btn-block">احفظ</button>
    </div>
    <div class="box">
      <h2>السيو والمشاركة</h2>
      <label>عنوان التبويب وجوجل <input name="g[meta_title]" id="meta_title" value="<?= h($g['meta_title'] ?? '') ?>" placeholder="سيبه فاضي عشان ياخد العنوان الرئيسي"><span class="counter" data-for="meta_title" data-max="60"></span></label>
      <label>الوصف <textarea name="g[meta_description]" id="meta_description" rows="3"><?= h($g['meta_description'] ?? '') ?></textarea><span class="counter" data-for="meta_description" data-max="160"></span></label>
      <?php img_input('g[og_image]', (string) ($g['og_image'] ?? ''), 'صورة المشاركة (واتساب وفيسبوك)', '1200×630 أحسن مقاس'); ?>
    </div>
    <?php if ($gallery): ?>
    <div class="box">
      <h2>صور المعرض</h2>
      <p class="hint">دوس على أي صورة عشان تنسخ رابطها، والزقه في أي خانة صورة.</p>
      <ul class="pick-gallery small"><?php foreach ($gallery as $gi): ?><li><button type="button" data-copy-url="<?= h($gi['url']) ?>" title="<?= h($gi['title']) ?>"><img src="<?= h($gi['url']) ?>" alt="" loading="lazy"></button></li><?php endforeach; ?></ul>
    </div>
    <?php endif; ?>
  </aside>
</form>
<script type="application/json" id="lp-preview-data"><?= json_encode(['phone' => $g['phone'] ?: setting('phone'), 'phone_intl' => setting('phone_intl'), 'whatsapp' => $g['whatsapp'] ?: setting('whatsapp'), 'logo' => ($g['logo'] ?? '') ?: (setting('site_logo') ?: rtrim(cfg('site_url'), '/') . '/logo.svg'), 'site' => setting('site_name')], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?></script>
<input type="file" id="lp-file" accept="image/jpeg,image/png,image/webp,image/gif" hidden>
<input type="file" id="lp-files" accept="image/jpeg,image/png,image/webp" multiple hidden>
<?php layout_end(array_merge(editor_scripts(), ['assets/landing-design.js?v=' . filemtime(__DIR__ . '/assets/landing-design.js')]));
