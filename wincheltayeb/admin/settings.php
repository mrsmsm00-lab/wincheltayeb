<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/layout.php';
$user = require_login();

$fields = [
    'site_name' => 'اسم الموقع', 'tagline' => 'الشعار', 'phone' => 'رقم التليفون (زي ما بيظهر)',
    'phone_intl' => 'الرقم الدولي (+20...)', 'whatsapp' => 'رقم الواتساب (201...)', 'email' => 'الإيميل',
    'address' => 'العنوان', 'hours' => 'مواعيد العمل', 'facebook' => 'فيسبوك', 'youtube' => 'يوتيوب', 'tiktok' => 'تيك توك', 'instagram' => 'إنستجرام',
];
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';
    if ($action === 'settings') {
        foreach ($fields as $k => $_) set_setting($k, post_str($k, 500));
        set_setting('general_faqs', json_encode(parse_faqs(post_str('general_faqs', 30000)), JSON_UNESCAPED_UNICODE));
        $s = db()->prepare('UPDATE districts SET name = ?, color = ? WHERE key = ?');
        foreach ((array) ($_POST['districts'] ?? []) as $k => $d) {
            if (preg_match('/^#[0-9a-fA-F]{6}$/', $d['color'] ?? '')) $s->execute([mb_substr(trim($d['name']), 0, 60), $d['color'], $k]);
        }
        $r = revalidate_site();
        flash('اتحفظت الإعدادات.' . ($r['ok'] ? '' : ' ' . $r['msg']), $r['ok'] ? 'ok' : 'warn');
        redirect('settings.php');
    }
    if ($action === 'password') {
        $s = db()->prepare('SELECT password_hash FROM users WHERE id = ?'); $s->execute([$user['id']]);
        $new = (string) ($_POST['new'] ?? '');
        if (!password_verify((string) ($_POST['current'] ?? ''), (string) $s->fetchColumn())) $errors[] = 'الباسورد الحالي غلط.';
        elseif (mb_strlen($new) < 10) $errors[] = 'الباسورد الجديد لازم يبقى 10 حروف على الأقل.';
        else {
            db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([password_hash($new, PASSWORD_DEFAULT), $user['id']]);
            flash('اتغير الباسورد.'); redirect('settings.php');
        }
    }
}
$districts = districts();
layout_start('الإعدادات', 'settings');
?>
<h1>الإعدادات</h1>
<?php foreach ($errors as $e): ?><div class="flash flash-err"><?= h($e) ?></div><?php endforeach; ?>
<form method="post" class="box form-grid">
  <?= csrf_field() ?><input type="hidden" name="action" value="settings">
  <h2>بيانات النشاط</h2>
  <?php foreach ($fields as $k => $label): ?>
    <label><?= h($label) ?> <input name="<?= $k ?>" value="<?= h(setting($k)) ?>"<?= in_array($k, ['phone', 'phone_intl', 'whatsapp', 'email', 'facebook', 'youtube', 'tiktok', 'instagram'], true) ? ' dir="ltr"' : '' ?>></label>
  <?php endforeach; ?>
  <h2>الأحياء وألوانها على الخريطة</h2>
  <?php foreach ($districts as $k => $d): ?>
    <div class="grid2"><label>الاسم <input name="districts[<?= h($k) ?>][name]" value="<?= h($d['name']) ?>"></label><label>اللون <input type="color" name="districts[<?= h($k) ?>][color]" value="<?= h($d['color']) ?>"></label></div>
  <?php endforeach; ?>
  <h2>أسئلة الصفحة الرئيسية</h2>
  <label><span class="hint">س: للسؤال وج: للإجابة</span><textarea name="general_faqs" rows="14"><?= h(faqs_to_text(setting('general_faqs', '[]'))) ?></textarea></label>
  <button class="btn">احفظ الإعدادات</button>
</form>

<form method="post" class="box form-grid narrow-form">
  <?= csrf_field() ?><input type="hidden" name="action" value="password">
  <h2>تغيير الباسورد</h2>
  <label>الباسورد الحالي <input type="password" name="current" required autocomplete="current-password" dir="ltr"></label>
  <label>الباسورد الجديد <input type="password" name="new" required minlength="10" autocomplete="new-password" dir="ltr"></label>
  <button class="btn">غيّر الباسورد</button>
</form>
<?php layout_end();
