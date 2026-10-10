<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/layout.php';
require __DIR__ . '/includes/stats.php';
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
    } elseif ($action === 'bulk') {
        $ids = array_values(array_unique(array_filter(array_map('intval', (array) ($_POST['ids'] ?? [])))));
        $op = $_POST['op'] ?? '';
        if (!$ids) { flash('مفيش حاجة متعلّم عليها.', 'warn'); redirect("posts.php?type=$type"); }
        $in = implode(',', array_fill(0, count($ids), '?'));
        $args = array_merge($ids, [$type]);
        if ($op === 'publish' || $op === 'draft') {
            $st = db()->prepare("UPDATE posts SET published = ?, updated_at = date('now') WHERE id IN ($in) AND type = ?");
            $st->execute(array_merge([$op === 'publish' ? 1 : 0], $args));
            $msg = ($op === 'publish' ? 'اتنشر: ' : 'اتحول لمسودة: ') . $st->rowCount();
        } elseif ($op === 'delete') {
            $st = db()->prepare("DELETE FROM posts WHERE id IN ($in) AND type = ?");
            $st->execute($args);
            $msg = 'اتمسح: ' . $st->rowCount();
        } else { flash('اختار العملية الأول.', 'warn'); redirect("posts.php?type=$type"); }
        $r = revalidate_site();
        flash($msg . '.' . ($r['ok'] ? ' والموقع اتحدث.' : ' ' . $r['msg']), $r['ok'] ? 'ok' : 'warn');
    } elseif ($action === 'sort') {
        $s = db()->prepare('UPDATE posts SET sort = ? WHERE id = ? AND type = ?');
        foreach ((array) ($_POST['sort'] ?? []) as $pid => $val) $s->execute([(int) $val, (int) $pid, $type]);
        revalidate_site();
        flash('اتحفظ الترتيب.');
    }
    redirect("posts.php?type=$type");
}

$q = trim((string) ($_GET['q'] ?? ''));
$status = $_GET['status'] ?? '';
$sql = 'SELECT id, slug, title, published, sort, updated_at, district, length(body) AS len FROM posts WHERE type = ?';
$args = [$type];
if ($q !== '') { $sql .= ' AND (title LIKE ? OR slug LIKE ?)'; $args[] = "%$q%"; $args[] = "%$q%"; }
if ($status === 'draft') $sql .= ' AND published = 0';
if ($status === 'published') $sql .= ' AND published = 1';
$sql .= $type === 'blog' ? ' ORDER BY published_at DESC' : ' ORDER BY sort, id';
$st = db()->prepare($sql); $st->execute($args); $rows = $st->fetchAll();
$districts = districts();

// عداد الزيارات والترتيب بيه
$period = view_period();
$order = $_GET['order'] ?? 'manual';
$PS = page_stats($period);
foreach ($rows as &$r) $r['st'] = $PS[post_path($type, $r['slug'])] ?? [];
unset($r);
$val = fn($r, $k) => (int) ($r['st'][$k] ?? 0);
match ($order) {
    'views' => usort($rows, fn($a, $b) => [$val($b, 'v'), $val($b, 'u')] <=> [$val($a, 'v'), $val($a, 'u')]),
    'views_asc' => usort($rows, fn($a, $b) => $val($a, 'v') <=> $val($b, 'v')),
    'conv' => usort($rows, fn($a, $b) => [$val($b, 'c'), $val($b, 'v')] <=> [$val($a, 'c'), $val($a, 'v')]),
    'entries' => usort($rows, fn($a, $b) => $val($b, 'e') <=> $val($a, 'e')),
    'updated' => usort($rows, fn($a, $b) => strcmp((string) $b['updated_at'], (string) $a['updated_at'])),
    default => null,
};
$maxV = max(1, ...array_map(fn($r) => $val($r, 'v'), $rows ?: [['st' => []]]));
$totalV = array_sum(array_map(fn($r) => $val($r, 'v'), $rows));
$ranked = in_array($order, ['views', 'conv', 'entries'], true);
$ORDERS = ['manual' => $type === 'blog' ? 'الأحدث نشراً' : 'الترتيب اليدوي', 'views' => 'الأكثر زيارة', 'views_asc' => 'الأقل زيارة', 'conv' => 'الأكثر اتصال وواتساب', 'entries' => 'الأكثر دخول من برا', 'updated' => 'آخر تعديل'];

layout_start(POST_TYPES[$type]['label'], $type);
?>
<div class="head-row">
  <h1><?= h(POST_TYPES[$type]['label']) ?></h1>
  <a class="btn" href="post_edit.php?type=<?= h($type) ?>">+ <?= h(POST_TYPES[$type]['one']) ?> جديدة</a>
