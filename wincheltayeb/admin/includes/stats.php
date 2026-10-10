<?php
// إحصائيات الموقع: قاعدة بيانات منفصلة (data/stats.sqlite) عشان متتقلش على المحتوى

function stats_db(): PDO {
    static $pdo = null;
    if ($pdo) return $pdo;
    $path = dirname((string) cfg('db_path')) . '/stats.sqlite';
    $pdo = new PDO('sqlite:' . $path, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
    $pdo->exec('PRAGMA journal_mode = WAL; PRAGMA synchronous = NORMAL; PRAGMA busy_timeout = 5000;');
    $pdo->exec("
    CREATE TABLE IF NOT EXISTS sessions (
        sid TEXT PRIMARY KEY, vid TEXT NOT NULL, ip TEXT NOT NULL DEFAULT '', country TEXT NOT NULL DEFAULT '',
        ua TEXT NOT NULL DEFAULT '', device TEXT NOT NULL DEFAULT '', os TEXT NOT NULL DEFAULT '', browser TEXT NOT NULL DEFAULT '',
        lang TEXT NOT NULL DEFAULT '', screen TEXT NOT NULL DEFAULT '',
        started_at INTEGER NOT NULL, last_at INTEGER NOT NULL, duration INTEGER NOT NULL DEFAULT 0, pageviews INTEGER NOT NULL DEFAULT 0,
        landing TEXT NOT NULL DEFAULT '', referrer TEXT NOT NULL DEFAULT '', ref_domain TEXT NOT NULL DEFAULT '',
        source_type TEXT NOT NULL DEFAULT 'direct', source_name TEXT NOT NULL DEFAULT '',
        utm_source TEXT NOT NULL DEFAULT '', utm_medium TEXT NOT NULL DEFAULT '', utm_campaign TEXT NOT NULL DEFAULT '',
        utm_term TEXT NOT NULL DEFAULT '', utm_content TEXT NOT NULL DEFAULT '', gclid TEXT NOT NULL DEFAULT '',
        campaign_id TEXT NOT NULL DEFAULT '', adgroup_id TEXT NOT NULL DEFAULT '', keyword TEXT NOT NULL DEFAULT '',
        matchtype TEXT NOT NULL DEFAULT '', ad_device TEXT NOT NULL DEFAULT '', network TEXT NOT NULL DEFAULT '',
        is_ads INTEGER NOT NULL DEFAULT 0, calls INTEGER NOT NULL DEFAULT 0, whatsapps INTEGER NOT NULL DEFAULT 0, new_visitor INTEGER NOT NULL DEFAULT 1
    );
    CREATE INDEX IF NOT EXISTS s_started ON sessions(started_at);
    CREATE INDEX IF NOT EXISTS s_ip ON sessions(ip);
    CREATE INDEX IF NOT EXISTS s_vid ON sessions(vid);
    CREATE INDEX IF NOT EXISTS s_ads ON sessions(is_ads, started_at);
    CREATE TABLE IF NOT EXISTS pageviews (
        pvid TEXT PRIMARY KEY, sid TEXT NOT NULL, path TEXT NOT NULL, title TEXT NOT NULL DEFAULT '', ts INTEGER NOT NULL, ms INTEGER NOT NULL DEFAULT 0
    );
    CREATE INDEX IF NOT EXISTS p_sid ON pageviews(sid, ts);
    CREATE INDEX IF NOT EXISTS p_ts ON pageviews(ts);
    CREATE TABLE IF NOT EXISTS events (
        id INTEGER PRIMARY KEY AUTOINCREMENT, sid TEXT NOT NULL, ts INTEGER NOT NULL, category TEXT NOT NULL,
        label TEXT NOT NULL DEFAULT '', href TEXT NOT NULL DEFAULT '', path TEXT NOT NULL DEFAULT ''
    );
    CREATE INDEX IF NOT EXISTS e_sid ON events(sid, ts);
    CREATE INDEX IF NOT EXISTS e_ts ON events(ts, category);
    ");
    return $pdo;
}

/* ---------- تصنيف المصادر ---------- */
const SOURCE_TYPES = [
    'ads' => 'إعلانات جوجل', 'paid' => 'إعلانات تانية', 'search' => 'بحث طبيعي', 'social' => 'سوشيال ميديا',
    'ai' => 'ذكاء اصطناعي', 'referral' => 'مواقع تانية', 'direct' => 'مباشر', 'internal' => 'داخلي',
];

function classify_source(string $referrer, array $q, string $siteHost): array {
    $refHost = strtolower((string) parse_url($referrer, PHP_URL_HOST));
    $refHost = preg_replace('/^www\./', '', $refHost);
    $src = strtolower($q['utm_source'] ?? ''); $med = strtolower($q['utm_medium'] ?? '');
    $isGoogleAds = !empty($q['gclid']) || !empty($q['gbraid']) || !empty($q['wbraid']) || ($src === 'google' && in_array($med, ['cpc', 'ppc', 'paid', 'paidsearch'], true));
    if ($isGoogleAds) return ['ads', 'Google Ads', $refHost, true];
    if (in_array($med, ['cpc', 'ppc', 'paid', 'paid_social', 'paidsocial', 'display', 'cpm'], true) || !empty($q['fbclid']) && $med !== '') return ['paid', $src ?: $refHost ?: 'إعلان', $refHost, false];
    if ($src !== '') return [preg_match('/facebook|instagram|tiktok|twitter|x\.com|snapchat|whatsapp|youtube|linkedin/', $src) ? 'social' : 'referral', $src, $refHost, false];
    if ($refHost === '' ) return ['direct', 'مباشر', '', false];
    $site = preg_replace('/^www\./', '', strtolower($siteHost));
    if ($refHost === $site || str_ends_with($refHost, '.' . $site)) return ['internal', $refHost, $refHost, false];
    $map = [
        'search' => ['/(^|\.)google\./' => 'Google', '/(^|\.)bing\.com$/' => 'Bing', '/yahoo\./' => 'Yahoo', '/duckduckgo\.com$/' => 'DuckDuckGo', '/yandex\./' => 'Yandex', '/ecosia\.org$/' => 'Ecosia', '/baidu\.com$/' => 'Baidu'],
        'social' => ['/facebook\.com$|fb\.me$|l\.facebook\.com$|m\.facebook\.com$/' => 'Facebook', '/instagram\.com$/' => 'Instagram', '/tiktok\.com$/' => 'TikTok', '/(^|\.)t\.co$|twitter\.com$|x\.com$/' => 'X', '/whatsapp\.com$|wa\.me$/' => 'WhatsApp', '/youtube\.com$|youtu\.be$/' => 'YouTube', '/linkedin\.com$|lnkd\.in$/' => 'LinkedIn', '/snapchat\.com$/' => 'Snapchat', '/t\.me$|telegram\./' => 'Telegram'],
        'ai' => ['/chatgpt\.com$|openai\.com$/' => 'ChatGPT', '/perplexity\.ai$/' => 'Perplexity', '/gemini\.google\.com$/' => 'Gemini', '/copilot\.microsoft\.com$/' => 'Copilot', '/claude\.ai$/' => 'Claude'],
    ];
    foreach (['ai', 'search', 'social'] as $type) foreach ($map[$type] as $re => $name) if (preg_match($re, $refHost)) return [$type, $name, $refHost, false];
    return ['referral', $refHost, $refHost, false];
}

/* ---------- الجهاز والمتصفح ---------- */
function parse_ua(string $ua): array {
    $bot = (bool) preg_match('/bot|crawl|spider|slurp|headless|lighthouse|pagespeed|preview|facebookexternalhit|whatsapp\/|curl|wget|python|httpclient|java\/|go-http|monitor|uptime|scan/i', $ua);
    $device = preg_match('/ipad|tablet|(android(?!.*mobile))/i', $ua) ? 'tablet' : (preg_match('/mobi|iphone|ipod|android/i', $ua) ? 'mobile' : 'desktop');
    $os = match (true) {
        (bool) preg_match('/iphone|ipad|ipod/i', $ua) => 'iOS', (bool) preg_match('/android/i', $ua) => 'Android',
        (bool) preg_match('/windows/i', $ua) => 'Windows', (bool) preg_match('/mac os x/i', $ua) => 'macOS',
        (bool) preg_match('/cros/i', $ua) => 'ChromeOS', (bool) preg_match('/linux/i', $ua) => 'Linux', default => 'تاني',
    };
    $browser = match (true) {
        (bool) preg_match('/edg\//i', $ua) => 'Edge', (bool) preg_match('/opr\/|opera/i', $ua) => 'Opera',
        (bool) preg_match('/samsungbrowser/i', $ua) => 'Samsung', (bool) preg_match('/fban|fbav|instagram/i', $ua) => 'متصفح فيسبوك/إنستجرام',
        (bool) preg_match('/firefox|fxios/i', $ua) => 'Firefox', (bool) preg_match('/chrome|crios/i', $ua) => 'Chrome',
        (bool) preg_match('/safari/i', $ua) => 'Safari', default => 'تاني',
    };
    return ['bot' => $bot, 'device' => $device, 'os' => $os, 'browser' => $browser];
}
const DEVICE_NAMES = ['mobile' => 'موبايل', 'desktop' => 'كمبيوتر', 'tablet' => 'تابلت'];

/* ---------- الدول (DB-IP Lite، رخصة CC BY 4.0) ---------- */
function geo_db_path(): string { return dirname((string) cfg('db_path')) . '/geo.sqlite'; }
function ip_bin(string $ip): ?string {
    $b = @inet_pton($ip);
    if ($b === false) return null;
    return strlen($b) === 4 ? str_repeat("\0", 10) . "\xff\xff" . $b : $b;
}
function geo_country(string $ip): string {
    if (!empty($_SERVER['HTTP_CF_IPCOUNTRY']) && preg_match('/^[A-Z]{2}$/', $_SERVER['HTTP_CF_IPCOUNTRY'])) return $_SERVER['HTTP_CF_IPCOUNTRY'];
    static $g = false;
    if ($g === false) { $g = is_file(geo_db_path()) ? new PDO('sqlite:' . geo_db_path(), null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]) : null; }
    $bin = ip_bin($ip);
    if (!$g || $bin === null) return '';
    $st = $g->prepare('SELECT cc, ip_end FROM ranges WHERE ip_start <= ? ORDER BY ip_start DESC LIMIT 1');
    $st->bindValue(1, $bin, PDO::PARAM_LOB);
    $st->execute();
    $r = $st->fetch(PDO::FETCH_ASSOC);
    return $r && strcmp($r['ip_end'], $bin) >= 0 ? (string) $r['cc'] : '';
}

/** بيحمّل أحدث قاعدة دول من DB-IP ويبنيها (بياخد دقيقة تقريباً) */
function geo_update(): array {
    @set_time_limit(600);
    $tmp = dirname(geo_db_path()) . '/geo-download.csv.gz';
    $ok = false; $used = '';
    foreach ([0, 1] as $back) {
        $ym = date('Y-m', strtotime("first day of -$back month"));
        $url = "https://download.db-ip.com/free/dbip-country-lite-$ym.csv.gz";
        $fp = fopen($tmp, 'w');
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_FILE => $fp, CURLOPT_TIMEOUT => 300, CURLOPT_FOLLOWLOCATION => true, CURLOPT_USERAGENT => 'WinchElTayebAdmin/1.0']);
        curl_exec($ch); $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch); fclose($fp);
        if ($code === 200 && filesize($tmp) > 100000) { $ok = true; $used = $ym; break; }
    }
    if (!$ok) { @unlink($tmp); return ['ok' => false, 'msg' => 'مقدرناش نحمّل قاعدة الدول من DB-IP. جرب تاني بعد شوية.']; }
    $new = geo_db_path() . '.new';
    @unlink($new);
    $g = new PDO('sqlite:' . $new, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $g->exec('PRAGMA journal_mode = OFF; PRAGMA synchronous = OFF; CREATE TABLE ranges (ip_start BLOB NOT NULL, ip_end BLOB NOT NULL, cc TEXT NOT NULL);');
    $g->beginTransaction();
    $ins = $g->prepare('INSERT INTO ranges VALUES (?, ?, ?)');
    $gz = gzopen($tmp, 'r'); $n = 0;
    while (($line = gzgets($gz)) !== false) {
        $p = str_getcsv(trim($line));
        if (count($p) < 3 || $p[2] === 'ZZ') continue;
        $a = ip_bin($p[0]); $b = ip_bin($p[1]);
        if ($a === null || $b === null) continue;
        $ins->bindValue(1, $a, PDO::PARAM_LOB); $ins->bindValue(2, $b, PDO::PARAM_LOB); $ins->bindValue(3, $p[2]);
        $ins->execute(); $n++;
    }
    gzclose($gz);
    $g->commit();
    $g->exec('CREATE INDEX r_start ON ranges(ip_start)');
    $g = null;
    rename($new, geo_db_path());
    @unlink($tmp);
    set_setting('_geo_updated', "$used ($n نطاق)");
    return ['ok' => true, 'msg' => "اتحمّلت قاعدة الدول ($used) وفيها $n نطاق."];
}

