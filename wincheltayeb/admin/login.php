<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/layout.php';
start_session();
if (!is_installed()) redirect('install.php');
if (current_user()) redirect('index.php');

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (login_blocked()) {
        $error = 'محاولات كتير غلط. استنى 15 دقيقة وجرب تاني.';
    } else {
        $s = db()->prepare('SELECT * FROM users WHERE username = ?');
        $s->execute([post_str('username', 50)]);
        $u = $s->fetch();
        if ($u && password_verify((string) ($_POST['password'] ?? ''), $u['password_hash'])) {
            login_reset();
            session_regenerate_id(true);
            $_SESSION['user'] = ['id' => (int) $u['id'], 'username' => $u['username']];
            $_SESSION['last_seen'] = time();
            if (password_needs_rehash($u['password_hash'], PASSWORD_DEFAULT)) {
                db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([password_hash($_POST['password'], PASSWORD_DEFAULT), $u['id']]);
            }
            redirect('index.php');
        }
        login_failed();
        usleep(400000);
        $error = 'اسم المستخدم أو الباسورد غلط.';
    }
}
layout_start('تسجيل الدخول');
?>
<div class="auth">
  <img src="assets/logo.svg" alt="" width="56" height="56">
  <h1>دخول لوحة ونش الطيب</h1>
  <?php if ($error): ?><div class="flash flash-err"><?= h($error) ?></div><?php endif; ?>
  <form method="post">
    <?= csrf_field() ?>
    <label>اسم المستخدم <input name="username" required autocomplete="username" dir="ltr" autofocus></label>
    <label>الباسورد <input type="password" name="password" required autocomplete="current-password" dir="ltr"></label>
    <button class="btn">دخول</button>
  </form>
</div>
<?php layout_end();
