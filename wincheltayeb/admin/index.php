<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/layout.php';
$user = require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'revalidate') {
    csrf_check();
    $r = revalidate_site();
    flash($r['msg'], $r['ok'] ? 'ok' : 'err');
    redirect('index.php');
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'ping') {
    csrf_check();
    $r = refresh_sitemap_and_ping();
    flash('خريطة الموقع فيها ' . $r['urls'] . ' رابط. ' . $r['indexnow'], $r['ok'] ? 'ok' : 'warn');
    redirect('index.php');
}

$counts = [];
foreach (db()->query('SELECT type, COUNT(*) c, SUM(published) p FROM posts GROUP BY type') as $r) $counts[$r['type']] = $r;
$gallery = (int) db()->query('SELECT COUNT(*) FROM gallery')->fetchColumn();
$recent = db()->query('SELECT id, type, title, updated_at FROM posts ORDER BY updated_at DESC, id DESC LIMIT 8')->fetchAll();
$last = json_decode(setting('_last_revalidate', '{}'), true) ?: [];
$ping = json_decode(setting('_last_ping', '{}'), true) ?: [];
$drafts = (int) db()->query('SELECT COUNT(*) FROM posts WHERE published = 0')->fetchColumn();
$noLatLng = (int) db()->query("SELECT COUNT(*) FROM posts WHERE type='area' AND (lat IS NULL OR lng IS NULL)")->fetchColumn();
$shortMeta = (int) db()->query("SELECT COUNT(*) FROM posts WHERE length(meta_description) < 70")->fetchColumn();

layout_start('الرئيسية', 'index');
?>
<h1>أهلاً <?= h($user['username']) ?></h1>
<div class="stats">
  <?php foreach (POST_TYPES as $k => $t): ?>
    <a class="stat" href="posts.php?type=<?= $k ?>"><strong><?= (int) ($counts[$k]['c'] ?? 0) ?></strong><span><?= h($t['label']) ?></span><small><?= (int) ($counts[$k]['p'] ?? 0) ?> منشور</small></a>
  <?php endforeach; ?>
  <a class="stat" href="gallery.php"><strong><?= $gallery ?></strong><span>صور وفيديوهات</span></a>
</div>

<div class="cols">
  <section class="box">
    <h2>تحديث الموقع</h2>
    <p>الموقع بيتحدث تلقائي بعد كل حفظ. لو حسيت إن تعديل مظهرش، دوس هنا.</p>
    <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="revalidate"><button class="btn">حدّث الموقع دلوقتي</button></form>
    <?php if ($last): ?><p class="muted">آخر تحديث: <?= h($last['at'] ?? '') ?>، <?= h($last['msg'] ?? '') ?></p><?php endif; ?>
    <h3>خريطة الموقع ومحركات البحث</h3>
    <p>بعد ما تضيف أو تنشر صفحات جديدة، دوس هنا: هنحدّث خريطة الموقع ونبلّغ Bing وYandex بالروابط فوراً عن طريق IndexNow.</p>
    <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="ping"><button class="btn">حدّث خريطة الموقع وبلّغ محركات البحث</button></form>
    <?php if ($ping): ?><p class="muted">آخر مرة: <?= h($ping['at'] ?? '') ?>، <?= (int) ($ping['urls'] ?? 0) ?> رابط، <?= h($ping['indexnow'] ?? '') ?></p><?php endif; ?>
    <p class="muted">جوجل وقّف خاصية التبليغ الفوري للخرايط من 2023، وبيرجع لخريطة الموقع لوحده لو مبعوتة في <a href="https://search.google.com/search-console" target="_blank" rel="noopener">Search Console</a>. وللصفحات المهمة استخدم «Request indexing» هناك.</p>
    <?php if ($drafts): ?><p class="warn-text">عندك <?= $drafts ?> صفحة مسودة مش منشورة.</p><?php endif; ?>
    <h3>فحص سريع للسيو</h3>
    <ul class="checks">
      <li class="<?= $noLatLng ? 'bad' : 'good' ?>"><?= $noLatLng ? "$noLatLng منطقة من غير مكان على الخريطة" : 'كل المناطق ليها مكان على الخريطة' ?></li>
      <li class="<?= $shortMeta ? 'bad' : 'good' ?>"><?= $shortMeta ? "$shortMeta صفحة وصف السيو بتاعها قصير (أقل من 70 حرف)" : 'أوصاف السيو كلها بطول كويس' ?></li>
    </ul>
    <p class="muted">روابط مهمة: <a href="<?= h(cfg('site_url')) ?>/sitemap.xml" target="_blank">sitemap.xml</a> ، <a href="<?= h(cfg('site_url')) ?>/llms.txt" target="_blank">llms.txt</a> ، <a href="api.php?resource=all" target="_blank">API</a></p>
  </section>
  <section class="box">
    <h2>آخر التعديلات</h2>
    <table class="table">
      <?php foreach ($recent as $r): ?>
        <tr><td><span class="tag"><?= h(POST_TYPES[$r['type']]['one']) ?></span></td><td><a href="post_edit.php?id=<?= (int) $r['id'] ?>"><?= h($r['title']) ?></a></td><td class="muted"><?= h($r['updated_at']) ?></td></tr>
      <?php endforeach; ?>
    </table>
  </section>
</div>
<?php layout_end();
