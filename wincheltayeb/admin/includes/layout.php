<?php
// رقم نسخة للملفات بيتغير مع كل تعديل، عشان المتصفح ميفضلش شايل النسخة القديمة
function asset_v(string $file): string {
    $path = __DIR__ . '/../assets/' . $file;
    return is_file($path) ? (string) filemtime($path) : '1';
}
function layout_start(string $title, string $active = ''): void {
    $u = current_user();
    $f = flash();
    $nav = ['index' => ['index.php', 'لوحة التحكم'], 'home' => ['home.php', 'الصفحة الرئيسية'], 'menu' => ['menu.php', 'القايمة']];
    foreach (POST_TYPES as $k => $t) $nav[$k] = ["posts.php?type=$k", $t['label']];
    $nav['gallery'] = ['gallery.php', 'صور وفيديوهات'];
    $nav['import'] = ['import.php', 'استيراد'];
    $nav['settings'] = ['settings.php', 'الإعدادات'];
    ?><!doctype html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= h($title) ?> | لوحة ونش الطيب</title>
<link rel="icon" href="assets/logo.svg">
<link rel="stylesheet" href="assets/admin.css?v=<?= asset_v('admin.css') ?>">
</head>
<body>
<?php if ($u): ?>
<header class="bar">
  <a class="brand" href="index.php"><img src="assets/logo.svg" alt="" width="34" height="34"> لوحة ونش الطيب</a>
  <nav><?php foreach ($nav as $k => [$href, $label]): ?><a href="<?= h($href) ?>"<?= $k === $active ? ' aria-current="page"' : '' ?>><?= h($label) ?></a><?php endforeach; ?></nav>
  <div class="bar-end"><a href="<?= h(cfg('site_url')) ?>" target="_blank" rel="noopener">فتح الموقع</a> <a href="logout.php">خروج (<?= h($u['username']) ?>)</a></div>
</header>
<?php endif; ?>
<main class="main">
<?php if ($f): ?><div class="flash flash-<?= h($f[1]) ?>" role="status"><?= h($f[0]) ?></div><?php endif; ?>
<?php
}
function layout_end(array $scripts = []): void {
    echo "</main>\n";
    foreach ($scripts as $s) echo '<script src="' . h($s) . '"></script>' . "\n";
    echo '<script src="assets/admin.js?v=' . asset_v('admin.js') . '"></script></body></html>';
}
