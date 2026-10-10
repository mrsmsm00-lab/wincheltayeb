<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/layout.php';
require_login();

$type = $_GET['type'] ?? 'service';
if (!isset(POST_TYPES[$type])) $type = 'service';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $id = (int) ($_POST['id'] ?? 0);
    $action = $_POST['action'] ?? '';
    if ($action === 'delete' && $id) {
        db()->prepare('DELETE FROM posts WHERE id = ? AND type = ?')->execute([$id, $type]);
        $r = revalidate_site();
        flash('اتمسح.' . ($r['ok'] ? '' : ' ' . $r['msg']), $r['ok'] ? 'ok' : 'warn');
    } elseif ($action === 'toggle' && $id) {
        db()->prepare("UPDATE posts SET published = 1 - published, updated_at = date('now') WHERE id = ? AND type = ?")->execute([$id, $type]);
        revalidate_site();
        flash('اتغيرت حالة النشر.');
    } elseif ($action === 'sort') {
        $s = db()->prepare('UPDATE posts SET sort = ? WHERE id = ? AND type = ?');
        foreach ((array) ($_POST['sort'] ?? []) as $pid => $val) $s->execute([(int) $val, (int) $pid, $type]);
        revalidate_site();
        flash('اتحفظ الترتيب.');
    }
    redirect("posts.php?type=$type");
}

$q = trim((string) ($_GET['q'] ?? ''));
$sql = 'SELECT id, slug, title, published, sort, updated_at, district, length(body) AS len FROM posts WHERE type = ?';
$args = [$type];
if ($q !== '') { $sql .= ' AND (title LIKE ? OR slug LIKE ?)'; $args[] = "%$q%"; $args[] = "%$q%"; }
$sql .= $type === 'blog' ? ' ORDER BY published_at DESC' : ' ORDER BY sort, id';
$st = db()->prepare($sql); $st->execute($args); $rows = $st->fetchAll();
$districts = districts();

layout_start(POST_TYPES[$type]['label'], $type);
?>
<div class="head-row">
  <h1><?= h(POST_TYPES[$type]['label']) ?></h1>
  <a class="btn" href="post_edit.php?type=<?= h($type) ?>">+ <?= h(POST_TYPES[$type]['one']) ?> جديدة</a>
</div>
<form class="search" method="get"><input type="hidden" name="type" value="<?= h($type) ?>"><input name="q" value="<?= h($q) ?>" placeholder="دوّر بالعنوان أو الرابط"><button class="btn btn-ghost">بحث</button></form>
<form method="post" id="sortform"><?= csrf_field() ?><input type="hidden" name="action" value="sort"></form>
<div class="table-wrap">
<table class="table">
  <thead><tr><th>الترتيب</th><th>العنوان</th><?php if ($type === 'area'): ?><th>الحي</th><?php endif; ?><th>الطول</th><th>الحالة</th><th>آخر تعديل</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($rows as $r): ?>
    <tr>
      <td><input class="sort-input" type="number" name="sort[<?= (int) $r['id'] ?>]" value="<?= (int) $r['sort'] ?>" form="sortform"></td>
      <td><a href="post_edit.php?id=<?= (int) $r['id'] ?>"><strong><?= h($r['title']) ?></strong></a><br><small class="muted" dir="ltr"><?= h($r['slug']) ?></small></td>
      <?php if ($type === 'area'): ?><td><?= h($districts[$r['district']]['name'] ?? '—') ?></td><?php endif; ?>
      <td class="<?= $r['len'] < 1500 ? 'warn-text' : '' ?>"><?= number_format((int) $r['len']) ?> حرف</td>
      <td>
        <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
          <button class="pill <?= $r['published'] ? 'pill-on' : 'pill-off' ?>"><?= $r['published'] ? 'منشور' : 'مسودة' ?></button></form>
      </td>
      <td class="muted"><?= h($r['updated_at']) ?></td>
      <td class="row-actions">
        <a href="post_edit.php?id=<?= (int) $r['id'] ?>">تعديل</a>
        <a href="<?= h(public_url($type, $r['slug'])) ?>" target="_blank" rel="noopener">عرض</a>
        <form method="post" class="inline" data-confirm="متأكد إنك عايز تمسح «<?= h($r['title']) ?>»؟"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>"><button class="link-danger">مسح</button></form>
      </td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$rows): ?><tr><td colspan="7" class="muted">مفيش نتايج. أضف <?= h(POST_TYPES[$type]['one']) ?> جديدة.</td></tr><?php endif; ?>
  </tbody>
</table>
</div>
<?php if ($rows && $type !== 'blog'): ?><p><button class="btn btn-ghost" form="sortform">احفظ الترتيب</button></p><?php endif; ?>
<p class="muted">الطول باللون البرتقالي معناه إن المحتوى أقل من 1500 حرف. المحتوى الأطول بيساعد في الأرشفة.</p>
<?php layout_end();
