<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/layout.php';
require __DIR__ . '/includes/ai.php';
require_login();

$errors = [];
function gallery_delete(int $id): int {
    $s = db()->prepare('SELECT url, thumb FROM gallery WHERE id = ?'); $s->execute([$id]);
    $row = $s->fetch();
    if (!$row) return 0;
    $prefix = rtrim(cfg('admin_url'), '/') . '/uploads/';
    $root = realpath(__DIR__ . '/uploads');
    foreach ([$row['url'], $row['thumb']] as $u) {
        if ($u && str_starts_with($u, $prefix)) {
            $file = realpath(__DIR__ . '/uploads/' . substr($u, strlen($prefix)));
            if ($file && $root && str_starts_with($file, $root)) @unlink($file);
        }
    }
    db()->prepare('DELETE FROM gallery WHERE id = ?')->execute([$id]);
    return 1;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';
    if ($action === 'delete' || $action === 'bulk_delete') {
        $ids = $action === 'delete' ? [(int) ($_POST['id'] ?? 0)] : array_map('intval', (array) ($_POST['ids'] ?? []));
        $n = 0;
        foreach (array_unique(array_filter($ids)) as $id) $n += gallery_delete($id);
        if ($n) { revalidate_site(); flash("اتمسح: $n."); } else flash('مفيش حاجة متعلّم عليها.', 'warn');
        redirect('gallery.php');
    }
    if ($action === 'add') {
        $title = post_str('title', 200);
        $caption = post_str('caption', 1000);
        $area = post_str('area_slug', 120);
        $yt = post_str('youtube', 300);
        if ($title === '') $errors[] = 'اكتب عنوان يوصف الصورة أو الفيديو (مهم للسيو).';
        try {
            $kind = null; $url = ''; $thumb = ''; $ytId = '';
            if ($yt !== '') {
                $ytId = youtube_id($yt) ?? '';
                if (!$ytId) throw new RuntimeException('رابط يوتيوب مش صحيح.');
                $kind = 'youtube'; $url = "https://www.youtube.com/watch?v=$ytId"; $thumb = "https://i.ytimg.com/vi/$ytId/hqdefault.jpg";
            } else {
                $url = handle_upload('file', ['image', 'video']) ?? '';
                if (!$url) throw new RuntimeException('اختار ملف أو حط رابط يوتيوب.');
                $kind = preg_match('/\.(mp4|webm)$/', $url) ? 'video' : 'image';
                if ($kind === 'video') $thumb = handle_upload('thumb', ['image']) ?? '';
            }
            if (!$errors) {
                db()->prepare('INSERT INTO gallery (kind, url, thumb, youtube_id, title, caption, area_slug) VALUES (?, ?, ?, ?, ?, ?, ?)')
                    ->execute([$kind, $url, $thumb, $ytId, $title, $caption, $area]);
                $r = revalidate_site();
                flash('اتضاف للمعرض.' . ($r['ok'] ? '' : ' ' . $r['msg']), $r['ok'] ? 'ok' : 'warn');
                redirect('gallery.php');
            }
        } catch (RuntimeException $e) { $errors[] = $e->getMessage(); }
    }
}

$items = db()->query('SELECT * FROM gallery ORDER BY sort, id DESC')->fetchAll();
$areas = db()->query("SELECT slug, title FROM posts WHERE type='area' ORDER BY sort")->fetchAll();
layout_start('صور وفيديوهات', 'gallery');
?>
<h1>صور وفيديوهات</h1>
<?php foreach ($errors as $e): ?><div class="flash flash-err"><?= h($e) ?></div><?php endforeach; ?>

<section class="box bulk-upload" id="bulk-upload" data-csrf="<?= h(csrf_token()) ?>" data-max-mb="<?= (int) cfg('max_upload_mb') ?>">
  <h2>رفع جماعي</h2>
  <div class="dropzone" tabindex="0" role="button" aria-label="اختار صور أو فيديوهات">
    <strong>اسحب الصور والفيديوهات هنا</strong>
    <span>أو دوس عشان تختار من جهازك (تقدر تختار كذا ملف مرة واحدة)</span>
    <input type="file" multiple accept="image/jpeg,image/png,image/webp,video/mp4,video/webm" hidden>
  </div>
  <div class="grid2">
    <label>المنطقة لكل الملفات دي <span class="hint">اختياري. الـ AI هيذكرها في العنوان</span>
      <select data-area><option value="">من غير منطقة</option><?php foreach ($areas as $a): ?><option value="<?= h($a['slug']) ?>"><?= h($a['title']) ?></option><?php endforeach; ?></select></label>
    <div>
      <label class="check"><input type="checkbox" data-ai<?= ai_key() !== '' ? ' checked' : ' disabled' ?>> اكتب العنوان والوصف بالذكاء الاصطناعي <?= ai_key() === '' ? '<span class="hint">(حط مفتاح OpenAI من <a href="settings.php#ai">الإعدادات</a>)</span>' : '<span class="hint">كل صورة = طلب واحد. الاستخدام النهارده: <span data-usage>' . ai_usage_today() . '</span> من ' . ai_daily_limit() . '</span>' ?></label>
      <label class="check"><input type="checkbox" data-resize checked> صغّر الصور قبل الرفع <span class="hint">(أقصى عرض 1920 بكسل: أسرع للموقع، وبيشيل بيانات الموقع الجغرافي من الصورة)</span></label>
    </div>
  </div>
  <div class="bulk-summary" aria-live="polite" hidden></div>
  <ul class="upload-list"></ul>
