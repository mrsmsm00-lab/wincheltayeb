<?php
// جامع الإحصائيات: الموقع بيبعت هنا الزيارات والضغطات ووقت البقاء
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/stats.php';

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$site = rtrim((string) cfg('site_url'), '/');
$allowed = array_unique(array_merge((array) cfg('allowed_origins'), [$site, preg_replace('~^(https?://)~', '$1www.', $site)]));
if (in_array($origin, $allowed, true)) { header('Access-Control-Allow-Origin: ' . $origin); header('Vary: Origin'); header('Access-Control-Allow-Headers: Content-Type'); }
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !is_installed()) { http_response_code(204); exit; }
if ($origin !== '' && !in_array($origin, $allowed, true)) { http_response_code(204); exit; }

$raw = (string) file_get_contents('php://input', false, null, 0, 8192);
$d = json_decode($raw, true);
$done = function () { http_response_code(204); exit; };
if (!is_array($d)) $done();
$id = fn($k) => preg_match('/^[a-z0-9]{8,40}$/', (string) ($d[$k] ?? '')) ? (string) $d[$k] : '';
$t = (string) ($d['t'] ?? ''); $vid = $id('vid'); $sid = $id('sid'); $pvid = $id('pvid');
if (!$vid || !$sid || !in_array($t, ['pv', 'hb', 'leave', 'ev'], true)) $done();

$ua = mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 400);
$uaInfo = parse_ua($ua);
if ($uaInfo['bot']) $done();
$ip = (string) ($_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? '');
if ($ip !== '' && in_array($ip, array_filter(array_map('trim', explode(',', setting('stats_exclude_ips')))), true)) $done();

$db = stats_db();
$now = time();
$s = $db->prepare('SELECT started_at, pageviews FROM sessions WHERE sid = ?'); $s->execute([$sid]);
$sess = $s->fetch();
$cut = fn($v, $n) => mb_substr(trim((string) $v), 0, $n);
$path = $cut(parse_url((string) ($d['path'] ?? '/'), PHP_URL_PATH) ?: '/', 300);

if ($t === 'pv') {
    if (!$sess) {
        $url = (string) ($d['url'] ?? '');
        parse_str((string) parse_url($url, PHP_URL_QUERY), $q);
        $q = array_map(fn($v) => is_string($v) ? mb_substr($v, 0, 200) : '', $q);
        $ref = $cut($d['ref'] ?? '', 500);
        [$stype, $sname, $refDomain, $isAds] = classify_source($ref, $q, (string) parse_url($site, PHP_URL_HOST));
        if ($stype === 'internal') { $stype = 'direct'; $sname = 'مباشر'; }
        $ret = $db->prepare('SELECT 1 FROM sessions WHERE vid = ? LIMIT 1'); $ret->execute([$vid]);
        $db->prepare('INSERT OR IGNORE INTO sessions (sid, vid, ip, country, ua, device, os, browser, lang, screen, started_at, last_at, pageviews, landing, referrer, ref_domain,
            source_type, source_name, utm_source, utm_medium, utm_campaign, utm_term, utm_content, gclid, campaign_id, adgroup_id, keyword, matchtype, ad_device, network, is_ads, new_visitor)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,1,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)')->execute([
            $sid, $vid, $ip, geo_country($ip), $ua, $uaInfo['device'], $uaInfo['os'], $uaInfo['browser'], $cut($d['lang'] ?? '', 20), $cut($d['screen'] ?? '', 20),
            $now, $now, $path, $ref, $refDomain, $stype, $cut($sname, 100),
            $q['utm_source'] ?? '', $q['utm_medium'] ?? '', $q['utm_campaign'] ?? '', $q['utm_term'] ?? '', $q['utm_content'] ?? '',
            $q['gclid'] ?? ($q['gbraid'] ?? ($q['wbraid'] ?? '')), $q['campaignid'] ?? ($q['campaign_id'] ?? ''), $q['adgroupid'] ?? ($q['adgroup'] ?? ''),
            $q['keyword'] ?? ($q['utm_term'] ?? ''), $q['matchtype'] ?? '', $q['device'] ?? '', $q['network'] ?? '', $isAds ? 1 : 0, $ret->fetch() ? 0 : 1,
        ]);
    } else {
        if ($sess['pageviews'] > 300) $done();
        $db->prepare('UPDATE sessions SET pageviews = pageviews + 1, last_at = ?, duration = ? WHERE sid = ?')->execute([$now, min(14400, $now - (int) $sess['started_at']), $sid]);
    }
    if ($pvid) $db->prepare('INSERT OR IGNORE INTO pageviews (pvid, sid, path, title, ts) VALUES (?, ?, ?, ?, ?)')->execute([$pvid, $sid, $path, $cut($d['title'] ?? '', 200), $now]);
    $done();
}

if (!$sess) $done();
$db->prepare('UPDATE sessions SET last_at = ?, duration = ? WHERE sid = ?')->execute([$now, min(14400, $now - (int) $sess['started_at']), $sid]);

if (($t === 'hb' || $t === 'leave') && $pvid) {
    $ms = max(0, min(4 * 3600 * 1000, (int) ($d['ms'] ?? 0)));
    $db->prepare('UPDATE pageviews SET ms = MAX(ms, CAST(? AS INTEGER)) WHERE pvid = ? AND sid = ?')->execute([$ms, $pvid, $sid]);
}
if ($t === 'ev') {
    $cat = in_array($d['cat'] ?? '', ['call', 'whatsapp', 'cta', 'nav', 'outbound', 'link', 'form'], true) ? $d['cat'] : 'link';
    $n = $db->prepare('SELECT COUNT(*) FROM events WHERE sid = ?'); $n->execute([$sid]);
    if ((int) $n->fetchColumn() > 500) $done();
    $db->prepare('INSERT INTO events (sid, ts, category, label, href, path) VALUES (?, ?, ?, ?, ?, ?)')->execute([$sid, $now, $cat, $cut($d['label'] ?? '', 120), $cut($d['href'] ?? '', 300), $path]);
    if ($cat === 'call') $db->prepare('UPDATE sessions SET calls = calls + 1 WHERE sid = ?')->execute([$sid]);
    if ($cat === 'whatsapp') $db->prepare('UPDATE sessions SET whatsapps = whatsapps + 1 WHERE sid = ?')->execute([$sid]);
}
$done();
