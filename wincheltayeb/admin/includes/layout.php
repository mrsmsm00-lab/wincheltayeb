<?php
function layout_start(string $title, string $active = ''): void {
    $u = current_user();
    $f = flash();
    $nav = ['index' => ['index.php', 'الرئيسية']];
    foreach (POST_TYPES as $k => $t) $nav[$k] = ["posts.php?type=$k", $t['label']];
    $nav['gallery'] = ['gallery.php', 'صور وفيديوهات'];
    $nav['settings'] = ['settings.php', 'الإعدادات'];
    ?><!doctype html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= h($title) ?> | لوحة ونش الطيب</title>
<link rel="icon" href="assets/logo.svg">
<link rel="stylesheet" href="assets/admin.css?v=1">
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
    echo '<script src="assets/admin.js?v=1"></script></body></html>';
}