const COUNTRY_AR = [
    'EG' => 'مصر', 'SA' => 'السعودية', 'AE' => 'الإمارات', 'KW' => 'الكويت', 'QA' => 'قطر', 'BH' => 'البحرين', 'OM' => 'عُمان',
    'JO' => 'الأردن', 'LB' => 'لبنان', 'SY' => 'سوريا', 'IQ' => 'العراق', 'PS' => 'فلسطين', 'LY' => 'ليبيا', 'SD' => 'السودان',
    'TN' => 'تونس', 'DZ' => 'الجزائر', 'MA' => 'المغرب', 'YE' => 'اليمن', 'TR' => 'تركيا', 'US' => 'أمريكا', 'GB' => 'بريطانيا',
    'DE' => 'ألمانيا', 'FR' => 'فرنسا', 'IT' => 'إيطاليا', 'NL' => 'هولندا', 'CA' => 'كندا', 'RU' => 'روسيا', 'CN' => 'الصين',
    'IN' => 'الهند', 'IE' => 'أيرلندا', 'SG' => 'سنغافورة', 'FI' => 'فنلندا', 'SE' => 'السويد', 'ES' => 'إسبانيا', 'AU' => 'أستراليا',
];
function country_label(string $cc): string {
    if ($cc === '') return 'مش معروف';
    $flag = mb_chr(0x1F1E6 + ord($cc[0]) - 65) . mb_chr(0x1F1E6 + ord($cc[1]) - 65);
    return $flag . ' ' . (COUNTRY_AR[$cc] ?? $cc);
}

