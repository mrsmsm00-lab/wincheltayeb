<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/layout.php';
require __DIR__ . '/includes/ai.php';
require __DIR__ . '/includes/editor.php';
require __DIR__ . '/includes/urls.php';
require_login();

$id = (int) ($_GET['id'] ?? 0);
$post = null;
if ($id) {
    $s = db()->prepare('SELECT * FROM posts WHERE id = ?'); $s->execute([$id]); $post = $s->fetch();
    if (!$post) { flash('الصفحة دي مش موجودة.', 'err'); redirect('index.php'); }
    $type = $post['type'];
} else {
    $type = $_GET['type'] ?? 'blog';
    if (!isset(POST_TYPES[$type])) $type = 'blog';
    $post = ['type' => $type, 'slug' => '', 'title' => '', 'excerpt' => '', 'body' => '', 'meta_title' => '', 'meta_description' => '',
        'image' => '', 'format' => 'markdown', 'icon' => 'car', 'district' => '', 'place_name' => '', 'lat' => null, 'lng' => null, 'eta' => '', 'faqs' => '[]',
        'published' => 1, 'sort' => 100, 'published_at' => date('Y-m-d')];
}
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $data = [
        'title' => post_str('title', 200),
        'slug' => slugify(post_str('slug', 120) ?: post_str('title', 120)),
        'excerpt' => post_str('excerpt', 500),
        'body' => str_replace("\r\n", "\n", post_str('body', 400000)),
        'format' => clean_format($_POST['format'] ?? 'markdown'),
        'meta_title' => post_str('meta_title', 120),
        'meta_description' => post_str('meta_description', 320),
        'image' => post_str('image', 500),
        'icon' => array_key_exists($_POST['icon'] ?? '', ICONS) ? $_POST['icon'] : '',
        'district' => post_str('district', 40),
        'place_name' => post_str('place_name', 120),
        'lat' => is_numeric($_POST['lat'] ?? null) ? (float) $_POST['lat'] : null,
        'lng' => is_numeric($_POST['lng'] ?? null) ? (float) $_POST['lng'] : null,
        'eta' => post_str('eta', 80),
        'faqs' => json_encode(parse_faqs(post_str('faqs', 50000)), JSON_UNESCAPED_UNICODE),
        'published' => isset($_POST['published']) ? 1 : 0,
        'sort' => (int) ($_POST['sort'] ?? 0),
        'published_at' => preg_match('/^\d{4}-\d{2}-\d{2}$/', $_POST['published_at'] ?? '') ? $_POST['published_at'] : date('Y-m-d'),
    ];
    try {
        $up = handle_upload('image_file', ['image']);
        if ($up) $data['image'] = $up;
    } catch (RuntimeException $e) { $errors[] = $e->getMessage(); }
    if ($data['title'] === '') $errors[] = 'العنوان مطلوب.';
    if ($type === 'area' && ($data['lat'] === null || $data['lng'] === null)) $errors[] = 'حدد مكان المنطقة على الخريطة.';
    if ($data['lat'] !== null && ($data['lat'] < 28.8 || $data['lat'] > 30.6 || $data['lng'] < 31.6 || $data['lng'] > 33.0)) $errors[] = 'المكان اللي اخترته شكله بره محافظة السويس. راجعه.';
    if ($c = url_conflict('post', $data['slug'], $type, $id)) $errors[] = $c . ' اقتراح: ' . url_suggest('post', $data['slug'], $type, $id);

    if (!$errors) {
        $data['updated_at'] = date('Y-m-d');
        $cols = array_keys($data);
        if ($id) {
            $set = implode(', ', array_map(fn($c) => "$c = :$c", $cols));
            $stmt = db()->prepare("UPDATE posts SET $set WHERE id = :id");
            $stmt->execute(array_combine(array_map(fn($c) => ":$c", $cols), array_values($data)) + [':id' => $id]);
        } else {
            $data['type'] = $type; $cols = array_keys($data);
            $stmt = db()->prepare('INSERT INTO posts (' . implode(',', $cols) . ') VALUES (' . implode(',', array_map(fn($c) => ":$c", $cols)) . ')');
            $stmt->execute(array_combine(array_map(fn($c) => ":$c", $cols), array_values($data)));
            $id = (int) db()->lastInsertId();
        }
        $r = revalidate_site();
        flash('اتحفظ.' . ($r['ok'] ? ' والموقع اتحدث.' : ' بس ' . $r['msg']), $r['ok'] ? 'ok' : 'warn');
        redirect("post_edit.php?id=$id");
    }
    $post = array_merge($post, $data);
}