</section>

<details class="box">
<summary><strong>رفع ملف واحد بإيدك، أو إضافة فيديو يوتيوب</strong></summary>
<form method="post" enctype="multipart/form-data" class="form-grid">
  <?= csrf_field() ?><input type="hidden" name="action" value="add">
  <label>العنوان (اكتب وصف حقيقي، زي: «سحب ميكروباص على طريق الأدبية») <input name="title" required value="<?= h($_POST['title'] ?? '') ?>"></label>
  <label>وصف إضافي <input name="caption" value="<?= h($_POST['caption'] ?? '') ?>"></label>
  <label>المنطقة <select name="area_slug"><option value="">من غير منطقة</option><?php foreach ($areas as $a): ?><option value="<?= h($a['slug']) ?>"><?= h($a['title']) ?></option><?php endforeach; ?></select></label>
  <label>ملف صورة أو فيديو (jpg, png, webp, mp4, webm حتى <?= (int) cfg('max_upload_mb') ?> ميجا) <input type="file" name="file" accept="image/jpeg,image/png,image/webp,video/mp4,video/webm"></label>
  <label>صورة غلاف للفيديو (اختياري) <input type="file" name="thumb" accept="image/jpeg,image/png,image/webp"></label>
  <label>أو رابط يوتيوب <input name="youtube" dir="ltr" placeholder="https://youtu.be/..."></label>
  <button class="btn">أضف للمعرض</button>
</form>
</details>

<?php if ($items): ?>
<form method="post" id="gbulk" class="bulk-bar" data-bulk data-op="delete">
  <?= csrf_field() ?><input type="hidden" name="action" value="bulk_delete">
  <label class="check"><input type="checkbox" class="bulk-all" data-form="gbulk"> علّم على الكل</label>
  <span class="bulk-count" aria-live="polite">محدد: <strong>0</strong></span>
  <button class="btn btn-danger" disabled>امسح المحدد</button>
</form>
<?php endif; ?>
<ul class="admin-gallery">
<?php foreach ($items as $g): ?>
  <li>
    <label class="g-check"><input type="checkbox" class="bulk-item" name="ids[]" value="<?= (int) $g['id'] ?>" form="gbulk" aria-label="<?= h($g['title']) ?>"></label>
    <?php if ($g['kind'] === 'image'): ?><img src="<?= h($g['url']) ?>" alt="" loading="lazy">
    <?php elseif ($g['thumb']): ?><img src="<?= h($g['thumb']) ?>" alt="" loading="lazy">
    <?php else: ?><video src="<?= h($g['url']) ?>" muted preload="metadata"></video><?php endif; ?>
    <span class="tag"><?= ['image' => 'صورة', 'video' => 'فيديو', 'youtube' => 'يوتيوب'][$g['kind']] ?></span>
    <p data-g-title><?= h($g['title']) ?></p>
    <?php if ($g['caption']): ?><p class="muted g-caption" data-g-caption><?= h($g['caption']) ?></p><?php endif; ?>
    <details class="g-edit">
      <summary>تعديل</summary>
      <form class="g-edit-form" data-id="<?= (int) $g['id'] ?>">
        <label>العنوان <input name="title" value="<?= h($g['title']) ?>" required></label>
        <label>الوصف <textarea name="caption" rows="2"><?= h($g['caption']) ?></textarea></label>
        <label>المنطقة <select name="area_slug"><option value="">من غير منطقة</option><?php foreach ($areas as $a): ?><option value="<?= h($a['slug']) ?>"<?= $g['area_slug'] === $a['slug'] ? ' selected' : '' ?>><?= h($a['title']) ?></option><?php endforeach; ?></select></label>
        <button class="btn">احفظ</button> <span class="g-status" aria-live="polite"></span>
      </form>
    </details>
    <form method="post" data-confirm="تمسح «<?= h($g['title']) ?>»؟"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $g['id'] ?>"><button class="link-danger">مسح</button></form>
  </li>
<?php endforeach; ?>
<?php if (!$items): ?><li class="muted">لسه مفيش حاجة. ارفع أول صورة من شغلكم.</li><?php endif; ?>
</ul>
<?php layout_end(['assets/gallery.js?v=' . filemtime(__DIR__ . '/assets/gallery.js')]);