/* ---------- فلاتر التقارير ---------- */
function stats_range(): array {
    $r = $_GET['range'] ?? '7d';
    $today = strtotime('today');
    [$from, $to] = match ($r) {
        'today' => [$today, time()],
        'yesterday' => [$today - 86400, $today - 1],
        '30d' => [$today - 29 * 86400, time()],
        '90d' => [$today - 89 * 86400, time()],
        'custom' => [strtotime((string) ($_GET['from'] ?? '')) ?: $today - 6 * 86400, (strtotime((string) ($_GET['to'] ?? '')) ?: $today) + 86399],
        default => [$today - 6 * 86400, time()],
    };
    return [$from, $to, $r];
}

/** شروط WHERE من الفلاتر المختارة */
function stats_where(array $extra = []): array {
    [$from, $to] = stats_range();
    $w = ['s.started_at BETWEEN ? AND ?']; $a = [$from, $to];
    $f = array_merge($_GET, $extra);
    if (!empty($f['source'])) { $w[] = 's.source_type = ?'; $a[] = $f['source']; }
    if (!empty($f['country'])) { $w[] = 's.country = ?'; $a[] = $f['country'] === '--' ? '' : $f['country']; }
    if (!empty($f['device'])) { $w[] = 's.device = ?'; $a[] = $f['device']; }
    if (!empty($f['ip'])) { $w[] = 's.ip = ?'; $a[] = $f['ip']; }
    if (!empty($f['vid'])) { $w[] = 's.vid = ?'; $a[] = $f['vid']; }
    if (!empty($f['campaign'])) { $w[] = '(s.utm_campaign = ? OR s.campaign_id = ?)'; $a[] = $f['campaign']; $a[] = $f['campaign']; }
    if (!empty($f['landing'])) { $w[] = 's.landing = ?'; $a[] = $f['landing']; }
    // نطاق التقرير: الموقع بس، أو صفحات الهبوط بس، أو الكل
    $scope = $f['scope'] ?? 'site';
    if (empty($f['landing']) && in_array($scope, ['site', 'lp'], true)) {
        $paths = lp_paths();
        if ($paths) {
            $w[] = 's.landing ' . ($scope === 'lp' ? '' : 'NOT ') . 'IN (' . implode(',', array_fill(0, count($paths), '?')) . ')';
            $a = array_merge($a, $paths);
        } elseif ($scope === 'lp') $w[] = '0';
    }
    if (($f['converted'] ?? '') === '1') $w[] = '(s.calls + s.whatsapps) > 0';
    if (($f['converted'] ?? '') === '0') $w[] = '(s.calls + s.whatsapps) = 0';
    if (($f['returning'] ?? '') === '1') $w[] = 's.new_visitor = 0';
    if (($f['ads'] ?? '') === '1') $w[] = 's.is_ads = 1';
    return [implode(' AND ', $w), $a];
}

