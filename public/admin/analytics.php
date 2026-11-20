<?php
declare(strict_types=1);
/* TENNILO anonymous page view dashboard, reuses existing Founders admin login. */
header('Content-Type: text/html; charset=UTF-8');
header('Cache-Control: private, no-store, max-age=0');
header('X-Content-Type-Options: nosniff');
header('X-Robots-Tag: noindex, nofollow, noarchive');
header('Referrer-Policy: no-referrer');
header('X-Frame-Options: DENY');
header("Content-Security-Policy: default-src 'none'; img-src 'self'; style-src 'unsafe-inline'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");
$configFile = dirname(__DIR__, 2).'/tennilo-founders-config.php';
if (!is_file($configFile)) {
    http_response_code(503);
    exit('TENNILO-Verwaltung nicht eingerichtet.');
}
try {
    $cfg = require $configFile;
} catch (Throwable $e) {
    error_log('TENNILO analytics admin config: '.get_class($e));
    http_response_code(503);
    exit('Konfiguration nicht verfügbar.');
}
if (!is_array($cfg) || ($cfg['enabled'] ?? false) !== true ||
    empty($cfg['database']) || empty($cfg['admin_password_hash']) || empty($cfg['key'])) {
    http_response_code(503);
    exit('TENNILO-Verwaltung nicht freigeschaltet.');
}
session_name('tennilo_founders_portal_v2');
session_set_cookie_params(['httponly'=>true,'secure'=>true,'samesite'=>'Strict','path'=>'/']);
session_start();
if (empty($_SESSION['authenticated']) || (int)($_SESSION['auth_at'] ?? 0) < time()-8*3600) {
    $_SESSION = [];
    header('Location: /founders-admin.php', true, 303);
    exit;
}

