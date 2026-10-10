<?php
// رفع صور من داخل المحرر
require __DIR__ . '/includes/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
start_session();
$out = function (array $d, int $code = 200) { http_response_code($code); echo json_encode($d, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); exit; };
if (!current_user()) $out(['ok' => false, 'error' => 'انتهت الجلسة. سجّل دخول تاني.'], 401);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') $out(['ok' => false, 'error' => 'method'], 405);
if (!hash_equals($_SESSION['csrf'] ?? '', (string) ($_POST['csrf'] ?? ''))) $out(['ok' => false, 'error' => 'حدّث الصفحة وجرب تاني.'], 400);
try {
    $url = handle_upload('file', ['image']);
    if (!$url) $out(['ok' => false, 'error' => 'مفيش ملف.'], 400);
    $out(['ok' => true, 'url' => $url]);
} catch (RuntimeException $e) {
    $out(['ok' => false, 'error' => $e->getMessage()], 400);
}
