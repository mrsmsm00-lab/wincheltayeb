<?php
// الإعدادات الافتراضية. القيم السرية تتحط في config.local.php (مش بيترفع على GitHub)
$CONFIG = [
    'db_path'           => __DIR__ . '/data/site.sqlite',
    'admin_url'         => 'https://admin.wincheltayeb.com',
    'site_url'          => 'https://wincheltayeb.com',
    'revalidate_url'    => 'https://wincheltayeb.com/api/revalidate',
    'revalidate_secret' => '',
    'allowed_origins'   => ['https://wincheltayeb.com', 'https://www.wincheltayeb.com', 'http://localhost:3000'],
    'max_upload_mb'     => 60,
];
if (is_file(__DIR__ . '/config.local.php')) {
    $CONFIG = array_merge($CONFIG, require __DIR__ . '/config.local.php');
}
return $CONFIG;