function tnEsc(mixed $s): string {
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function tnNumber(int $count): string {
    return number_format($count, 0, ',', '.');
}

$range = (int)($_GET['days'] ?? 30);
if (!in_array($range, [7,30,90,365], true)) $range = 30;
$now = new DateTimeImmutable('today', new DateTimeZone('Europe/Berlin'));
$start = $now->modify('-'.($range - 1).' days')->format('Y-m-d');
$end = $now->format('Y-m-d');
$today = 0;
$total = 0;
$byDay = [];
$byPage = [];
$byDevice = ['mobile'=>0,'tablet'=>0,'desktop'=>0];
$error = '';
$dbPath = dirname(__DIR__, 2).'/tennilo-analytics.sqlite';
$pages = [
    'home-de'=>'Startseite (DE)',
    'home-en'=>'Home (EN)',
    'imprint-de'=>'Impressum (DE)',
    'imprint-en'=>'Imprint (EN)',
    'privacy-de'=>'Datenschutz (DE)',
    'privacy-en'=>'Privacy (EN)'
];
for ($i=$range-1; $i>=0; $i--) {
    $byDay[$now->modify('-'.$i.' days')->format('Y-m-d')] = 0;
}
if (is_file($dbPath)) {
    try {
        $db = new PDO('sqlite:'.$dbPath, null, null, [
            PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
            PDO::ATTR_TIMEOUT=>3
        ]);
        // Open only the known table; data is already daily and anonymous.
        $q = $db->prepare('SELECT day, page, device, SUM(views) AS views
            FROM page_views WHERE day >= ? AND day <= ?
            GROUP BY day, page, device ORDER BY day ASC');
        $q->execute([$start, $end]);
        foreach ($q->fetchAll() as $row) {
            $day = (string)$row['day'];
            $page = (string)$row['page'];
            $device = (string)$row['device'];
            $views = (int)$row['views'];
            $total += $views;
            if ($day === $end) $today += $views;
            if (isset($byDay[$day])) $byDay[$day] += $views;
            if (isset($pages[$page])) $byPage[$page] = ($byPage[$page] ?? 0) + $views;
            if (isset($byDevice[$device])) $byDevice[$device] += $views;
        }
    } catch (Throwable $e) {
        error_log('TENNILO analytics admin read: '.get_class($e));
        $error = 'Die Statistik kann derzeit nicht geladen werden. Bitte die Datenbankberechtigung prüfen.';
    }
} else {
    $error = 'Noch keine Messdaten vorhanden. Die Zählung beginnt nach Veröffentlichung.';
}
arsort($byPage, SORT_NUMERIC);
$maxDay = max(1, ...array_values($byDay));
$maxPage = max(1, ...(count($byPage) ? array_values($byPage) : [1]));
$maxDevice = max(1, ...array_values($byDevice));
$dayLabels = ['Mo','Di','Mi','Do','Fr','Sa','So'];
?><!doctype html>
<html lang="de">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<meta name="theme-color" content="#081e36">
<title>Besucherstatistik · TENNILO Backoffice</title>
<style>
:root{--ink:#081e36;--blue:#2333c9;--coral:#ff6541;--cyan:#48dceb;--line:#d7dfeb;--surface:#f5f4f1;--muted:#54657b}
*{box-sizing:border-box}body{margin:0;background:var(--surface);color:var(--ink);font:15px/1.5 system-ui,-apple-system,Segoe UI,sans-serif}a{color:inherit}
a:focus-visible{outline:3px solid var(--coral);outline-offset:3px}
.shell{min-height:100vh;display:grid;grid-template-columns:238px minmax(0,1fr)}
aside{background:var(--ink);color:#fff;padding:26px 19px;display:flex;flex-direction:column;gap:29px}
.brand{text-decoration:none;display:block;font-size:24px;font-weight:950;letter-spacing:-.04em}.brand span{font-size:10px;display:block;letter-spacing:.17em;color:#87dfef}
nav{display:grid;gap:7px}.nav-label{font-size:10px;letter-spacing:.17em;color:#96b4c5;text-transform:uppercase;margin:5px 12px}
.nav-link{padding:11px 13px;font-size:14px;text-decoration:none;font-weight:700;border-left:3px solid transparent;color:#d7eafa}
.nav-link[aria-current],.nav-link:hover{background:#17334d;border-left-color:var(--coral)}
.back{display:block;margin-top:auto;border-top:1px solid #32506b;padding:20px 11px 0;font-size:12px;color:#bad5eb;text-decoration:none}
main{width:min(1220px,100%);margin:0 auto;padding:38px clamp(17px,4vw,60px) 75px}
.top{display:flex;justify-content:space-between;align-items:flex-end;gap:20px;flex-wrap:wrap;margin-bottom:29px}
.eyebrow{color:var(--blue);font-size:11px;font-weight:950;letter-spacing:.16em;text-transform:uppercase}
h1{margin:5px 0 6px;font-size:clamp(29px,4vw,48px);line-height:1.08;letter-spacing:-.05em}
p{margin:0}.muted{color:var(--muted);font-size:13px;line-height:1.6}
.ranges{display:flex;flex-wrap:wrap;gap:6px}.ranges a{text-decoration:none;padding:9px 13px;background:#fff;border:1px solid var(--line);font-weight:800;font-size:12px}
.ranges a[aria-current]{background:var(--blue);color:white;border-color:var(--blue)}
.cards{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:15px;margin-bottom:22px}
.stat{padding:23px;background:#fff;border:1px solid var(--line);border-top:4px solid var(--blue)}
.stat:nth-child(2){border-top-color:var(--coral)}.stat:nth-child(3){border-top-color:var(--cyan)}
.stat strong{display:block;font-size:clamp(30px,4vw,49px);font-variant-numeric:tabular-nums;letter-spacing:-.06em;line-height:1.2}
.stat span{display:block;font-size:12px;font-weight:700;color:var(--muted);margin-top:5px}
.panel{background:#fff;border:1px solid var(--line);padding:24px;margin-bottom:20px}
.panel h2{font-size:19px;margin:0 0 16px;letter-spacing:-.03em}
.alert{border-left:4px solid var(--coral);padding:13px 16px;background:#fff5f1;margin-bottom:20px;color:#79392e;font-size:13px}
.bar-chart{display:flex;align-items:flex-end;gap:3px;min-height:160px;border-bottom:2px solid var(--line);overflow:hidden}
.daybar{height:155px;min-width:0;flex:1;display:flex;flex-direction:column;justify-content:flex-end}
.daybar span{display:block;width:100%;background:var(--blue);min-height:0}.daybar:last-child span{background:var(--coral)}
.chart-footer{display:flex;justify-content:space-between;color:var(--muted);font-size:11px;margin-top:12px}
.grid{display:grid;grid-template-columns:1.3fr .7fr;gap:20px}
.row{display:grid;grid-template-columns:minmax(0,1fr) 90px;gap:12px;align-items:center;margin:0 0 17px}
.row-label{font-size:13px;font-weight:700}.row-val{text-align:right;font-size:13px;font-variant-numeric:tabular-nums;font-weight:850}
.meter{grid-column:1/-1;height:8px;background:#e9eef5;margin-top:-5px}
.meter i{display:block;height:100%;background:var(--blue)}
.device .meter i{background:var(--coral)}
.note{font-size:12px;color:#627086;line-height:1.7}
.foot{margin-top:28px;color:#607086;font-size:12px}
@media(max-width:960px){.shell{grid-template-columns:1fr}aside{padding:15px 20px;gap:12px}nav{display:flex;gap:4px;flex-wrap:wrap}.nav-label{display:none}.nav-link{padding:8px;font-size:12px}.back{display:none}.grid{grid-template-columns:1fr}}
@media(max-width:600px){main{padding:25px 14px 48px}.cards{grid-template-columns:1fr}.panel{padding:17px}.ranges a{padding:8px 11px}.bar-chart{gap:2px}}
</style>
</head><body><div class="shell">
<aside><a class="brand" href="/admin/">TENNILO <span>BACKOFFICE</span></a>
<nav aria-label="Backoffice-Menü"><span class="nav-label">Verwaltung</span>
<a class="nav-link" href="/admin/">Übersicht</a>
<a class="nav-link" href="/admin/analytics.php" aria-current="page">Besucherstatistik</a>
<a class="nav-link" href="/admin/?view=subscribers">Abonnenten</a>
<a class="nav-link" href="/admin/?view=campaigns">Kampagnen</a>
<a class="nav-link" href="/admin/?view=settings">Einstellungen</a></nav>
<a class="back" href="/">Website ansehen ↗</a></aside>
<main>
<div class="top"><div><p class="eyebrow">TENNILO / INSIGHTS</p><h1>Besucherstatistik</h1><p class="muted">Anonyme, aggregierte Seitenaufrufe · <?= tnEsc($start) ?> bis <?= tnEsc($end) ?></p></div>
<div class="ranges">
<?php foreach ([7,30,90,365] as $days): ?><a href="?days=<?= $days ?>" <?= $days===$range?'aria-current="page"':'' ?>><?= $days ?> Tage</a><?php endforeach; ?>
</div></div>
<?php if ($error): ?><div class="alert" role="status"><?= tnEsc($error) ?></div><?php endif; ?>
<div class="cards">
<div class="stat"><strong><?= tnNumber($today) ?></strong><span>Aufrufe heute</span></div>
<div class="stat"><strong><?= tnNumber($total) ?></strong><span>Aufrufe im gewählten Zeitraum</span></div>
<div class="stat"><strong><?= tnNumber((int)round($total/$range)) ?></strong><span>Ø Aufrufe pro Tag</span></div>
</div>
<section class="panel"><h2>Entwicklung nach Tagen</h2>
<div class="bar-chart" role="img" aria-label="Tägliche Seitenaufrufe im Zeitraum <?= $range ?> Tage. Höchster Tageswert: <?= $maxDay ?> Aufrufe.">
<?php foreach ($byDay as $day=>$views): ?>
<div class="daybar" title="<?= tnEsc($day) ?>: <?= $views ?> Aufrufe"><span style="height:<?= $views ? max(2,round($views/$maxDay*100)) : 0 ?>%"></span></div>
<?php endforeach; ?>
</div><div class="chart-footer"><span><?= tnEsc($start) ?></span><span><?= tnEsc($end) ?></span></div>
</section>
<div class="grid">
<section class="panel"><h2>Meistbesuchte Seiten</h2>
<?php if (!$byPage): ?><p class="muted">Noch keine Seitenaufrufe erfasst.</p><?php endif; ?>
<?php foreach ($byPage as $page=>$views): ?><div class="row">
<span class="row-label"><?= tnEsc($pages[$page]??$page) ?></span><span class="row-val"><?= tnNumber($views) ?></span>
<div class="meter"><i style="width:<?= round($views/$maxPage*100) ?>%"></i></div>
</div><?php endforeach; ?>
</section>
<section class="panel device"><h2>Geräteklassen</h2>
<?php foreach (['mobile'=>'Smartphone','tablet'=>'Tablet','desktop'=>'Desktop / sonstige'] as $key=>$label): $views=$byDevice[$key]; ?>
<div class="row"><span class="row-label"><?= tnEsc($label) ?></span>
<span class="row-val"><?= tnNumber($views) ?></span><div class="meter"><i style="width:<?= round($views/$maxDevice*100) ?>%"></i></div></div>
<?php endforeach; ?>
<p class="note">Die Geräteklasse wird aus gewöhnlichen HTTP-Anfrageinformationen grob abgeleitet. Sie ist nicht immer eindeutig.</p>
</section></div>
<p class="foot">Keine Cookies oder eindeutigen Besucherkennungen, keine Speicherung von IP-Adressen und keine individuellen Verläufe. Diese Zahlen sind <strong>Seitenaufrufe</strong> – keine Anzahl verschiedener Personen. Automatisierte Zugriffe können trotz Filterung enthalten sein. Statistikbeginn: ab Aktivierung.</p>
</main></div></body></html>
