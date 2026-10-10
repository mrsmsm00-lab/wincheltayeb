<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/layout.php';
require __DIR__ . '/includes/stats.php';
require_login();

$tab = $_GET['tab'] ?? 'overview';
$TABS = ['overview' => 'نظرة عامة', 'ads' => 'Google Ads', 'landing' => 'صفحات الهبوط', 'sources' => 'المصادر', 'countries' => 'الدول والأجهزة', 'pages' => 'الصفحات',
    'clicks' => 'الضغطات', 'sessions' => 'آخر الزيارات', 'ips' => 'الزيارات المكررة', 'settings' => 'إعدادات'];
if (!isset($TABS[$tab]) && $tab !== 'session') $tab = 'overview';

/* ---------- الإعدادات ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $a = $_POST['action'] ?? '';
    if ($a === 'settings') {
        set_setting('stats_retention_days', (string) max(7, min(1095, (int) ($_POST['retention'] ?? 180))));
        $ips = array_filter(array_map('trim', preg_split('/[\s,]+/', (string) ($_POST['exclude_ips'] ?? ''))), fn($x) => filter_var($x, FILTER_VALIDATE_IP));
        set_setting('stats_exclude_ips', implode(', ', array_unique($ips)));
        flash('اتحفظت إعدادات الإحصائيات.');
    } elseif ($a === 'geo') {
        $r = geo_update(); flash($r['msg'], $r['ok'] ? 'ok' : 'err');
    } elseif ($a === 'add_my_ip') {
        $mine = client_ip();
        $list = array_filter(array_map('trim', explode(',', setting('stats_exclude_ips'))));
        $list[] = $mine;
        set_setting('stats_exclude_ips', implode(', ', array_unique($list)));
        flash("اتضاف الـ IP بتاعك ($mine) للمستبعدين.");
    } elseif ($a === 'purge') {
        $db = stats_db(); $db->exec('DELETE FROM pageviews; DELETE FROM events; DELETE FROM sessions;'); $db->exec('VACUUM');
        flash('اتمسحت كل الإحصائيات.', 'warn');
    }
    redirect('stats.php?tab=settings');
}

stats_cleanup();
$db = stats_db();
$defaultScope = in_array($tab, ['ads', 'session', 'ips', 'sessions'], true) ? 'all' : 'site';
if (!isset($_GET['scope'])) $_GET['scope'] = $defaultScope;
[$W, $A] = stats_where($tab === 'ads' ? ['ads' => '1'] : []);
[$from, $to, $range] = stats_range();
$q = fn(string $sql, array $args = []) => (function () use ($db, $sql, $args) { $s = $db->prepare($sql); $s->execute($args); return $s; })();
$qs = function (array $over = []) { $p = array_merge($_GET, $over); return '?' . http_build_query(array_filter($p, fn($v) => $v !== '' && $v !== null)); };

/* ---------- تصدير CSV ---------- */
if (($tab === 'sessions' || $tab === 'ads') && ($_GET['export'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="visits-' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w'); fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['الوقت', 'IP', 'الدولة', 'الجهاز', 'المصدر', 'الحملة', 'الكلمة', 'صفحة الدخول', 'صفحات', 'المدة (ث)', 'اتصال', 'واتساب', 'gclid']);
    foreach ($q("SELECT * FROM sessions s WHERE $W ORDER BY started_at DESC LIMIT 20000", $A) as $s) {
        fputcsv($out, [date('Y-m-d H:i', $s['started_at']), $s['ip'], $s['country'], $s['device'], SOURCE_TYPES[$s['source_type']] ?? $s['source_type'],
            $s['utm_campaign'] ?: $s['campaign_id'], $s['keyword'] ?: $s['utm_term'], $s['landing'], $s['pageviews'], $s['duration'], $s['calls'], $s['whatsapps'], $s['gclid']]);
    }
    exit;
}

/* ---------- أدوات العرض ---------- */
function kpi(string $label, $value, string $sub = '', string $cls = ''): void {
    echo '<div class="kpi ' . $cls . '"><span>' . h($label) . '</span><strong>' . h((string) $value) . '</strong>' . ($sub ? '<small>' . h($sub) . '</small>' : '') . '</div>';
}
function bars(array $rows, string $labelKey, string $valKey, int $max = 0, ?callable $label = null, ?callable $link = null): void {
    $max = $max ?: max(1, ...array_map(fn($r) => (int) $r[$valKey], $rows ?: [[$valKey => 1]]));
    echo '<ul class="hbars">';
    foreach ($rows as $r) {
        $l = $label ? $label($r) : $r[$labelKey];
        $txt = h($l === '' ? '—' : $l);
        if ($link) $txt = '<a href="' . h($link($r)) . '">' . $txt . '</a>';
        echo '<li><span class="hb-label">' . $txt . '</span><span class="hb-bar"><i style="width:' . round((int) $r[$valKey] * 100 / $max, 1) . '%"></i></span><span class="hb-val">' . number_format((int) $r[$valKey]) . '</span></li>';
    }
    if (!$rows) echo '<li class="muted">مفيش بيانات في الفترة دي.</li>';
    echo '</ul>';
}
function day_chart(PDO $db, string $W, array $A, int $from, int $to): void {
    $off = (int) date('Z');
    $s = $db->prepare("SELECT date(started_at + $off, 'unixepoch') d, COUNT(*) v, SUM(CASE WHEN calls + whatsapps > 0 THEN 1 ELSE 0 END) c FROM sessions s WHERE $W GROUP BY d");
    $s->execute($A);
    $map = []; foreach ($s as $r) $map[$r['d']] = $r;
    $days = []; for ($t = strtotime(date('Y-m-d', $from)); $t <= $to; $t += 86400) $days[] = date('Y-m-d', $t);
    if (count($days) > 120) $days = array_slice($days, -120);
    $max = max(1, ...array_map(fn($d) => (int) ($map[$d]['v'] ?? 0), $days));
    $w = 900; $h = 180; $bw = $w / max(1, count($days));
    echo '<svg class="daychart" viewBox="0 0 ' . $w . ' ' . ($h + 24) . '" role="img" aria-label="الزيارات كل يوم">';
    foreach ($days as $i => $d) {
        $v = (int) ($map[$d]['v'] ?? 0); $c = (int) ($map[$d]['c'] ?? 0);
        $bh = $v / $max * $h; $ch = $c / $max * $h; $x = $w - ($i + 1) * $bw;
        echo '<g><title>' . h($d) . ': ' . $v . ' زيارة، ' . $c . ' اتصلوا أو راسلوا</title>';
        echo '<rect x="' . round($x + 1, 1) . '" y="' . round($h - $bh, 1) . '" width="' . round(max(1, $bw - 2), 1) . '" height="' . round($bh, 1) . '" class="b-v"/>';
        if ($c) echo '<rect x="' . round($x + 1, 1) . '" y="' . round($h - $ch, 1) . '" width="' . round(max(1, $bw - 2), 1) . '" height="' . round($ch, 1) . '" class="b-c"/>';
        echo '</g>';
        if (count($days) <= 31 || $i % 7 === 0) echo '<text x="' . round($x + $bw / 2, 1) . '" y="' . ($h + 16) . '" text-anchor="middle">' . (int) substr($d, 8) . '</text>';
    }
    echo '</svg><p class="legend"><span class="lg-v"></span> زيارات <span class="lg-c"></span> زيارات فيها اتصال أو واتساب</p>';
}
function summary(PDO $db, string $W, array $A): array {
    $s = $db->prepare("SELECT COUNT(*) sessions, COUNT(DISTINCT vid) visitors, COALESCE(SUM(pageviews),0) pv, COALESCE(AVG(duration),0) dur,
        SUM(CASE WHEN pageviews <= 1 AND duration < 10 THEN 1 ELSE 0 END) bounces, COALESCE(SUM(calls),0) calls, COALESCE(SUM(whatsapps),0) was,
        SUM(CASE WHEN calls + whatsapps > 0 THEN 1 ELSE 0 END) conv, SUM(CASE WHEN new_visitor = 0 THEN 1 ELSE 0 END) ret, COUNT(DISTINCT ip) ips
        FROM sessions s WHERE $W");
    $s->execute($A);
    return $s->fetch();
}
function src_label(array $r): string { return (SOURCE_TYPES[$r['source_type']] ?? $r['source_type']) . (($r['source_name'] ?? '') && !in_array($r['source_name'], ['مباشر', 'Google Ads'], true) ? ' • ' . $r['source_name'] : ''); }

layout_start('الإحصائيات', 'stats');
?>
<div class="head-row"><h1>الإحصائيات</h1></div>
<nav class="tabs" aria-label="أقسام الإحصائيات">
  <?php foreach ($TABS as $k => $l): ?><a href="<?= h($qs(['tab' => $k, 'page' => null, 'sid' => null])) ?>" class="<?= $k === 'ads' ? 'tab-ads ' : '' ?><?= $tab === $k ? 'is-active' : '' ?>"><?= h($l) ?></a><?php endforeach; ?>
</nav>

<?php if ($tab !== 'settings' && $tab !== 'session'): ?>
<form class="filters box" method="get">
  <input type="hidden" name="tab" value="<?= h($tab) ?>">
  <?php if (!empty($_GET['landing'])): ?><input type="hidden" name="landing" value="<?= h($_GET['landing']) ?>"><?php elseif ($tab !== 'landing'): ?>
  <label>النطاق <select name="scope"><?php foreach (STATS_SCOPES as $k => $l): ?><option value="<?= $k ?>"<?= ($_GET['scope'] ?? 'site') === $k ? ' selected' : '' ?>><?= h($l) ?></option><?php endforeach; ?></select></label>
  <?php endif; ?>
  <label>الفترة <select name="range" onchange="this.form.querySelector('.custom-range').hidden = this.value !== 'custom'">
    <?php foreach (['today' => 'النهارده', 'yesterday' => 'امبارح', '7d' => 'آخر 7 أيام', '30d' => 'آخر 30 يوم', '90d' => 'آخر 90 يوم', 'custom' => 'فترة مخصصة'] as $k => $l): ?><option value="<?= $k ?>"<?= $range === $k ? ' selected' : '' ?>><?= $l ?></option><?php endforeach; ?>
  </select></label>
  <span class="custom-range"<?= $range === 'custom' ? '' : ' hidden' ?>><label>من <input type="date" name="from" value="<?= h($_GET['from'] ?? date('Y-m-d', $from)) ?>"></label><label>لحد <input type="date" name="to" value="<?= h($_GET['to'] ?? date('Y-m-d', $to)) ?>"></label></span>
  <?php if ($tab !== 'ads'): ?>
  <label>المصدر <select name="source"><option value="">الكل</option><?php foreach (SOURCE_TYPES as $k => $l): if ($k === 'internal') continue; ?><option value="<?= $k ?>"<?= ($_GET['source'] ?? '') === $k ? ' selected' : '' ?>><?= h($l) ?></option><?php endforeach; ?></select></label>
  <?php endif; ?>
  <label>الدولة <select name="country"><option value="">الكل</option><?php foreach ($q("SELECT country, COUNT(*) n FROM sessions GROUP BY country ORDER BY n DESC LIMIT 60") as $c): ?><option value="<?= h($c['country'] ?: '--') ?>"<?= ($_GET['country'] ?? '') === ($c['country'] ?: '--') ? ' selected' : '' ?>><?= h(country_label($c['country'])) ?></option><?php endforeach; ?></select></label>
  <label>الجهاز <select name="device"><option value="">الكل</option><?php foreach (DEVICE_NAMES as $k => $l): ?><option value="<?= $k ?>"<?= ($_GET['device'] ?? '') === $k ? ' selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select></label>
  <label>التحويل <select name="converted"><option value="">الكل</option><option value="1"<?= ($_GET['converted'] ?? '') === '1' ? ' selected' : '' ?>>اتصل أو راسل</option><option value="0"<?= ($_GET['converted'] ?? '') === '0' ? ' selected' : '' ?>>متصلش</option></select></label>
  <label>الزوار <select name="returning"><option value="">الكل</option><option value="1"<?= ($_GET['returning'] ?? '') === '1' ? ' selected' : '' ?>>راجعين تاني بس</option></select></label>
  <label>IP <input name="ip" value="<?= h($_GET['ip'] ?? '') ?>" dir="ltr" placeholder="مثلاً 41.33.1.2"></label>
  <button class="btn">طبّق</button>
  <a href="stats.php?tab=<?= h($tab) ?>" class="link-btn">امسح الفلاتر</a>
</form>
<?php endif; ?>

<?php if (!empty($_GET['landing'])): ?>
<div class="flash flash-ok">إنت شايف إحصائيات الزيارات اللي دخلت من <strong dir="ltr"><?= h($_GET['landing']) ?></strong> بس. <a href="<?= h($qs(['landing' => null])) ?>">ارجع لكل الموقع</a></div>
<?php endif; ?>
<?php
switch ($tab):
/* ================= نظرة عامة ================= */
case 'overview':
    $S = summary($db, $W, $A);
    echo '<div class="kpis">';
    kpi('الزيارات', number_format((int) $S['sessions']), number_format((int) $S['visitors']) . ' زائر مختلف');
    kpi('مشاهدات الصفحات', number_format((int) $S['pv']), $S['sessions'] ? round($S['pv'] / $S['sessions'], 1) . ' صفحة لكل زيارة' : '');
    kpi('متوسط وقت الزيارة', fmt_dur((int) round($S['dur'])));
    kpi('خرجوا بسرعة', pct($S['bounces'], $S['sessions']), 'صفحة واحدة وأقل من 10 ث');
    kpi('ضغطوا اتصال', number_format((int) $S['calls']), '', 'kpi-good');
    kpi('ضغطوا واتساب', number_format((int) $S['was']), '', 'kpi-good');
    kpi('نسبة التحويل', pct($S['conv'], $S['sessions']), number_format((int) $S['conv']) . ' زيارة فيها تواصل', 'kpi-good');
    kpi('زوار راجعين', pct($S['ret'], $S['sessions']), number_format((int) $S['ips']) . ' IP مختلف');
    echo '</div><section class="box">';
    day_chart($db, $W, $A, $from, $to);
    echo '</section><div class="cols">';
    echo '<section class="box"><h2>المصادر</h2>'; bars($q("SELECT source_type, COUNT(*) n FROM sessions s WHERE $W GROUP BY source_type ORDER BY n DESC", $A)->fetchAll(), 'source_type', 'n', 0, fn($r) => SOURCE_TYPES[$r['source_type']] ?? $r['source_type'], fn($r) => $qs(['tab' => 'sessions', 'source' => $r['source_type']])); echo '</section>';
    echo '<section class="box"><h2>الدول</h2>'; bars($q("SELECT country, COUNT(*) n FROM sessions s WHERE $W GROUP BY country ORDER BY n DESC LIMIT 8", $A)->fetchAll(), 'country', 'n', 0, fn($r) => country_label($r['country']), fn($r) => $qs(['tab' => 'sessions', 'country' => $r['country'] ?: '--'])); echo '</section>';
    echo '<section class="box"><h2>أكتر صفحات الدخول</h2>'; bars($q("SELECT landing, COUNT(*) n FROM sessions s WHERE $W GROUP BY landing ORDER BY n DESC LIMIT 8", $A)->fetchAll(), 'landing', 'n', 0, null, fn($r) => $qs(['tab' => 'sessions', 'landing' => $r['landing']])); echo '</section>';
    echo '<section class="box"><h2>الضغطات</h2>'; bars($q("SELECT e.category, COUNT(*) n FROM events e JOIN sessions s ON s.sid = e.sid WHERE $W GROUP BY e.category ORDER BY n DESC", $A)->fetchAll(), 'category', 'n', 0, fn($r) => EVENT_NAMES[$r['category']] ?? $r['category']); echo '</section>';
    echo '<section class="box"><h2>الأجهزة</h2>'; bars($q("SELECT device, COUNT(*) n FROM sessions s WHERE $W GROUP BY device ORDER BY n DESC", $A)->fetchAll(), 'device', 'n', 0, fn($r) => DEVICE_NAMES[$r['device']] ?? $r['device']); echo '</section>';
    echo '<section class="box"><h2>آخر 8 زيارات</h2><ul class="mini-list">';
    foreach ($q("SELECT * FROM sessions s WHERE $W ORDER BY started_at DESC LIMIT 8", $A) as $s) echo '<li><a href="' . h($qs(['tab' => 'session', 'sid' => $s['sid']])) . '">' . date('H:i', $s['started_at']) . ' • ' . h(country_label($s['country'])) . ' • ' . h(src_label($s)) . ' • ' . h($s['landing']) . '</a>' . (($s['calls'] + $s['whatsapps']) ? ' <span class="pill pill-on">تواصل</span>' : '') . '</li>';
    echo '</ul></section></div>';
    break;

/* ================= Google Ads ================= */
case 'ads':
    $S = summary($db, $W, $A);
    echo '<div class="kpis">';
    kpi('زيارات من الإعلانات', number_format((int) $S['sessions']), number_format((int) $S['ips']) . ' IP مختلف');
    kpi('اتصلوا', number_format((int) $S['calls']), '', 'kpi-good');
    kpi('راسلوا واتساب', number_format((int) $S['was']), '', 'kpi-good');
    kpi('نسبة التحويل', pct($S['conv'], $S['sessions']), number_format((int) $S['conv']) . ' زيارة فيها تواصل', 'kpi-good');
    kpi('متوسط وقت الزيارة', fmt_dur((int) round($S['dur'])));
    kpi('خرجوا بسرعة', pct($S['bounces'], $S['sessions']), 'صفحة واحدة وأقل من 10 ث', 'kpi-warn');
    echo '</div><section class="box">'; day_chart($db, $W, $A, $from, $to); echo '</section>';
    $grp = function (string $col, string $title) use ($q, $W, $A, $qs) {
        $rows = $q("SELECT $col k, COUNT(*) n, COUNT(DISTINCT ip) ips, SUM(CASE WHEN calls + whatsapps > 0 THEN 1 ELSE 0 END) c, AVG(duration) d,
            SUM(CASE WHEN pageviews <= 1 AND duration < 10 THEN 1 ELSE 0 END) b FROM sessions s WHERE $W GROUP BY k ORDER BY n DESC LIMIT 25", $A)->fetchAll();
        echo '<section class="box"><h2>' . h($title) . '</h2><div class="table-wrap"><table class="table"><thead><tr><th>' . h($title) . '</th><th>زيارات</th><th>IPs</th><th>تواصل</th><th>التحويل</th><th>متوسط الوقت</th><th>خروج سريع</th></tr></thead><tbody>';
        foreach ($rows as $r) echo '<tr><td>' . h($r['k'] !== '' ? $r['k'] : 'مش متسجل') . '</td><td>' . $r['n'] . '</td><td>' . $r['ips'] . '</td><td>' . $r['c'] . '</td><td><strong>' . pct($r['c'], $r['n']) . '</strong></td><td>' . fmt_dur((int) $r['d']) . '</td><td>' . pct($r['b'], $r['n']) . '</td></tr>';
        if (!$rows) echo '<tr><td colspan="7" class="muted">مفيش زيارات من الإعلانات في الفترة دي.</td></tr>';
        echo '</tbody></table></div></section>';
    };
    $grp("COALESCE(NULLIF(utm_campaign,''), campaign_id)", 'الحملة');
    $grp("COALESCE(NULLIF(keyword,''), utm_term)", 'الكلمة المفتاحية');
    $grp('landing', 'صفحة الهبوط');
    $grp('device', 'الجهاز');

    $sus = $q("SELECT ip, COUNT(*) n, SUM(CASE WHEN calls + whatsapps > 0 THEN 1 ELSE 0 END) c, MIN(started_at) f, MAX(started_at) l, AVG(duration) d, MAX(country) cc
        FROM sessions s WHERE $W AND ip != '' GROUP BY ip HAVING n >= 2 ORDER BY n DESC LIMIT 100", $A)->fetchAll();
    $short = $q("SELECT COUNT(*) FROM sessions s WHERE $W AND pageviews <= 1 AND duration < 5", $A)->fetchColumn();
    echo '<section class="box"><h2>ضغطات مشكوك فيها</h2><p class="muted">IPs ضغطت على الإعلان أكتر من مرة في الفترة دي. لو العدد كبير والتحويل صفر، ممكن يكون منافس أو ضغطات وهمية. وفيه <strong>' . (int) $short . '</strong> زيارة إعلان خرجت في أقل من 5 ثواني.</p>';
    echo '<div class="table-wrap"><table class="table"><thead><tr><th>IP</th><th>الدولة</th><th>ضغطات الإعلان</th><th>تواصل</th><th>متوسط الوقت</th><th>أول مرة</th><th>آخر مرة</th><th></th></tr></thead><tbody>';
    $flag = [];
    foreach ($sus as $r) {
        $bad = $r['n'] >= 3 && !$r['c'];
        if ($bad) $flag[] = $r['ip'];
        echo '<tr' . ($bad ? ' class="row-bad"' : '') . '><td dir="ltr">' . h($r['ip']) . '</td><td>' . h(country_label((string) $r['cc'])) . '</td><td><strong>' . $r['n'] . '</strong></td><td>' . $r['c'] . '</td><td>' . fmt_dur((int) $r['d']) . '</td><td>' . date('m-d H:i', $r['f']) . '</td><td>' . date('m-d H:i', $r['l']) . '</td><td><a href="' . h($qs(['tab' => 'sessions', 'ip' => $r['ip'], 'ads' => '1'])) . '">الزيارات</a></td></tr>';
    }
    if (!$sus) echo '<tr><td colspan="8" class="muted">مفيش IP ضغط على الإعلان أكتر من مرة.</td></tr>';
    echo '</tbody></table></div>';
    if ($flag) echo '<label>IPs مقترح تستبعدها من Google Ads (3 ضغطات أو أكتر من غير تواصل) <span class="hint">في Google Ads: الحملة > الإعدادات > استبعاد عناوين IP، والزقهم هناك</span><textarea readonly rows="3" dir="ltr" onclick="this.select()">' . h(implode("\n", $flag)) . '</textarea></label>';
    echo '</section>';

    echo '<section class="box"><h2>آخر زيارات الإعلانات</h2><p><a class="btn btn-ghost" href="' . h($qs(['tab' => 'sessions', 'ads' => '1'])) . '">كل زيارات الإعلانات بالتفصيل</a> <a class="btn btn-ghost" href="' . h($qs(['export' => 'csv'])) . '">تصدير Excel (CSV)</a></p></section>';
    $suffix = 'utm_source=google&utm_medium=cpc&utm_campaign={campaignid}&adgroup={adgroupid}&keyword={keyword}&matchtype={matchtype}&device={device}&network={network}';
    echo '<section class="box"><h2>إعداد التتبع في Google Ads (مرة واحدة)</h2>
      <ol class="setup-steps">
        <li>في Google Ads افتح <strong>الإعدادات > إعدادات الحساب > التتبع التلقائي (Auto-tagging)</strong> واتأكد إنه شغال. ده بيضيف <code>gclid</code> ونعرف منه إن الزيارة من إعلان.</li>
        <li>عشان تشوف الحملة والكلمة المفتاحية هنا، افتح <strong>الإعدادات > إعدادات الحساب > التتبع > لاحقة عنوان URL النهائي (Final URL suffix)</strong> والزق ده:
          <textarea readonly rows="2" dir="ltr" onclick="this.select()">' . h($suffix) . '</textarea></li>
        <li>بعد ساعات من التشغيل هتبدأ الحملات والكلمات تظهر في الجداول اللي فوق.</li>
      </ol>
      <p class="muted">الأرقام هنا بتيجي من زوار الموقع نفسه. Google Ads بيحسب الضغطة لحظة ما الشخص يدوس على الإعلان، وإحنا بنحسبها لما الصفحة تحمّل فعلاً، فطبيعي يبقى فيه فرق بسيط. وفرق كبير معناه إن ناس بتضغط وتقفل قبل ما الصفحة تفتح.</p></section>';
    break;

/* ================= صفحات الهبوط ================= */
case 'landing':
    [$W2, $A2] = stats_where(['scope' => 'lp']);
    $S = summary($db, $W2, $A2);
    echo '<div class="kpis">';
    kpi('زيارات صفحات الهبوط', number_format((int) $S['sessions']), number_format((int) $S['visitors']) . ' زائر');
    kpi('اتصلوا', number_format((int) $S['calls']), '', 'kpi-good');
    kpi('راسلوا واتساب', number_format((int) $S['was']), '', 'kpi-good');
    kpi('نسبة التحويل', pct($S['conv'], $S['sessions']), '', 'kpi-good');
    kpi('متوسط الوقت', fmt_dur((int) round($S['dur'])));
    kpi('خرجوا بسرعة', pct($S['bounces'], $S['sessions']), '', 'kpi-warn');
    echo '</div><section class="box">'; day_chart($db, $W2, $A2, $from, $to); echo '</section>';
    $names = []; foreach (db()->query('SELECT slug, name, template, published FROM landing_pages') as $r) $names['/' . $r['slug']] = $r;
    $rows = $q("SELECT landing, COUNT(*) n, SUM(is_ads) ads, COUNT(DISTINCT ip) ips, SUM(calls) calls, SUM(whatsapps) was, SUM(CASE WHEN calls + whatsapps > 0 THEN 1 ELSE 0 END) c,
        AVG(duration) d, SUM(CASE WHEN pageviews <= 1 AND duration < 10 THEN 1 ELSE 0 END) b FROM sessions s WHERE $W2 GROUP BY landing ORDER BY n DESC", $A2)->fetchAll();
    echo '<section class="box"><h2>مقارنة الصفحات</h2><p class="muted">قارن بين القوالب والصفحات: أنهي واحدة بتحوّل الزوار لمكالمات أكتر؟ دوس على أي صفحة تشوف إحصائياتها كاملة (المصادر، والدول، والكلمات، وكل زائر عمل إيه).</p><div class="table-wrap"><table class="table"><thead><tr><th>الصفحة</th><th>زيارات</th><th>من إعلان</th><th>IPs</th><th>اتصال</th><th>واتساب</th><th>التحويل</th><th>متوسط الوقت</th><th>خروج سريع</th></tr></thead><tbody>';
    foreach ($rows as $r) {
        $nm = $names[$r['landing']] ?? null;
        echo '<tr><td><a href="' . h($qs(['tab' => 'overview', 'landing' => $r['landing'], 'scope' => 'all'])) . '"><strong>' . h($nm['name'] ?? $r['landing']) . '</strong></a><br><small dir="ltr" class="muted">' . h($r['landing']) . '</small>' . ($nm && !$nm['published'] ? ' <span class="tag">مسودة</span>' : '') . '</td><td>' . $r['n'] . '</td><td>' . $r['ads'] . '</td><td>' . $r['ips'] . '</td><td>' . $r['calls'] . '</td><td>' . $r['was'] . '</td><td><strong>' . pct($r['c'], $r['n']) . '</strong></td><td>' . fmt_dur((int) $r['d']) . '</td><td>' . pct($r['b'], $r['n']) . '</td></tr>';
    }
    if (!$rows) echo '<tr><td colspan="9" class="muted">مفيش زيارات لصفحات الهبوط في الفترة دي.</td></tr>';
    echo '</tbody></table></div></section>';
    echo '<section class="box"><h2>أنهي زرار بيتضغط أكتر في صفحات الهبوط؟</h2>';
    bars($q("SELECT e.label, COUNT(*) n FROM events e JOIN sessions s ON s.sid = e.sid WHERE $W2 AND e.category IN ('call','whatsapp') GROUP BY e.label ORDER BY n DESC LIMIT 12", $A2)->fetchAll(), 'label', 'n');
    echo '<p class="hint">اسم الزرار بيوضح مكانه (الواجهة، العائم، الشريط الثابت…)، فتعرف أنهي مكان أحسن.</p></section>';
    break;

/* ================= المصادر ================= */
case 'sources':
    $rows = $q("SELECT source_type, source_name, COUNT(*) n, COUNT(DISTINCT vid) v, AVG(duration) d, SUM(CASE WHEN calls + whatsapps > 0 THEN 1 ELSE 0 END) c
        FROM sessions s WHERE $W GROUP BY source_type, source_name ORDER BY n DESC LIMIT 100", $A)->fetchAll();
    echo '<section class="box"><h2>الزوار جايين منين</h2><div class="table-wrap"><table class="table"><thead><tr><th>المصدر</th><th>التفاصيل</th><th>زيارات</th><th>زوار</th><th>متوسط الوقت</th><th>تواصل</th><th>التحويل</th></tr></thead><tbody>';
    foreach ($rows as $r) echo '<tr><td><a href="' . h($qs(['tab' => 'sessions', 'source' => $r['source_type']])) . '">' . h(SOURCE_TYPES[$r['source_type']] ?? $r['source_type']) . '</a></td><td>' . h($r['source_name']) . '</td><td>' . $r['n'] . '</td><td>' . $r['v'] . '</td><td>' . fmt_dur((int) $r['d']) . '</td><td>' . $r['c'] . '</td><td>' . pct($r['c'], $r['n']) . '</td></tr>';
    if (!$rows) echo '<tr><td colspan="7" class="muted">مفيش بيانات.</td></tr>';
    echo '</tbody></table></div></section>';
    echo '<div class="cols"><section class="box"><h2>المواقع اللي بتبعتلنا زوار</h2>'; bars($q("SELECT ref_domain, COUNT(*) n FROM sessions s WHERE $W AND ref_domain != '' GROUP BY ref_domain ORDER BY n DESC LIMIT 20", $A)->fetchAll(), 'ref_domain', 'n'); echo '</section>';
    echo '<section class="box"><h2>حملات UTM</h2>'; bars($q("SELECT utm_source || ' / ' || utm_medium || ' / ' || utm_campaign k, COUNT(*) n FROM sessions s WHERE $W AND utm_source != '' GROUP BY k ORDER BY n DESC LIMIT 20", $A)->fetchAll(), 'k', 'n'); echo '</section></div>';
    break;

/* ================= الدول والأجهزة ================= */
case 'countries':
    echo '<div class="cols"><section class="box"><h2>الدول</h2>'; bars($q("SELECT country, COUNT(*) n FROM sessions s WHERE $W GROUP BY country ORDER BY n DESC LIMIT 40", $A)->fetchAll(), 'country', 'n', 0, fn($r) => country_label($r['country']), fn($r) => $qs(['tab' => 'sessions', 'country' => $r['country'] ?: '--']));
    if (!is_file(geo_db_path()) && empty($_SERVER['HTTP_CF_IPCOUNTRY'])) echo '<p class="warn-text">قاعدة الدول مش متحملة لسه. حمّلها من تاب «إعدادات».</p>';
    echo '</section><section class="box"><h2>الأجهزة</h2>'; bars($q("SELECT device, COUNT(*) n FROM sessions s WHERE $W GROUP BY device ORDER BY n DESC", $A)->fetchAll(), 'device', 'n', 0, fn($r) => DEVICE_NAMES[$r['device']] ?? $r['device'], fn($r) => $qs(['tab' => 'sessions', 'device' => $r['device']]));
    echo '<h2>أنظمة التشغيل</h2>'; bars($q("SELECT os, COUNT(*) n FROM sessions s WHERE $W GROUP BY os ORDER BY n DESC", $A)->fetchAll(), 'os', 'n');
    echo '<h2>المتصفحات</h2>'; bars($q("SELECT browser, COUNT(*) n FROM sessions s WHERE $W GROUP BY browser ORDER BY n DESC", $A)->fetchAll(), 'browser', 'n');
    echo '<h2>لغة المتصفح</h2>'; bars($q("SELECT lang, COUNT(*) n FROM sessions s WHERE $W GROUP BY lang ORDER BY n DESC LIMIT 10", $A)->fetchAll(), 'lang', 'n');
    echo '</section></div>';
    break;

/* ================= الصفحات ================= */
case 'pages':
    $rows = $q("SELECT p.path, COUNT(*) n, COUNT(DISTINCT p.sid) u, AVG(NULLIF(p.ms,0)) ms FROM pageviews p JOIN sessions s ON s.sid = p.sid WHERE $W GROUP BY p.path ORDER BY n DESC LIMIT 200", $A)->fetchAll();
    echo '<section class="box"><h2>الصفحات</h2><div class="table-wrap"><table class="table"><thead><tr><th>الصفحة</th><th>مشاهدات</th><th>زيارات</th><th>متوسط وقت القراءة</th></tr></thead><tbody>';
    foreach ($rows as $r) echo '<tr><td><a href="' . h(rtrim(cfg('site_url'), '/') . $r['path']) . '" target="_blank" dir="ltr">' . h($r['path']) . '</a></td><td>' . $r['n'] . '</td><td>' . $r['u'] . '</td><td>' . ($r['ms'] ? fmt_dur((int) round($r['ms'] / 1000)) : '—') . '</td></tr>';
    if (!$rows) echo '<tr><td colspan="4" class="muted">مفيش بيانات.</td></tr>';
    echo '</tbody></table></div></section>';
    $rows = $q("SELECT landing, COUNT(*) n, SUM(CASE WHEN calls + whatsapps > 0 THEN 1 ELSE 0 END) c, SUM(CASE WHEN pageviews <= 1 AND duration < 10 THEN 1 ELSE 0 END) b FROM sessions s WHERE $W GROUP BY landing ORDER BY n DESC LIMIT 50", $A)->fetchAll();
    echo '<section class="box"><h2>صفحات الدخول: أنهي صفحة بتجيب مكالمات؟</h2><div class="table-wrap"><table class="table"><thead><tr><th>صفحة الدخول</th><th>زيارات</th><th>تواصل</th><th>التحويل</th><th>خروج سريع</th></tr></thead><tbody>';
    foreach ($rows as $r) echo '<tr><td dir="ltr">' . h($r['landing']) . '</td><td>' . $r['n'] . '</td><td>' . $r['c'] . '</td><td><strong>' . pct($r['c'], $r['n']) . '</strong></td><td>' . pct($r['b'], $r['n']) . '</td></tr>';
    echo '</tbody></table></div></section>';
    break;

/* ================= الضغطات ================= */
case 'clicks':
    echo '<div class="cols"><section class="box"><h2>نوع الضغطة</h2>'; bars($q("SELECT e.category, COUNT(*) n FROM events e JOIN sessions s ON s.sid = e.sid WHERE $W GROUP BY e.category ORDER BY n DESC", $A)->fetchAll(), 'category', 'n', 0, fn($r) => EVENT_NAMES[$r['category']] ?? $r['category']);
    echo '</section><section class="box"><h2>الصفحات اللي فيها اتصال وواتساب أكتر</h2>'; bars($q("SELECT e.path, COUNT(*) n FROM events e JOIN sessions s ON s.sid = e.sid WHERE $W AND e.category IN ('call','whatsapp') GROUP BY e.path ORDER BY n DESC LIMIT 15", $A)->fetchAll(), 'path', 'n'); echo '</section></div>';
    $rows = $q("SELECT e.category, e.label, e.href, COUNT(*) n, COUNT(DISTINCT e.sid) u FROM events e JOIN sessions s ON s.sid = e.sid WHERE $W GROUP BY e.category, e.label, e.href ORDER BY n DESC LIMIT 200", $A)->fetchAll();
    echo '<section class="box"><h2>كل الأزرار والروابط</h2><div class="table-wrap"><table class="table"><thead><tr><th>النوع</th><th>الزرار</th><th>الرابط</th><th>ضغطات</th><th>زيارات</th></tr></thead><tbody>';
    foreach ($rows as $r) echo '<tr><td>' . h(EVENT_NAMES[$r['category']] ?? $r['category']) . '</td><td>' . h($r['label']) . '</td><td dir="ltr" class="muted">' . h(mb_substr($r['href'], 0, 60)) . '</td><td>' . $r['n'] . '</td><td>' . $r['u'] . '</td></tr>';
    if (!$rows) echo '<tr><td colspan="5" class="muted">مفيش ضغطات في الفترة دي.</td></tr>';
    echo '</tbody></table></div></section>';
    break;

/* ================= آخر الزيارات ================= */
case 'sessions':
    $page = max(1, (int) ($_GET['page'] ?? 1)); $per = 50;
    $total = (int) $q("SELECT COUNT(*) FROM sessions s WHERE $W", $A)->fetchColumn();
    $rows = $q("SELECT s.*, (SELECT COUNT(*) FROM sessions x WHERE x.ip = s.ip AND s.ip != '') ipn FROM sessions s WHERE $W ORDER BY started_at DESC LIMIT $per OFFSET " . (($page - 1) * $per), $A)->fetchAll();
    echo '<p class="muted">' . number_format($total) . ' زيارة. <a href="' . h($qs(['export' => 'csv'])) . '">تصدير Excel (CSV)</a></p>';
    echo '<div class="table-wrap"><table class="table sessions-table"><thead><tr><th>الوقت</th><th>IP</th><th>الدولة</th><th>الجهاز</th><th>المصدر</th><th>صفحة الدخول</th><th>صفحات</th><th>المدة</th><th>عمل إيه</th><th></th></tr></thead><tbody>';
    foreach ($rows as $s) {
        $tags = [];
        if ($s['calls']) $tags[] = '<span class="pill pill-on">اتصال ' . $s['calls'] . '</span>';
        if ($s['whatsapps']) $tags[] = '<span class="pill pill-on">واتساب ' . $s['whatsapps'] . '</span>';
        if (!$s['new_visitor']) $tags[] = '<span class="pill">راجع</span>';
        if ($s['ipn'] > 1) $tags[] = '<a class="pill pill-warn" href="' . h($qs(['ip' => $s['ip'], 'page' => null])) . '">IP ده دخل ' . $s['ipn'] . ' مرات</a>';
        echo '<tr><td>' . date('m-d H:i', $s['started_at']) . '</td><td dir="ltr"><a href="' . h($qs(['ip' => $s['ip'], 'page' => null])) . '">' . h($s['ip']) . '</a></td><td>' . h(country_label($s['country'])) . '</td><td>' . h(DEVICE_NAMES[$s['device']] ?? $s['device']) . '<br><small class="muted">' . h($s['os'] . ' • ' . $s['browser']) . '</small></td><td>' . h(src_label($s)) . (($s['keyword'] ?: $s['utm_term']) ? '<br><small class="muted">«' . h($s['keyword'] ?: $s['utm_term']) . '»</small>' : '') . '</td><td dir="ltr">' . h($s['landing']) . '</td><td>' . $s['pageviews'] . '</td><td>' . fmt_dur((int) $s['duration']) . '</td><td>' . implode(' ', $tags) . '</td><td><a href="' . h($qs(['tab' => 'session', 'sid' => $s['sid']])) . '">التفاصيل</a></td></tr>';
    }
    if (!$rows) echo '<tr><td colspan="10" class="muted">مفيش زيارات بالفلاتر دي.</td></tr>';
    echo '</tbody></table></div>';
    $pages = (int) ceil($total / $per);
    if ($pages > 1) { echo '<nav class="pager">'; for ($i = max(1, $page - 4); $i <= min($pages, $page + 4); $i++) echo $i === $page ? "<strong>$i</strong>" : '<a href="' . h($qs(['page' => $i])) . '">' . $i . '</a>'; echo '</nav>'; }
    break;

/* ================= الزيارات المكررة ================= */
case 'ips':
    $rows = $q("SELECT ip, COUNT(*) n, COUNT(DISTINCT vid) v, MIN(started_at) f, MAX(started_at) l, SUM(is_ads) ads, SUM(calls) calls, SUM(whatsapps) was, SUM(pageviews) pv, SUM(duration) d, MAX(country) cc, MAX(device) dev
        FROM sessions s WHERE $W AND ip != '' GROUP BY ip HAVING n > 1 ORDER BY n DESC LIMIT 300", $A)->fetchAll();
    echo '<p class="muted">IPs دخلت الموقع أكتر من مرة في الفترة دي. ناس كتير ممكن يبقى ليهم نفس الـ IP (شبكة موبايل أو واي فاي واحد)، فـ«أجهزة مختلفة» بتوضح ده.</p>';
    echo '<div class="table-wrap"><table class="table"><thead><tr><th>IP</th><th>الدولة</th><th>عدد الزيارات</th><th>أجهزة مختلفة</th><th>من الإعلانات</th><th>اتصال / واتساب</th><th>صفحات</th><th>الوقت الكلي</th><th>أول زيارة</th><th>آخر زيارة</th></tr></thead><tbody>';
    foreach ($rows as $r) echo '<tr' . ($r['ads'] >= 3 && !($r['calls'] + $r['was']) ? ' class="row-bad"' : '') . '><td dir="ltr"><a href="' . h($qs(['tab' => 'sessions', 'ip' => $r['ip']])) . '">' . h($r['ip']) . '</a></td><td>' . h(country_label((string) $r['cc'])) . '</td><td><strong>' . $r['n'] . '</strong></td><td>' . $r['v'] . '</td><td>' . $r['ads'] . '</td><td>' . $r['calls'] . ' / ' . $r['was'] . '</td><td>' . $r['pv'] . '</td><td>' . fmt_dur((int) $r['d']) . '</td><td>' . date('m-d H:i', $r['f']) . '</td><td>' . date('m-d H:i', $r['l']) . '</td></tr>';
    if (!$rows) echo '<tr><td colspan="10" class="muted">مفيش IP دخل أكتر من مرة في الفترة دي.</td></tr>';
    echo '</tbody></table></div>';
    break;

/* ================= تفاصيل زيارة ================= */
case 'session':
    $s = $q('SELECT * FROM sessions WHERE sid = ?', [(string) ($_GET['sid'] ?? '')])->fetch();
    if (!$s) { echo '<p class="muted">الزيارة دي مش موجودة.</p>'; break; }
    $prev = (int) $q('SELECT COUNT(*) FROM sessions WHERE vid = ? AND started_at < ?', [$s['vid'], $s['started_at']])->fetchColumn();
    $ipn = (int) $q('SELECT COUNT(*) FROM sessions WHERE ip = ?', [$s['ip']])->fetchColumn();
    echo '<p><a href="' . h($qs(['tab' => 'sessions', 'sid' => null])) . '">← رجوع للزيارات</a></p><div class="cols"><section class="box"><h2>الزائر</h2><dl class="facts">';
    $fact = fn($k, $v) => print('<div><dt>' . h($k) . '</dt><dd>' . $v . '</dd></div>');
    $fact('بدأ', date('Y-m-d H:i:s', $s['started_at']));
    $fact('المدة', fmt_dur((int) $s['duration']) . ' • ' . $s['pageviews'] . ' صفحة');
    $fact('IP', '<span dir="ltr">' . h($s['ip']) . '</span> • <a href="' . h($qs(['tab' => 'sessions', 'ip' => $s['ip'], 'sid' => null])) . '">دخل ' . $ipn . ' مرات</a>');
    $fact('الدولة', h(country_label($s['country'])));
    $fact('الجهاز', h((DEVICE_NAMES[$s['device']] ?? $s['device']) . ' • ' . $s['os'] . ' • ' . $s['browser'] . ($s['screen'] ? ' • ' . $s['screen'] : '')));
    $fact('اللغة', h($s['lang']));
    $fact('زياراته قبل كده', $prev ? '<a href="' . h($qs(['tab' => 'sessions', 'vid' => $s['vid'], 'sid' => null, 'range' => '90d'])) . '">' . $prev . ' زيارة</a>' : 'أول مرة');
    echo '</dl></section><section class="box"><h2>جه منين</h2><dl class="facts">';
    $fact('المصدر', h(src_label($s)));
    if ($s['referrer']) $fact('الرابط اللي جه منه', '<span dir="ltr" class="break">' . h($s['referrer']) . '</span>');
    $fact('صفحة الدخول', '<span dir="ltr">' . h($s['landing']) . '</span>');
    $s['matchtype'] = ['e' => 'مطابقة تامة', 'p' => 'مطابقة عبارة', 'b' => 'مطابقة واسعة'][$s['matchtype']] ?? $s['matchtype'];
    $s['ad_device'] = ['m' => 'موبايل', 'c' => 'كمبيوتر', 't' => 'تابلت'][$s['ad_device']] ?? $s['ad_device'];
    $s['network'] = ['g' => 'بحث جوجل', 's' => 'شركاء البحث', 'd' => 'الشبكة الإعلانية', 'ytv' => 'يوتيوب', 'x' => 'Performance Max'][$s['network']] ?? $s['network'];
    foreach (['utm_campaign' => 'الحملة', 'campaign_id' => 'رقم الحملة', 'keyword' => 'الكلمة', 'utm_term' => 'utm_term', 'matchtype' => 'نوع المطابقة', 'adgroup_id' => 'مجموعة الإعلان', 'network' => 'الشبكة', 'ad_device' => 'جهاز الإعلان', 'gclid' => 'gclid'] as $k => $l) if ($s[$k] !== '') $fact($l, '<span dir="ltr" class="break">' . h($s[$k]) . '</span>');
    echo '</dl></section></div>';
    $tl = [];
    foreach ($q('SELECT * FROM pageviews WHERE sid = ? ORDER BY ts', [$s['sid']]) as $p) $tl[] = ['ts' => $p['ts'], 'html' => '<span class="tl-ic">📄</span> فتح <a href="' . h(rtrim(cfg('site_url'), '/') . $p['path']) . '" target="_blank" dir="ltr">' . h($p['path']) . '</a> <span class="muted">' . h($p['title']) . '</span>' . ($p['ms'] ? ' <span class="pill">قعد ' . fmt_dur((int) round($p['ms'] / 1000)) . '</span>' : '')];
    foreach ($q('SELECT * FROM events WHERE sid = ? ORDER BY ts', [$s['sid']]) as $e) $tl[] = ['ts' => $e['ts'], 'html' => '<span class="tl-ic">' . (['call' => '📞', 'whatsapp' => '💬'][$e['category']] ?? '👆') . '</span> ضغط <strong>' . h(EVENT_NAMES[$e['category']] ?? $e['category']) . '</strong>: ' . h($e['label']) . ' <span class="muted" dir="ltr">' . h(mb_substr($e['href'], 0, 60)) . '</span>', 'ev' => in_array($e['category'], ['call', 'whatsapp'], true)];
    usort($tl, fn($a, $b) => $a['ts'] <=> $b['ts']);
    echo '<section class="box"><h2>عمل إيه بالظبط</h2><ol class="timeline">';
    foreach ($tl as $i) echo '<li' . (!empty($i['ev']) ? ' class="tl-conv"' : '') . '><time>' . date('H:i:s', $i['ts']) . ' <small>(+' . fmt_dur($i['ts'] - $s['started_at']) . ')</small></time><div>' . $i['html'] . '</div></li>';
    echo '</ol></section>';
    break;

/* ================= إعدادات ================= */
case 'settings':
    $count = (int) $db->query('SELECT COUNT(*) FROM sessions')->fetchColumn();
    $size = is_file(dirname((string) cfg('db_path')) . '/stats.sqlite') ? round(filesize(dirname((string) cfg('db_path')) . '/stats.sqlite') / 1048576, 1) : 0;
    ?>
    <div class="cols">
    <form method="post" class="box">
      <?= csrf_field() ?><input type="hidden" name="action" value="settings">
      <h2>الإعدادات</h2>
      <label>مدة الاحتفاظ بالبيانات (يوم) <span class="hint">البيانات الأقدم بتتمسح لوحدها. الافتراضي 180 يوم</span><input type="number" name="retention" min="7" max="1095" value="<?= (int) setting('stats_retention_days', '180') ?>"></label>
      <label>IPs متتحسبش <span class="hint">IPs بتاعتك أو بتاعة الشغل، مفصولة بفاصلة</span><textarea name="exclude_ips" rows="3" dir="ltr"><?= h(setting('stats_exclude_ips')) ?></textarea></label>
      <button class="btn">احفظ</button>
    </form>
    <section class="box">
      <h2>استبعد زياراتك إنت</h2>
      <p>عشان زياراتك متبوظش الأرقام:</p>
      <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="add_my_ip"><button class="btn btn-ghost">ضيف الـ IP بتاعي الحالي (<?= h(client_ip()) ?>)</button></form>
      <p>أو افتح الرابط ده مرة واحدة من كل جهاز وموبايل بتستخدمه، والجهاز ده مش هيتحسب تاني حتى لو الـ IP اتغير:</p>
      <p><a href="<?= h(rtrim(cfg('site_url'), '/')) ?>/?wt_ignore=1" target="_blank" dir="ltr"><?= h(rtrim(cfg('site_url'), '/')) ?>/?wt_ignore=1</a></p>
      <p class="muted">وعشان ترجّع الجهاز يتحسب: <span dir="ltr"><?= h(rtrim(cfg('site_url'), '/')) ?>/?wt_ignore=0</span></p>
    </section>
    <section class="box">
      <h2>قاعدة الدول</h2>
      <p>الدولة بتتعرف من الـ IP باستخدام قاعدة DB-IP Lite المجانية، ودي بتتحدث كل شهر.</p>
      <p class="muted">الحالة: <?= is_file(geo_db_path()) ? 'متحملة ' . h(setting('_geo_updated')) : '<strong class="warn-text">مش متحملة لسه</strong>' ?></p>
      <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="geo"><button class="btn">حمّل / حدّث قاعدة الدول</button></form>
      <p class="hint">بياخد دقيقة أو اتنين. <a href="https://db-ip.com" target="_blank" rel="noopener">IP Geolocation by DB-IP</a> (CC BY 4.0)</p>
    </section>
    <section class="box">
      <h2>البيانات</h2>
      <p class="muted"><?= number_format($count) ?> زيارة متسجلة • حجم القاعدة <?= $size ?> ميجا</p>
      <p class="muted">الإحصائيات دي فيها IPs، ودي بيانات شخصية. يفضّل تضيف سطر في صفحة «مين إحنا» أو صفحة خصوصية إن الموقع بيسجل الزيارات عشان يحسّن الخدمة.</p>
      <form method="post" data-confirm="هتمسح كل الإحصائيات نهائياً. متأكد؟"><?= csrf_field() ?><input type="hidden" name="action" value="purge"><button class="link-danger">امسح كل الإحصائيات</button></form>
    </section>
    </div>
    <?php
    break;
endswitch;
layout_end();
