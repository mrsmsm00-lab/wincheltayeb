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
    if (!$hero || trim($hero['headline']) === '') $errors[] = 'العنوان الرئيسي في قسم «الواجهة» مطلوب.';
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
        img_input("b[$bid][image]", (string) ($b['image'] ?? ''), 'صورة الواجهة', 'بتظهر في القوالب اللي فيها صورة (نصين، صورة خلفية)');
        b_check($bid, 'show_phone', 'اعرض الرقم كبير في الواجهة (في القوالب اللي بتدعم ده)', $b['show_phone'] ?? true);
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
      <fieldset class="tpl-pick"><legend>القالب (الألوان وشكل الواجهة)</legend>
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
<input type="file" id="lp-file" accept="image/jpeg,image/png,image/webp,image/gif" hidden>
<input type="file" id="lp-files" accept="image/jpeg,image/png,image/webp" multiple hidden>
<?php layout_end(editor_scripts());
