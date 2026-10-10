<?php
// صفحات الهبوط: القايمة، القوالب، وإعدادات تحويلات Google Ads
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/layout.php';
require __DIR__ . '/includes/landing.php';
require __DIR__ . '/includes/stats.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $a = $_POST['action'] ?? '';
    if ($a === 'create') {
        $tpl = (string) ($_POST['template'] ?? '');
        if (!isset(lp_templates()[$tpl])) { flash('القالب مش موجود.', 'err'); redirect('landing.php'); }
        $slug = url_conflict('landing', 'ads') ? url_suggest('landing', 'ads') : 'ads';
        $d = lp_defaults();
        $data = ['logo' => '', 'og_image' => '', 'area_name' => $d['area_name'], 'keyword_default' => $d['keyword_default'], 'call_label' => $d['call_label'], 'wa_label' => $d['wa_label'],
            'wa_message' => $d['wa_message'], 'phone' => '', 'whatsapp' => '', 'button_style' => '', 'primary_override' => '', 'meta_title' => '', 'meta_description' => '', 'blocks' => lp_default_blocks()];
        db()->prepare('INSERT INTO landing_pages (slug, name, template, published, noindex, data) VALUES (?, ?, ?, 0, 0, ?)')
            ->execute([$slug, 'صفحة هبوط: ' . lp_templates()[$tpl]['name'], $tpl, json_encode($data, JSON_UNESCAPED_UNICODE)]);
        flash('اتعملت صفحة هبوط جديدة كمسودة. غيّر الرابط والكلام، وبعدين انشرها.');
        redirect('landing_edit.php?id=' . db()->lastInsertId());
    }
    if ($a === 'duplicate') {
        $s = db()->prepare('SELECT * FROM landing_pages WHERE id = ?'); $s->execute([(int) $_POST['id']]);
        if ($lp = $s->fetch()) {
            $slug = url_suggest('landing', $lp['slug']);
            db()->prepare('INSERT INTO landing_pages (slug, name, template, published, noindex, data) VALUES (?, ?, ?, 0, ?, ?)')->execute([$slug, $lp['name'] . ' (نسخة)', $lp['template'], $lp['noindex'], $lp['data']]);
            flash("اتعملت نسخة على الرابط /$slug كمسودة.");
            redirect('landing_edit.php?id=' . db()->lastInsertId());
        }
    }
    if ($a === 'delete') {
        db()->prepare('DELETE FROM landing_pages WHERE id = ?')->execute([(int) $_POST['id']]);
        revalidate_site(); flash('اتمسحت صفحة الهبوط.');
    }
    if ($a === 'toggle') {
        db()->prepare("UPDATE landing_pages SET published = 1 - published, updated_at = datetime('now') WHERE id = ?")->execute([(int) $_POST['id']]);
        revalidate_site(); flash('اتغيرت حالة النشر.');
    }
    if ($a === 'gads') {
        $id = strtoupper(trim((string) ($_POST['gads_id'] ?? '')));
        if ($id !== '' && !preg_match('/^AW-\d{6,14}$/', $id)) { flash('رقم Google Ads لازم يكون بالشكل AW-123456789.', 'err'); redirect('landing.php#gads'); }
        set_setting('gads_id', $id);
        set_setting('gads_call_label', preg_replace('/[^A-Za-z0-9_\-]/', '', (string) ($_POST['gads_call_label'] ?? '')));
        set_setting('gads_wa_label', preg_replace('/[^A-Za-z0-9_\-]/', '', (string) ($_POST['gads_wa_label'] ?? '')));
        set_setting('gads_site_wide', isset($_POST['gads_site_wide']) ? '1' : '');
        revalidate_site(); flash('اتحفظت إعدادات تحويلات Google Ads.');
    }
    redirect('landing.php');
}

$list = db()->query('SELECT * FROM landing_pages ORDER BY updated_at DESC')->fetchAll();
$since = strtotime('today') - 29 * 86400;
$st = stats_db()->prepare('SELECT COUNT(*) n, SUM(is_ads) ads, SUM(CASE WHEN calls + whatsapps > 0 THEN 1 ELSE 0 END) c FROM sessions WHERE landing = ? AND started_at >= ?');

layout_start('صفحات الهبوط', 'landing');
?>
<div class="head-row">
  <h1>صفحات الهبوط</h1>
  <a class="btn btn-ghost" href="stats.php?tab=landing&scope=lp">إحصائيات صفحات الهبوط</a>
</div>

