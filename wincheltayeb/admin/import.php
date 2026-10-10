<?php
// استيراد مقالات وصفحات كتير مرة واحدة من ملف JSON
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/layout.php';
require_login();

$report = null;
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $json = '';
    if (!empty($_FILES['file']['tmp_name']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
        if ($_FILES['file']['size'] > 8 * 1024 * 1024) $errors[] = 'الملف أكبر من 8 ميجا.';
        else $json = (string) file_get_contents($_FILES['file']['tmp_name']);
    } else {
        $json = (string) ($_POST['json'] ?? '');
    }
    $json = preg_replace('/^\xEF\xBB\xBF/', '', trim($json));
    $data = $json !== '' ? json_decode($json, true) : null;
    if (!$errors && !is_array($data)) $errors[] = 'الملف مش JSON صحيح: ' . json_last_error_msg();
    $posts = is_array($data) ? ($data['posts'] ?? (array_is_list($data) ? $data : [])) : [];
    if (!$errors && !$posts) $errors[] = 'مفيش صفحات في الملف (المفروض تكون جوه "posts").';

    if (!$errors) {
        $mode = ($_POST['mode'] ?? 'skip') === 'update' ? 'update' : 'skip';
        $asDraft = isset($_POST['draft']);
        $pdo = db();
        $pdo->beginTransaction();
        $report = ['added' => [], 'updated' => [], 'skipped' => [], 'invalid' => [], 'districts' => 0];

        $i = (int) $pdo->query('SELECT COALESCE(MAX(sort), 0) FROM districts')->fetchColumn() + 1;
        foreach ((array) ($data['districts'] ?? []) as $k => $d) {
            if (!preg_match('/^[a-z0-9_-]{2,30}$/', (string) $k) || empty($d['name'])) continue;
            $color = preg_match('/^#[0-9a-fA-F]{6}$/', $d['color'] ?? '') ? $d['color'] : '#0B4EA2';
            $ins = $pdo->prepare('INSERT INTO districts (key, name, color, sort) VALUES (?, ?, ?, ?) ON CONFLICT(key) DO NOTHING');
            $ins->execute([$k, mb_substr($d['name'], 0, 60), $color, $i++]);
            $report['districts'] += $ins->rowCount();
        }

        $nextSort = (int) $pdo->query('SELECT COALESCE(MAX(sort), 0) FROM posts')->fetchColumn() + 1;
        $find = $pdo->prepare('SELECT id FROM posts WHERE type = ? AND slug = ?');
        foreach ($posts as $n => $p) {
            $type = $p['type'] ?? '';
            $title = trim((string) ($p['title'] ?? ''));
            $slug = slugify((string) ($p['slug'] ?? $title));
            if (!isset(POST_TYPES[$type]) || $title === '' || trim((string) ($p['body'] ?? '')) === '') {
                $report['invalid'][] = ($n + 1) . ': ' . ($title ?: '(من غير عنوان)'); continue;
            }
            $lat = is_numeric($p['lat'] ?? null) ? (float) $p['lat'] : null;
            $lng = is_numeric($p['lng'] ?? null) ? (float) $p['lng'] : null;
            if ($type === 'area' && ($lat === null || $lng === null)) { $report['invalid'][] = "$title (منطقة من غير إحداثيات)"; continue; }
            $row = [
                'title' => mb_substr($title, 0, 200), 'excerpt' => mb_substr(trim((string) ($p['excerpt'] ?? '')), 0, 500),
                'body' => str_replace("\r\n", "\n", (string) $p['body']),
                'format' => clean_format($p['format'] ?? 'markdown'),
                'meta_title' => mb_substr(trim((string) ($p['meta_title'] ?? '')), 0, 120),
                'meta_description' => mb_substr(trim((string) ($p['meta_description'] ?? '')), 0, 320),
                'image' => (string) ($p['image'] ?? ''), 'icon' => (string) ($p['icon'] ?? ''),
                'district' => (string) ($p['district'] ?? ''), 'place_name' => mb_substr((string) ($p['place_name'] ?? ''), 0, 120),
                'lat' => $lat, 'lng' => $lng, 'eta' => mb_substr((string) ($p['eta'] ?? ''), 0, 80),
                'faqs' => json_encode(array_values(array_filter((array) ($p['faqs'] ?? []), fn($f) => !empty($f['q']) && !empty($f['a']))), JSON_UNESCAPED_UNICODE),
                'published' => $asDraft ? 0 : (int) ($p['published'] ?? 1),
                'published_at' => preg_match('/^\d{4}-\d{2}-\d{2}$/', $p['published_at'] ?? '') ? $p['published_at'] : date('Y-m-d'),
                'updated_at' => date('Y-m-d'),
            ];
            $find->execute([$type, $slug]);
            $existing = $find->fetchColumn();
            if ($existing) {
                if ($mode === 'skip') { $report['skipped'][] = $title; continue; }
                unset($row['published']);
                $set = implode(', ', array_map(fn($c) => "$c = :$c", array_keys($row)));
                $pdo->prepare("UPDATE posts SET $set WHERE id = :id")->execute(array_combine(array_map(fn($c) => ":$c", array_keys($row)), array_values($row)) + [':id' => $existing]);
                $report['updated'][] = $title;
            } else {
                $row += ['type' => $type, 'slug' => $slug, 'sort' => isset($p['sort']) ? (int) $p['sort'] : $nextSort++];
                $cols = array_keys($row);
                $pdo->prepare('INSERT INTO posts (' . implode(',', $cols) . ') VALUES (' . implode(',', array_map(fn($c) => ":$c", $cols)) . ')')
                    ->execute(array_combine(array_map(fn($c) => ":$c", $cols), array_values($row)));
                $report['added'][] = $title;
            }
        }
        $pdo->commit();
        if ($report['added'] || $report['updated']) $report['revalidate'] = revalidate_site();
    }
}