</div>
<form class="search" method="get"><input type="hidden" name="type" value="<?= h($type) ?>"><input name="q" value="<?= h($q) ?>" placeholder="دوّر بالعنوان أو الرابط">
  <select name="status" aria-label="الحالة"><option value="">الكل</option><option value="published"<?= $status === 'published' ? ' selected' : '' ?>>منشور</option><option value="draft"<?= $status === 'draft' ? ' selected' : '' ?>>مسودة</option></select>
  <select name="order" aria-label="الترتيب"><?php foreach ($ORDERS as $k => $l): ?><option value="<?= $k ?>"<?= $order === $k ? ' selected' : '' ?>><?= h($l) ?></option><?php endforeach; ?></select>
  <select name="period" aria-label="فترة العداد"><?php foreach (VIEW_PERIODS as $k => $l): ?><option value="<?= $k ?>"<?= $period === (string) $k ? ' selected' : '' ?>><?= h($l) ?></option><?php endforeach; ?></select>
  <button class="btn btn-ghost">طبّق</button></form>
<p class="muted views-note">العداد: <strong><?= number_format($totalV) ?></strong> مشاهدة لـ<?= h(POST_TYPES[$type]['label']) ?> في <?= h(VIEW_PERIODS[$period]) ?>. «تواصل» = ضغطات اتصال وواتساب من جوه الصفحة نفسها.</p>
<form method="post" id="bulkform" class="bulk-bar" data-bulk>
  <?= csrf_field() ?><input type="hidden" name="action" value="bulk">
  <span class="bulk-count" aria-live="polite">محدد: <strong>0</strong></span>
  <select name="op" aria-label="العملية الجماعية" required>
    <option value="">اختار العملية…</option>
    <option value="publish">انشر / اعتمد</option>
    <option value="draft">حوّل لمسودة</option>
    <option value="delete">امسح</option>
  </select>
  <button class="btn" disabled>نفّذ على المحدد</button>
</form>
<form method="post" id="sortform"><?= csrf_field() ?><input type="hidden" name="action" value="sort"></form>
<div class="table-wrap">
<table class="table">
  <thead><tr><th class="cb"><input type="checkbox" class="bulk-all" data-form="bulkform" aria-label="علّم على الكل"></th><th><?= $ranked ? '#' : 'الترتيب' ?></th><th>العنوان</th><th><a href="?<?= h(http_build_query(array_merge($_GET, ['order' => $order === 'views' ? 'views_asc' : 'views']))) ?>" class="th-sort">الزيارات <?= $order === 'views' ? '▼' : ($order === 'views_asc' ? '▲' : '↕') ?></a></th><?php if ($type === 'area'): ?><th>الحي</th><?php endif; ?><th>الطول</th><th>الحالة</th><th>آخر تعديل</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($rows as $r): ?>
    <tr>
      <td class="cb"><input type="checkbox" class="bulk-item" name="ids[]" value="<?= (int) $r['id'] ?>" form="bulkform" aria-label="<?= h($r['title']) ?>"></td>
      <td><?php if ($ranked): $rank = ($rank ?? 0) + 1; ?><span class="rank<?= $rank <= 3 ? ' rank-top' : '' ?>"><?= $rank ?></span><?php else: ?><input class="sort-input" type="number" name="sort[<?= (int) $r['id'] ?>]" value="<?= (int) $r['sort'] ?>" form="sortform"><?php endif; ?></td>
      <td><a href="post_edit.php?id=<?= (int) $r['id'] ?>"><strong><?= h($r['title']) ?></strong></a><br><small class="muted" dir="ltr"><?= h($r['slug']) ?></small></td>
      <td><a class="views-link" href="stats.php?tab=pages&scope=all&range=<?= $period === 'all' ? '90d' : ($period === '7' ? '7d' : ($period === '90' || $period === '365' ? '90d' : '30d')) ?>" title="تفاصيل"><?= views_cell($r['st'], $maxV) ?></a></td>
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
  <?php if (!$rows): ?><tr><td colspan="9" class="muted">مفيش نتايج. أضف <?= h(POST_TYPES[$type]['one']) ?> جديدة.</td></tr><?php endif; ?>
  </tbody>
</table>
</div>
<?php if ($rows && $type !== 'blog' && !$ranked): ?><p><button class="btn btn-ghost" form="sortform">احفظ الترتيب</button></p><?php endif; ?>
<p class="muted">الطول باللون البرتقالي معناه إن المحتوى أقل من 1500 حرف. المحتوى الأطول بيساعد في الأرشفة.</p>
<?php layout_end();
