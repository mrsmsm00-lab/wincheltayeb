<?php
// نقطة اتصال المحرر بالذكاء الاصطناعي (للمدير بس)
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/ai.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex');

function ai_out(array $data, int $code = 200): never {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

start_session();
if (!current_user()) ai_out(['ok' => false, 'error' => 'انتهت الجلسة. سجّل دخول تاني.'], 401);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') ai_out(['ok' => false, 'error' => 'method'], 405);
if (!hash_equals($_SESSION['csrf'] ?? '', (string) ($_POST['csrf'] ?? ''))) ai_out(['ok' => false, 'error' => 'انتهت صلاحية الصفحة. حدّث الصفحة وجرب تاني.'], 400);
session_write_close(); // عشان الطلب الطويل ميقفلش باقي صفحات اللوحة

$action = (string) ($_POST['action'] ?? '');
if (!in_array($action, ['write', 'improve', 'seo', 'faqs'], true)) ai_out(['ok' => false, 'error' => 'أمر مش معروف.'], 400);

$limit = ai_daily_limit();
if (ai_usage_today() >= $limit) ai_out(['ok' => false, 'error' => "وصلت للحد اليومي ($limit طلب). تقدر تغيّره من الإعدادات."], 429);

@set_time_limit(190);
try {
    $type = (string) ($_POST['type'] ?? 'blog');
    if (!isset(POST_TYPES[$type])) $type = 'blog';
    $fields = ai_run($action, [
        'type' => $type,
        'title' => (string) ($_POST['title'] ?? ''),
        'idea' => (string) ($_POST['idea'] ?? ''),
        'body' => (string) ($_POST['body'] ?? ''),
        'place_name' => (string) ($_POST['place_name'] ?? ''),
        'format' => clean_format($_POST['format'] ?? 'markdown'),
    ]);
    ai_bump_usage();
    ai_out(['ok' => true, 'fields' => $fields, 'usage' => ai_usage_today(), 'limit' => $limit]);
} catch (RuntimeException $e) {
    ai_out(['ok' => false, 'error' => $e->getMessage()], 502);
}