function fmt_dur(int $s): string {
    if ($s < 60) return $s . ' ث';
    $m = intdiv($s, 60); $r = $s % 60;
    return $m < 60 ? "$m د " . ($r ? "$r ث" : '') : intdiv($m, 60) . ' س ' . ($m % 60) . ' د';
}
function pct($a, $b): string { return $b ? round($a * 100 / $b, 1) . '%' : '0%'; }

/** حذف البيانات الأقدم من مدة الاحتفاظ (بيتنفذ مرة في اليوم) */
function stats_cleanup(): void {
    if (setting('_stats_cleanup') === date('Y-m-d')) return;
    $days = max(7, (int) setting('stats_retention_days', '180'));
    $cut = time() - $days * 86400;
    $db = stats_db();
    $db->prepare('DELETE FROM pageviews WHERE ts < ?')->execute([$cut]);
    $db->prepare('DELETE FROM events WHERE ts < ?')->execute([$cut]);
    $db->prepare('DELETE FROM sessions WHERE started_at < ?')->execute([$cut]);
    set_setting('_stats_cleanup', date('Y-m-d'));
}

const EVENT_NAMES = ['call' => 'اتصال', 'whatsapp' => 'واتساب', 'cta' => 'زرار', 'nav' => 'القايمة', 'outbound' => 'رابط خارجي', 'link' => 'رابط داخلي', 'form' => 'فورم'];

/** روابط كل صفحات الهبوط (/ads ...) */
function lp_paths(): array {
    static $p = null;
    if ($p === null) { try { $p = array_map(fn($x) => '/' . $x, db()->query('SELECT slug FROM landing_pages')->fetchAll(PDO::FETCH_COLUMN)); } catch (Throwable) { $p = []; } }
    return $p;
}
const STATS_SCOPES = ['site' => 'الموقع (من غير صفحات الهبوط)', 'lp' => 'صفحات الهبوط بس', 'all' => 'الكل'];