<section class="box">
  <h2>صفحاتك</h2>
  <div class="table-wrap"><table class="table">
    <thead><tr><th>الاسم</th><th>الرابط</th><th>القالب</th><th>الحالة</th><th>آخر 30 يوم</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($list as $lp):
      $tpl = lp_templates()[$lp['template']] ?? null;
      $st->execute(['/' . $lp['slug'], $since]); $s = $st->fetch(); ?>
      <tr>
        <td><a href="landing_edit.php?id=<?= (int) $lp['id'] ?>"><strong><?= h($lp['name']) ?></strong></a><?= $lp['noindex'] ? ' <span class="tag">مخفية عن جوجل</span>' : ' <span class="tag">ظاهرة لجوجل</span>' ?></td>
        <td dir="ltr"><a href="<?= h(lp_url($lp['slug']) . ($lp['published'] ? '' : '?preview=' . lp_preview_token($lp['slug']))) ?>" target="_blank" rel="noopener">/<?= h($lp['slug']) ?></a></td>
        <td><?= $tpl ? lp_swatch($tpl) . ' ' . h($tpl['name']) : h($lp['template']) ?></td>
        <td><form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int) $lp['id'] ?>"><button class="pill <?= $lp['published'] ? 'pill-on' : 'pill-off' ?>"><?= $lp['published'] ? 'منشورة' : 'مسودة' ?></button></form></td>
        <td><?= (int) $s['n'] ?> زيارة • <?= (int) $s['ads'] ?> من إعلان • <strong><?= (int) $s['c'] ?> تواصل (<?= pct((int) $s['c'], (int) $s['n']) ?>)</strong></td>
        <td class="row-actions">
          <a href="landing_edit.php?id=<?= (int) $lp['id'] ?>">تعديل</a>
          <a href="stats.php?tab=overview&scope=all&landing=<?= h(rawurlencode('/' . $lp['slug'])) ?>&range=30d">الإحصائيات</a>
          <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="action" value="duplicate"><input type="hidden" name="id" value="<?= (int) $lp['id'] ?>"><button class="link-btn">نسخة</button></form>
          <form method="post" class="inline" data-confirm="تمسح صفحة «<?= h($lp['name']) ?>»؟ لو عليها إعلان شغال، الإعلان هيودّي على صفحة مش موجودة."><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $lp['id'] ?>"><button class="link-danger">مسح</button></form>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$list): ?><tr><td colspan="6" class="muted">لسه مفيش صفحات هبوط. اختار قالب من تحت.</td></tr><?php endif; ?>
    </tbody>
  </table></div>
</section>

<section class="box">
  <h2>صفحة هبوط جديدة: اختار قالب</h2>
  <p class="muted">كل القوالب فيها أزرار اتصال وواتساب ظاهرة دايماً على الموبايل. اللي بيفرق بينهم الألوان، وشكل الواجهة، ومكان الأزرار.</p>
  <ul class="tpl-grid">
    <?php foreach (lp_templates() as $t): ?>
      <li>
        <?= lp_swatch($t) ?>
        <strong><?= h($t['name']) ?></strong>
        <p class="muted"><?= h($t['desc']) ?></p>
        <p class="hint">الأزرار: <?= h(LP_BUTTON_STYLES[$t['buttons']] ?? $t['buttons']) ?></p>
        <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="create"><input type="hidden" name="template" value="<?= h($t['id']) ?>"><button class="btn">استخدم القالب ده</button></form>
      </li>
    <?php endforeach; ?>
  </ul>
</section>

<form method="post" class="box form-grid" id="gads">
  <?= csrf_field() ?><input type="hidden" name="action" value="gads">
  <h2>تحويلات Google Ads (اختياري)</h2>
  <p class="muted">لو حطيت البيانات دي، أي ضغطة على زرار اتصال أو واتساب في صفحات الهبوط بتتسجل «تحويل» في Google Ads، وده بيخلي جوجل يحسّن الإعلان لوحده ويجيبلك ناس بتتصل فعلاً. هتلاقيهم في Google Ads > الأهداف > التحويلات > إنشاء تحويل > موقع ويب > إعداد يدوي.</p>
  <div class="grid2">
    <label>رقم الحساب (Conversion ID) <input name="gads_id" dir="ltr" placeholder="AW-123456789" value="<?= h(setting('gads_id')) ?>"></label>
    <span></span>
    <label>Conversion label لضغطة الاتصال <input name="gads_call_label" dir="ltr" value="<?= h(setting('gads_call_label')) ?>"></label>
    <label>Conversion label لضغطة الواتساب <input name="gads_wa_label" dir="ltr" value="<?= h(setting('gads_wa_label')) ?>" placeholder="ممكن نفس label الاتصال"></label>
  </div>
  <label class="check"><input type="checkbox" name="gads_site_wide"<?= setting('gads_site_wide') ? ' checked' : '' ?>> سجّل التحويلات في كل صفحات الموقع كمان، مش صفحات الهبوط بس</label>
  <button class="btn">احفظ</button>
</form>
<?php layout_end();
