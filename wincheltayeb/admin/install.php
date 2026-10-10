<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/layout.php';
start_session();

if (is_installed()) { flash('اللوحة متثبتة بالفعل. امسح ملف install.php من السيرفر للأمان.', 'warn'); redirect('login.php'); }

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $user = post_str('username', 50); $pass = (string) ($_POST['password'] ?? '');
    $secret = (string) cfg('revalidate_secret');
    if (mb_strlen($secret) < 16) $error = 'اعمل ملف config.local.php وحط فيه revalidate_secret (16 حرف على الأقل) قبل التثبيت.';
    elseif (!hash_equals($secret, (string) ($_POST['secret'] ?? ''))) $error = 'كود التثبيت مش مطابق لـ revalidate_secret اللي في config.local.php.';
    elseif (!preg_match('/^[A-Za-z0-9_.-]{3,50}$/', $user)) $error = 'اسم المستخدم لازم يبقى حروف إنجليزي أو أرقام (3 حروف على الأقل).';
    elseif (mb_strlen($pass) < 10) $error = 'الباسورد لازم يبقى 10 حروف على الأقل.';
    else {
        $pdo = db();
        $pdo->beginTransaction();
        $pdo->prepare('INSERT INTO users (username, password_hash) VALUES (?, ?)')->execute([$user, password_hash($pass, PASSWORD_DEFAULT)]);
        $seedFile = __DIR__ . '/install/seed.json';
        $count = 0;
        if (is_file($seedFile)) {
            $seed = json_decode(file_get_contents($seedFile), true);
            foreach ($seed['settings'] ?? [] as $k => $v) set_setting($k, (string) $v);
            $i = 0;
            foreach ($seed['districts'] ?? [] as $k => $d) {
                $pdo->prepare('INSERT OR REPLACE INTO districts (key, name, color, sort) VALUES (?, ?, ?, ?)')->execute([$k, $d['name'], $d['color'], $i++]);
            }
            $ins = $pdo->prepare('INSERT OR IGNORE INTO posts (type, slug, title, excerpt, body, meta_title, meta_description, image, icon, district, place_name, lat, lng, eta, faqs, published, sort, published_at, updated_at)
                VALUES (:type, :slug, :title, :excerpt, :body, :meta_title, :meta_description, :image, :icon, :district, :place_name, :lat, :lng, :eta, :faqs, :published, :sort, :published_at, :updated_at)');
            foreach ($seed['posts'] ?? [] as $p) {
                $ins->execute([
                    ':type' => $p['type'], ':slug' => $p['slug'], ':title' => $p['title'], ':excerpt' => $p['excerpt'] ?? '',
                    ':body' => $p['body'] ?? '', ':meta_title' => $p['meta_title'] ?? '', ':meta_description' => $p['meta_description'] ?? '',
                    ':image' => $p['image'] ?? '', ':icon' => $p['icon'] ?? '', ':district' => $p['district'] ?? '', ':place_name' => $p['place_name'] ?? '',
                    ':lat' => $p['lat'], ':lng' => $p['lng'], ':eta' => $p['eta'] ?? '',
                    ':faqs' => json_encode($p['faqs'] ?? [], JSON_UNESCAPED_UNICODE), ':published' => (int) ($p['published'] ?? 1),
                    ':sort' => (int) ($p['sort'] ?? 0), ':published_at' => $p['published_at'] ?? date('Y-m-d'), ':updated_at' => $p['updated_at'] ?? date('Y-m-d'),
                ]);
                $count++;
            }
        }
        $pdo->commit();
        flash("اتثبتت اللوحة واستوردنا $count صفحة. سجّل دخول، وبعدها امسح install.php ومجلد install من السيرفر.");
        redirect('login.php');
    }
}
layout_start('تثبيت اللوحة');
?>
<div class="auth">
  <h1>تثبيت لوحة ونش الطيب</h1>
  <p>اعمل حساب المدير. المحتوى الأساسي للموقع (الخدمات والمناطق والمقالات) هيتستورد تلقائي.</p>
  <?php if ($error): ?><div class="flash flash-err"><?= h($error) ?></div><?php endif; ?>
  <form method="post">
    <?= csrf_field() ?>
    <label>كود التثبيت <span class="hint">نفس قيمة revalidate_secret في config.local.php، عشان محدش غيرك يثبّت اللوحة</span><input type="password" name="secret" required dir="ltr"></label>
    <label>اسم المستخدم <input name="username" required autocomplete="username" dir="ltr" value="<?= h($_POST['username'] ?? '') ?>"></label>
    <label>الباسورد (10 حروف على الأقل) <input type="password" name="password" required minlength="10" autocomplete="new-password" dir="ltr"></label>
    <button class="btn">ثبّت اللوحة</button>
  </form>
</div>
<?php layout_end();
