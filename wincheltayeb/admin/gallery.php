<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/layout.php';
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
<form method="post" enctype="multipart/form-data" class="box form-grid">
  <?= csrf_field() ?><input type="hidden" name="action" value="add">
  <label>العنوان (اكتب وصف حقيقي، زي: «سحب ميكروباص على طريق الأدبية») <input name="title" required value="<?= h($_POST['title'] ?? '') ?>"></label>
  <label>وصف إضافي <input name="caption" value="<?= h($_POST['caption'] ?? '') ?>"></label>
  <label>المنطقة <select name="area_slug"><option value="">من غير منطقة</option><?php foreach ($areas as $a): ?><option value="<?= h($a['slug']) ?>"><?= h($a['title']) ?></option><?php endforeach; ?></select></label>
  <label>ملف صورة أو فيديو (jpg, png, webp, mp4, webm حتى <?= (int) cfg('max_upload_mb') ?> ميجا) <input type="file" name="file" accept="image/jpeg,image/png,image/webp,video/mp4,video/webm"></label>
  <label>صورة غلاف للفيديو (اختياري) <input type="file" name="thumb" accept="image/jpeg,image/png,image/webp"></label>
  <label>أو رابط يوتيوب <input name="youtube" dir="ltr" placeholder="https://youtu.be/..."></label>
  <button class="btn">أضف للمعرض</button>
</form>

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
    <p><?= h($g['title']) ?></p>
    <form method="post" data-confirm="تمسح «<?= h($g['title']) ?>»؟"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $g['id'] ?>"><button class="link-danger">مسح</button></form>
  </li>
<?php endforeach; ?>
<?php if (!$items): ?><li class="muted">لسه مفيش حاجة. ارفع أول صورة من شغلكم.</li><?php endif; ?>
</ul>
<?php layout_end();