$districts = districts();
$t = POST_TYPES[$type];
layout_start(($id ? 'تعديل ' : 'إضافة ') . $t['one'], $type);
?>
<div class="head-row">
  <h1><?= $id ? 'تعديل: ' . h($post['title']) : 'إضافة ' . h($t['one']) ?></h1>
  <?php if ($id): ?><a class="btn btn-ghost" href="<?= h(public_url($type, $post['slug'])) ?>" target="_blank" rel="noopener">عرض على الموقع</a><?php endif; ?>
</div>
<?php foreach ($errors as $e): ?><div class="flash flash-err"><?= h($e) ?></div><?php endforeach; ?>

<form method="post" enctype="multipart/form-data" class="editor" id="editor" data-type="<?= h($type) ?>">
  <?= csrf_field() ?>
  <div class="editor-main">
    <label>العنوان (H1) <input name="title" id="title" required value="<?= h($post['title']) ?>"></label>
    <label>الرابط (slug) <span class="hint">حروف إنجليزي صغيرة وشرطة، مثلاً: ain-sokhna</span>
      <div class="slug-row"><span dir="ltr" class="muted"><?= h(rtrim(cfg('site_url'), '/') . '/' . ($t['path'] ? $t['path'] . '/' : '')) ?></span><input name="slug" id="slug" dir="ltr" value="<?= h($post['slug']) ?>" data-slug-check="post" data-type="<?= h($type) ?>" data-id="<?= (int) $id ?>"></div>
      <span class="slug-status" aria-live="polite"></span></label>
    <label>المقدمة (بتظهر تحت العنوان وفي القوايم) <textarea name="excerpt" id="excerpt" rows="2"><?= h($post['excerpt']) ?></textarea></label>

    <?php editor_field('body', (string) $post['body'], (string) ($post['format'] ?? 'markdown'), 'format', 'body', $id ? "post-$id" : "new-$type"); ?>

    <label>الأسئلة الشائعة <span class="hint">كل سؤال في سطر يبدأ بـ «س:» وإجابته في السطر اللي بعده بـ «ج:»</span>
      <textarea name="faqs" id="faqs" rows="8"><?= h(faqs_to_text($post['faqs'])) ?></textarea></label>

    <?php if ($type === 'area'): ?>
    <fieldset class="box">
      <legend>بيانات المنطقة والخريطة</legend>
      <div class="grid2">
        <label>اسم المكان (بيظهر على الخريطة) <input name="place_name" id="place_name" value="<?= h($post['place_name']) ?>"></label>
        <label>الحي <select name="district"><?php foreach ($districts as $k => $d): ?><option value="<?= h($k) ?>"<?= $post['district'] === $k ? ' selected' : '' ?>><?= h($d['name']) ?></option><?php endforeach; ?></select></label>
        <label>وقت الوصول المتوقع <input name="eta" value="<?= h($post['eta']) ?>" placeholder="20 لـ 30 دقيقة"></label>
        <div class="grid2">
          <label>خط العرض <input name="lat" id="lat" dir="ltr" value="<?= h($post['lat']) ?>"></label>
          <label>خط الطول <input name="lng" id="lng" dir="ltr" value="<?= h($post['lng']) ?>"></label>
        </div>
      </div>
      <p class="hint">دوس على الخريطة أو اسحب العلامة عشان تحدد مكان المنطقة.</p>
      <div id="picker" class="picker"></div>
    </fieldset>
    <?php endif; ?>
  </div>

  <aside class="editor-side">
    <div class="box">
      <label class="check"><input type="checkbox" name="published" <?= $post['published'] ? 'checked' : '' ?>> منشور على الموقع</label>
      <label>تاريخ النشر <input type="date" name="published_at" value="<?= h($post['published_at']) ?>"></label>
      <label>الترتيب <input type="number" name="sort" value="<?= (int) $post['sort'] ?>"></label>
      <button class="btn btn-block">احفظ</button>
    </div>
    <div class="box ai-box" id="ai-box">
      <h2>مساعد الكتابة (AI)</h2>
      <?php if (ai_key() === ''): ?>
        <p class="muted">علشان تشغّله، حط مفتاح OpenAI من <a href="settings.php#ai">الإعدادات</a>.</p>
      <?php else: ?>
        <label>الفكرة أو ملاحظاتك <span class="hint">مثلاً: «ونش لعربية أوتوماتيك عطلت عند ميناء الأدبية بالليل». اذكر أي أماكن حقيقية تحب تتكتب.</span>
          <textarea id="ai-idea" rows="3"></textarea></label>
        <div class="ai-actions">
          <button type="button" class="btn" data-ai="write">اكتب المقال كامل</button>
          <button type="button" class="btn btn-ghost" data-ai="improve">حسّن وطوّل المحتوى</button>
          <button type="button" class="btn btn-ghost" data-ai="seo">اعمل عنوان ووصف جوجل</button>
          <button type="button" class="btn btn-ghost" data-ai="faqs">اعمل أسئلة شائعة</button>
        </div>
        <p class="ai-status" id="ai-status" role="status" aria-live="polite"></p>
        <button type="button" class="link-btn" id="ai-undo" hidden>تراجع عن آخر تعديل من الـ AI</button>
        <p class="hint">النتيجة بتتحط في الخانات بس، ومش بتتحفظ غير لما تدوس «احفظ». راجع أي اسم مكان قبل النشر.
          الاستخدام النهارده: <span id="ai-usage"><?= ai_usage_today() ?></span> من <?= ai_daily_limit() ?>.</p>
      <?php endif; ?>
    </div>
    <div class="box">
      <h2>السيو</h2>
      <label>عنوان جوجل <input name="meta_title" id="meta_title" value="<?= h($post['meta_title']) ?>" maxlength="120"><span class="counter" data-for="meta_title" data-max="60"></span></label>
      <label>وصف جوجل <textarea name="meta_description" id="meta_description" rows="4" maxlength="320"><?= h($post['meta_description']) ?></textarea><span class="counter" data-for="meta_description" data-max="160"></span></label>
      <div class="serp" aria-label="معاينة نتيجة جوجل">
        <span class="serp-url" dir="ltr"><?= h(preg_replace('~^https?://~', '', cfg('site_url'))) ?> › <?= h($t['path']) ?></span>
        <span class="serp-title" id="serp-title"></span>
        <span class="serp-desc" id="serp-desc"></span>
      </div>
    </div>
    <div class="box">
      <h2>الصورة</h2>
      <?php if ($post['image']): ?><img src="<?= h($post['image']) ?>" alt="" class="thumb"><?php endif; ?>
      <label>رفع صورة <input type="file" name="image_file" accept="image/jpeg,image/png,image/webp"></label>
      <label>أو رابط صورة <input name="image" dir="ltr" value="<?= h($post['image']) ?>"></label>
    </div>
    <?php if ($type === 'service'): ?>
    <div class="box"><label>الأيقونة <select name="icon"><?php foreach (ICONS as $k => $l): ?><option value="<?= $k ?>"<?= $post['icon'] === $k ? ' selected' : '' ?>><?= h($l) ?></option><?php endforeach; ?></select></label></div>
    <?php endif; ?>
  </aside>
</form>
<?php
$scripts = editor_scripts();
if ($type === 'area') { echo '<link rel="stylesheet" href="assets/vendor/leaflet/leaflet.css">'; $scripts[] = 'assets/vendor/leaflet/leaflet.js'; }
layout_end($scripts);