layout_start('استيراد جماعي', 'import');
?>
<h1>استيراد جماعي</h1>
<p class="muted">ارفع ملف JSON فيه صفحات أو مقالات (زي ملفات الدفعات اللي بتجيلك). المقالات الموجودة بنفس الرابط مش بتتكرر.</p>
<?php foreach ($errors as $e): ?><div class="flash flash-err"><?= h($e) ?></div><?php endforeach; ?>

<?php if ($report): ?>
<section class="box">
  <h2>النتيجة</h2>
  <ul class="checks">
    <li class="good">اتضاف: <?= count($report['added']) ?></li>
    <li class="good">اتحدث: <?= count($report['updated']) ?></li>
    <li>اتساب لأنه موجود: <?= count($report['skipped']) ?></li>
    <?php if ($report['districts']): ?><li class="good">أحياء جديدة على الخريطة: <?= $report['districts'] ?></li><?php endif; ?>
    <?php if ($report['invalid']): ?><li class="bad">ناقص بيانات ومتضافش: <?= h(implode('، ', $report['invalid'])) ?></li><?php endif; ?>
  </ul>
  <?php if ($report['added']): ?><p><strong>الجديد:</strong> <?= h(implode('، ', $report['added'])) ?></p><?php endif; ?>
  <?php if (!empty($report['revalidate'])): ?><p class="muted"><?= h($report['revalidate']['msg']) ?></p><?php endif; ?>
  <?php if (isset($_POST['draft']) && $report['added']): ?><p>الصفحات اتضافت <strong>كمسودة</strong>. راجعها من القوايم ودوس على «مسودة» عشان تنشرها، واحدة واحدة أو على دفعات.</p><?php endif; ?>
</section>
<?php endif; ?>

<form method="post" enctype="multipart/form-data" class="box form-grid">
  <?= csrf_field() ?>
  <label>ملف JSON <input type="file" name="file" accept=".json,application/json"></label>
  <label>أو الزق المحتوى هنا <textarea name="json" rows="6" dir="ltr" placeholder='{"posts": [ ... ]}'></textarea></label>
  <label>لو الصفحة موجودة بنفس الرابط
    <select name="mode"><option value="skip">سيبها زي ما هي (متعدلش عليها)</option><option value="update">حدّثها بالمحتوى الجديد (حالة النشر مش بتتغير)</option></select></label>
  <label class="check"><input type="checkbox" name="draft" checked> استوردهم كمسودة عشان أراجعهم قبل النشر</label>
  <button class="btn">استورد</button>
</form>
<?php layout_end();
