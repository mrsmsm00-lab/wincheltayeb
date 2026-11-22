<?php

// يبعت إيميل لصاحب الموقع أول ما نفس التليفون يضغط إعلان جوجل 3 مرات من غير ما يتواصل.
// بيتشغّل من الكرون كل كام دقيقة. إيميل واحد لكل تليفون، وبيعيد لو زوّد 3 ضغطات تانية. CLI بس.

declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }

require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/stats.php';

const ALERT_TO = 'mr.smsm0.0@gmail.com';
const ALERT_FROM = 'no-reply@wincheltayeb.com';
const ALERT_FROM_NAME = 'ونش الطيب';
const ALERT_THRESHOLD = 3;        // عدد ضغطات الإعلان اللي تبعت أول إيميل
const ALERT_WINDOW_DAYS = 14;     // منحسب الضغطات في المدة دي بس
const ALERT_ONLY_NO_CONTACT = true;

// التليفون = البصمة المبدئية (نوع الجهاز والنظام والمتصفح والشاشة واللغة)، وإلا رقم الزائر، وإلا الـ IP.
const PHONE_KEY_SQL = "CASE WHEN sig <> '' THEN 's'||sig WHEN vid <> '' THEN 'v'||vid ELSE 'i'||ip END";

function esc_h(?string $s): string { return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

/** التليفونات اللي ضغطت الإعلان ALERT_THRESHOLD+ مرة وتستاهل إيميل جديد. */
function phones_to_alert(PDO $db): array
{
    $since = time() - ALERT_WINDOW_DAYS * 86400;
    $having = ALERT_ONLY_NO_CONTACT ? 'HAVING ad_clicks >= ? AND SUM(calls + whatsapps) = 0' : 'HAVING ad_clicks >= ?';
    $st = $db->prepare('SELECT ' . PHONE_KEY_SQL . ' pkey, COUNT(*) ad_clicks
                        FROM sessions WHERE is_ads = 1 AND started_at >= ?
                        GROUP BY pkey ' . $having);
    $st->execute([$since, ALERT_THRESHOLD]);
    $rows = $st->fetchAll();
    if (!$rows) { return []; }
    $seen = [];
    foreach ($db->query('SELECT pkey, alerted_clicks FROM phone_alerts') as $r) { $seen[$r['pkey']] = (int) $r['alerted_clicks']; }
    $due = [];
    foreach ($rows as $r) {
        $prev = $seen[$r['pkey']] ?? null;
        if ($prev === null || (int) $r['ad_clicks'] >= $prev + ALERT_THRESHOLD) { $due[] = $r; }
    }
    return $due;
}

/** كل زيارات تليفون واحد، الأحدث الأول. */
function phone_sessions(PDO $db, string $pkey): array
{
    $st = $db->prepare('SELECT * FROM sessions WHERE ' . PHONE_KEY_SQL . ' = ? ORDER BY started_at DESC LIMIT 60');
    $st->execute([$pkey]);
    return $st->fetchAll();
}

function build_report(array $sessions): array
{
    $latest = $sessions[0];
    $adSessions = array_values(array_filter($sessions, fn ($s) => (int) $s['is_ads'] === 1));
    $adClicks = count($adSessions);
    $calls = array_sum(array_map('intval', array_column($sessions, 'calls')));
    $was = array_sum(array_map('intval', array_column($sessions, 'whatsapps')));

    $dev = DEVICE_NAMES[$latest['device']] ?? $latest['device'];
    $phone = trim($dev . ' • ' . $latest['os'] . ' • ' . $latest['browser']);
    $ips = array_values(array_unique(array_filter(array_column($sessions, 'ip'))));
    $screens = array_values(array_unique(array_filter(array_column($sessions, 'screen'))));
    $terms = array_values(array_unique(array_filter(array_map(fn ($s) => $s['keyword'] ?: $s['utm_term'], $adSessions))));
    $countries = array_values(array_unique(array_filter(array_column($sessions, 'country'))));

    $contactLine = $calls + $was
        ? "<b style='color:#1fae52'>اتواصل:</b> " . ($calls ? "$calls اتصال " : '') . ($was ? "$was واتساب" : '')
        : "<b style='color:#d0101e'>⚠️ ضغط الإعلان $adClicks مرات ومتصلش ولا كلّم</b>";

    $h = "<div style='font-family:Tahoma,Arial,sans-serif;direction:rtl;text-align:right;max-width:640px;margin:auto;color:#1a1a1a'>";
    $h .= "<h2 style='background:#0d1b3e;color:#fff;padding:12px 16px;border-radius:8px;margin:0 0 12px'>🚨 نفس التليفون ضغط إعلان ونش الطيب $adClicks مرات</h2>";
    $h .= "<table style='width:100%;border-collapse:collapse;font-size:14px'>";
    $row = fn ($k, $v) => "<tr><td style='padding:7px 10px;border-bottom:1px solid #eee;color:#666;white-space:nowrap'>$k</td><td style='padding:7px 10px;border-bottom:1px solid #eee'><b>$v</b></td></tr>";
    $h .= $row('📱 الجهاز', esc_h($phone));
    $h .= $row('الشاشة', esc_h(implode(' ، ', $screens) ?: '—'));
    $h .= $row('الدولة', esc_h(implode(' ، ', $countries) ?: '—'));
    $h .= $row('كلمات البحث', esc_h(implode(' | ', $terms) ?: '—'));
    $h .= $row('ضغطات الإعلان', $adClicks . ' مرة (من إجمالي ' . count($sessions) . ' زيارة)');
    $h .= "<tr><td style='padding:7px 10px;border-bottom:1px solid #eee;color:#666'>التواصل</td><td style='padding:7px 10px;border-bottom:1px solid #eee'>$contactLine</td></tr>";
    $h .= $row('عناوين IP', esc_h(implode('، ', $ips) ?: '—'));
    $h .= '</table>';

    $h .= "<h3 style='margin:18px 0 8px'>الزيارات</h3><table style='width:100%;border-collapse:collapse;font-size:13px'>";
    $h .= "<tr style='background:#f3f4f6'><th style='padding:6px;text-align:right'>الوقت</th><th style='padding:6px;text-align:right'>جاي منين</th><th style='padding:6px;text-align:right'>IP</th><th style='padding:6px;text-align:right'>عمل إيه</th></tr>";
    foreach (array_slice($sessions, 0, 25) as $s) {
        $from = (int) $s['is_ads'] === 1 ? '🟢 إعلان' . ($s['keyword'] ? ' — ' . esc_h($s['keyword']) : '') : esc_h($s['source_name'] ?: $s['source_type']);
        $did = [];
        if ((int) $s['calls']) { $did[] = "<span style='color:#d0101e'>📞×" . (int) $s['calls'] . '</span>'; }
        if ((int) $s['whatsapps']) { $did[] = "<span style='color:#1fae52'>💬×" . (int) $s['whatsapps'] . '</span>'; }
        if (!$did) { $did[] = "<span style='color:#999'>مضغطش</span>"; }
        $h .= '<tr><td style="padding:6px;border-bottom:1px solid #f0f0f0">' . esc_h(date('m-d H:i', (int) $s['started_at'])) . '</td>'
            . "<td style='padding:6px;border-bottom:1px solid #f0f0f0'>$from</td>"
            . "<td style='padding:6px;border-bottom:1px solid #f0f0f0' dir='ltr'>" . esc_h($s['ip']) . '</td>'
            . "<td style='padding:6px;border-bottom:1px solid #f0f0f0'>" . implode(' ', $did) . '</td></tr>';
    }
    $h .= '</table>';
    $h .= "<p style='margin:18px 0 6px'><a href='" . esc_h((string) cfg('admin_url')) . "/stats.php' style='background:#0d1b3e;color:#fff;padding:10px 18px;border-radius:6px;text-decoration:none'>افتح الإحصائيات</a></p>";
    $h .= "<p style='color:#999;font-size:12px'>التعرّف على التليفون بصمة مبدئية (نوع الجهاز والنظام والمتصفح والشاشة واللغة) مش إثبات قاطع — راجع المكان والوقت.</p></div>";

    return ['تنبيه: تليفون ضغط إعلان ونش الطيب ' . $adClicks . ' مرات', $h];
}

function send_alert(string $subject, string $html): bool
{
    $body = rtrim(chunk_split(base64_encode($html), 72, "\r\n"));
    $headers = 'From: ' . mb_encode_mimeheader(ALERT_FROM_NAME, 'UTF-8') . ' <' . ALERT_FROM . ">\r\n"
        . "MIME-Version: 1.0\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\nX-Mailer: tayeb-alerts";
    return mail(ALERT_TO, '=?UTF-8?B?' . base64_encode($subject) . '?=', $body, $headers, '-f ' . ALERT_FROM);
}

try {
    $db = stats_db();
    $due = phones_to_alert($db);
    $sent = 0;
    foreach ($due as $p) {
        $sessions = phone_sessions($db, $p['pkey']);
        if (!$sessions) { continue; }
        [$subject, $html] = build_report($sessions);
        if (send_alert($subject, $html)) {
            $up = $db->prepare('INSERT INTO phone_alerts (pkey, alerted_clicks, first_at, last_at) VALUES (?, ?, ?, ?)
                ON CONFLICT(pkey) DO UPDATE SET alerted_clicks = excluded.alerted_clicks, emails = emails + 1, last_at = excluded.last_at');
            $up->execute([$p['pkey'], (int) $p['ad_clicks'], time(), time()]);
            $sent++;
        }
    }
    if (in_array('-v', $argv, true)) { echo 'due ' . count($due) . ', sent ' . $sent . "\n"; }
} catch (Throwable $e) {
    error_log('tayeb alerts.php: ' . $e->getMessage());
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}
