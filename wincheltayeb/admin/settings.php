<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/layout.php';
require __DIR__ . '/includes/ai.php';
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
    if ($action === 'ai') {
        $key = trim((string) ($_POST['openai_api_key'] ?? ''));
        if (isset($_POST['remove_key'])) set_setting('openai_api_key', '');
        elseif ($key !== '') {
            if (!preg_match('/^sk-[A-Za-z0-9_\-]{20,}$/', $key)) { $errors[] = 'شكل المفتاح مش صحيح. لازم يبدأ بـ sk-'; }
            else set_setting('openai_api_key', $key);
        }
        $model = post_str('openai_model', 80);
        if ($model !== '' && !preg_match('/^[A-Za-z0-9._:\-]+$/', $model)) $errors[] = 'اسم الموديل فيه حروف مش مسموحة.';
        else set_setting('openai_model', $model);
        set_setting('ai_daily_limit', (string) max(1, min(500, (int) ($_POST['ai_daily_limit'] ?? 40))));
        if (!$errors) { flash('اتحفظت إعدادات الذكاء الاصطناعي.'); redirect('settings.php#ai'); }
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

<form method="post" class="box form-grid narrow-form" id="ai">
  <?= csrf_field() ?><input type="hidden" name="action" value="ai">
  <h2>مساعد الكتابة بالذكاء الاصطناعي (OpenAI)</h2>
  <?php $src = ai_key_source(); ?>
  <p class="muted">الحالة: <?= $src === '' ? '<strong class="warn-text">مش متظبط</strong>' : '<strong style="color:var(--green)">شغال</strong> (المفتاح ' . h(ai_mask(ai_key())) . ($src === 'config' ? '، من ملف config.local.php' : '') . ')' ?>.
    الاستخدام النهارده: <?= ai_usage_today() ?> من <?= ai_daily_limit() ?>.</p>
  <?php if ($src !== 'config'): ?>
  <label>مفتاح OpenAI API <span class="hint">من platform.openai.com > API keys. سيبه فاضي لو مش عايز تغيّر المفتاح المحفوظ. المفتاح بيتحفظ على السيرفر بس.</span>
    <input type="password" name="openai_api_key" dir="ltr" autocomplete="off" placeholder="<?= $src ? h(ai_mask(ai_key())) : 'sk-...' ?>"></label>
  <?php if ($src === 'settings'): ?><label class="check"><input type="checkbox" name="remove_key"> امسح المفتاح المحفوظ</label><?php endif; ?>
  <?php endif; ?>
  <label>الموديل <span class="hint">سيبه فاضي عشان يستخدم <?= h(AI_DEFAULT_MODEL) ?>. لو OpenAI رفض الموديل، اكتب اسم موديل متاح لحسابك من صفحة Models في OpenAI.</span>
    <input name="openai_model" dir="ltr" value="<?= h(setting('openai_model')) ?>" placeholder="<?= h(AI_DEFAULT_MODEL) ?>"></label>
  <label>الحد الأقصى للطلبات في اليوم <input type="number" name="ai_daily_limit" min="1" max="500" value="<?= ai_daily_limit() ?>"></label>
  <button class="btn">احفظ إعدادات الـ AI</button>
</form>

<form method="post" class="box form-grid narrow-form">
  <?= csrf_field() ?><input type="hidden" name="action" value="password">
  <h2>تغيير الباسورد</h2>
  <label>الباسورد الحالي <input type="password" name="current" required autocomplete="current-password" dir="ltr"></label>
  <label>الباسورد الجديد <input type="password" name="new" required minlength="10" autocomplete="new-password" dir="ltr"></label>
  <button class="btn">غيّر الباسورد</button>
</form>
<?php layout_end();
