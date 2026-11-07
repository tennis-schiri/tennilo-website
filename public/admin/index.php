<?php
declare(strict_types=1);

/*
 * TENNILO Backoffice – gemeinsame, lesende Übersicht für Newsletter und Founders.
 * Wiederverwendet ausschließlich die bestehende private Datenbank und Admin-Sitzung.
 * Kein eigener Login, keine neue Empfängerliste, kein automatischer Mailversand.
 */
header('Content-Type: text/html; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
header('X-Robots-Tag: noindex, nofollow, noarchive');
header('Referrer-Policy: no-referrer');
header('Cache-Control: private, no-store, max-age=0');
header('X-Frame-Options: DENY');
header("Content-Security-Policy: default-src 'none'; img-src 'self'; style-src 'unsafe-inline'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");

$configFile = dirname(__DIR__, 2).'/tennilo-founders-config.php';
if (!is_file($configFile)) {
    http_response_code(503);
    exit('TENNILO-Verwaltung noch nicht eingerichtet.');
}
try {
    $cfg = require $configFile;
} catch (Throwable $e) {
    error_log('TENNILO Backoffice config: '.$e->getMessage());
    http_response_code(503);
    exit('Konfiguration nicht verfügbar.');
}
if (!is_array($cfg) || ($cfg['enabled'] ?? false) !== true ||
    empty($cfg['database']) || empty($cfg['admin_password_hash']) || empty($cfg['key'])) {
    http_response_code(503);
    exit('TENNILO-Verwaltung nicht freigeschaltet.');
}

session_name('tennilo_founders_portal_v2');
session_set_cookie_params(['httponly' => true, 'secure' => true, 'samesite' => 'Strict', 'path' => '/']);
session_start();
if (empty($_SESSION['authenticated']) || (int)($_SESSION['auth_at'] ?? 0) < time() - 8 * 3600) {
    $_SESSION = [];
    header('Location: /founders-admin.php', true, 303);
    exit;
}
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(24));
}

function tnEsc(mixed $value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function tnDate(mixed $timestamp): string {
    return is_numeric($timestamp) && (int)$timestamp > 0
        ? date('d.m.Y, H:i', (int)$timestamp) : '–';
}
function tnHasTopic(array $person, string $topic, array $newsletterConsents): bool {
    if ($person['confirmed_at'] === null) return false;
    $topics = json_decode((string)($person['interests'] ?? '[]'), true);
    if (!is_array($topics) || !in_array($topic, $topics, true)) return false;
    if ($topic === 'newsletter' && !isset($newsletterConsents[(int)$person['id']])) return false;
    return true;
}
function tnTablePresent(PDO $db, string $name): bool {
    $q = $db->prepare("SELECT 1 FROM sqlite_master WHERE type='table' AND name=? LIMIT 1");
    $q->execute([$name]);
    return (bool)$q->fetchColumn();
}
function tnStatus(string $state): string {
    return match ($state) {
        'draft' => 'Entwurf',
        'ready' => 'Freigegeben',
        'sending' => 'Im Versand',
        'finished' => 'Abgeschlossen',
        'stopped' => 'Gestoppt',
        default => $state
    };
}

try {
    $db = new PDO('sqlite:'.$cfg['database'], null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    $db->exec('PRAGMA busy_timeout=5000');
    $people = tnTablePresent($db, 'subscribers')
        ? $db->query('SELECT id,email,role,interests,created_at,confirmed_at FROM subscribers ORDER BY created_at DESC')->fetchAll()
        : [];
    $consents = [];
    if (tnTablePresent($db, 'subscriber_consents')) {
        foreach ($db->query("SELECT subscriber_id FROM subscriber_consents WHERE topic='newsletter'")->fetchAll() as $row) {
            $consents[(int)$row['subscriber_id']] = true;
        }
    }
    $campaigns = tnTablePresent($db, 'founders_campaigns')
        ? $db->query('SELECT id,subject,topic,state,created_at,updated_at FROM founders_campaigns ORDER BY id DESC LIMIT 100')->fetchAll()
        : [];
} catch (Throwable $e) {
    error_log('TENNILO Backoffice storage: '.$e->getMessage());
    http_response_code(503);
    exit('Verwaltungsdaten vorübergehend nicht verfügbar.');
}

$topics = [
    'newsletter' => 'Allgemeiner Newsletter',
    'beta' => 'Betatests',
    'founders' => 'Founders',
    'crowdfunding' => 'Crowdfunding'
];
$counts = array_fill_keys(array_keys($topics), 0);
$confirmed = 0;
$pending = 0;
foreach ($people as $p) {
    if ($p['confirmed_at'] === null) { $pending++; continue; }
    $confirmed++;
    foreach ($topics as $key => $label) {
        if (tnHasTopic($p, $key, $consents)) $counts[$key]++;
    }
}
$view = (string)($_GET['view'] ?? 'dashboard');
if (!in_array($view, ['dashboard', 'subscribers', 'campaigns', 'settings'], true)) $view = 'dashboard';
$filter = (string)($_GET['topic'] ?? 'all');
if (!in_array($filter, array_merge(['all', 'pending'], array_keys($topics)), true)) $filter = 'all';
$visiblePeople = array_values(array_filter($people, static function (array $p) use ($filter, $consents): bool {
    if ($filter === 'all') return true;
    if ($filter === 'pending') return $p['confirmed_at'] === null;
    return tnHasTopic($p, $filter, $consents);
}));
$visiblePeople = array_slice($visiblePeople, 0, 100);
$drafts = 0;
$active = 0;
foreach ($campaigns as $campaign) {
    if ($campaign['state'] === 'draft') $drafts++;
    if (in_array($campaign['state'], ['ready', 'sending'], true)) $active++;
}
$pageTitles = ['dashboard'=>'Übersicht','subscribers'=>'Abonnenten','campaigns'=>'Kampagnen','settings'=>'Verwaltung'];
$pageTitle = $pageTitles[$view];
?>
<!doctype html>
<html lang="de">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="robots" content="noindex,nofollow">
  <meta name="theme-color" content="#092b36">
  <title><?= tnEsc($pageTitle) ?> · TENNILO Backoffice</title>
  <style>
  :root{--dark:#092b36;--dark2:#173f4c;--teal:#04bdb8;--lime:#e7ff6a;--coral:#ed805c;--ink:#17343d;--muted:#5f747b;--line:#dce8e6;--surface:#f2f7f5}
  *{box-sizing:border-box}body{margin:0;background:var(--surface);color:var(--ink);font:15px/1.5 system-ui,-apple-system,"Segoe UI",sans-serif}a{color:inherit}a:focus-visible,button:focus-visible{outline:3px solid var(--coral);outline-offset:3px}
  .shell{min-height:100vh;display:grid;grid-template-columns:250px minmax(0,1fr)}
  aside{background:var(--dark);color:#e8f9f7;padding:26px 18px;display:flex;flex-direction:column;gap:24px}
  .brand{display:block;padding:15px 13px;background:#f6f8f4;border-radius:13px}.brand img{width:176px;height:auto;display:block}
  .brand small{display:block;color:#49716e;font-weight:750;font-size:11px;letter-spacing:.16em;margin-top:12px}
  nav{display:grid;gap:6px}.nav-label{font-size:10px;text-transform:uppercase;letter-spacing:.17em;color:#97b7b4;padding:5px 11px}
  .nav-link{display:block;border-radius:11px;color:#d4e7e3;text-decoration:none;padding:11px 14px;font-weight:700}
  .nav-link:hover,.nav-link[aria-current=page]{background:#22505a;color:white}
  .nav-link[aria-current=page]{box-shadow:inset 3px 0 0 var(--lime)}
  .side-foot{margin-top:auto;border-top:1px solid #28505a;padding:20px 9px 0;font-size:12px;color:#b8d0cb}
  .side-foot a{display:block;margin-bottom:12px;color:#c9efdf}
  .side-foot button{border:1px solid #6b9293;background:none;color:white;border-radius:8px;padding:8px 12px;cursor:pointer}
  main{max-width:1220px;width:100%;margin:0 auto;padding:35px clamp(17px,3.6vw,55px) 70px}
  .eyebrow{font-weight:850;color:#007e80;text-transform:uppercase;letter-spacing:.15em;font-size:11px;margin:0 0 5px}
  h1{font-size:clamp(29px,3vw,42px);line-height:1.1;letter-spacing:-.04em;margin:0 0 8px}
  h2{font-size:20px;letter-spacing:-.025em;margin:0 0 16px}
  p{margin:8px 0 16px}.muted{color:var(--muted)}.intro{margin:0 0 30px;color:var(--muted)}
  .topline{display:flex;justify-content:space-between;align-items:flex-start;gap:20px;flex-wrap:wrap;margin-bottom:27px}
  .button{display:inline-flex;align-items:center;justify-content:center;min-height:39px;border:0;border-radius:9px;background:var(--dark);color:white;text-decoration:none;font-weight:800;padding:10px 15px;white-space:nowrap}
  .button.lime{background:var(--lime);color:var(--dark)}.button.outline{background:white;color:var(--dark);border:1px solid var(--line)}
  .actions{display:flex;flex-wrap:wrap;gap:9px}.cards{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:15px;margin-bottom:25px}
  .stat,.panel{background:white;border:1px solid var(--line);border-radius:15px}
  .stat{padding:19px 18px}.stat strong{font-size:32px;color:var(--dark);display:block;line-height:1.2;letter-spacing:-.05em}
  .stat span{display:block;color:var(--muted);font-size:12px;margin-top:6px;font-weight:650}.stat a{font-size:12px;color:#047d7c}
  .panel{padding:23px;margin:0 0 21px}.panel-head{display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap;align-items:baseline}
  .grid-2{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1fr);gap:20px}
  .quick{display:grid;gap:12px}.quick a{display:block;text-decoration:none;background:#f4f9f7;border:1px solid var(--line);border-radius:12px;padding:15px 17px;font-weight:800}
  .quick a:hover{border-color:var(--teal)}.quick small{display:block;font-weight:400;color:var(--muted);margin-top:4px}
  .table-wrap{overflow-x:auto}table{width:100%;border-collapse:collapse;text-align:left;font-size:13px}
  th{text-transform:uppercase;letter-spacing:.08em;font-size:10px;color:#617b81}
  th,td{padding:13px 11px;border-bottom:1px solid var(--line);vertical-align:top}
  td a{font-weight:700;color:#067d7d}tr:last-child td{border-bottom:0}
  .pill{font-size:11px;font-weight:750;border:1px solid #badbd2;background:#eaf8ef;color:#17584b;padding:4px 8px;display:inline-block;border-radius:6px;white-space:nowrap}
  .pill.pending{background:#fff5e8;color:#8b4d1e;border-color:#f4dec0}
  .filters{display:flex;gap:7px;flex-wrap:wrap;margin:0 0 20px}.filters a{text-decoration:none;border:1px solid var(--line);background:white;padding:8px 11px;border-radius:9px;font-weight:700;font-size:12px}
  .filters a[aria-current=page]{background:var(--dark);color:white;border-color:var(--dark)}
  .footnote{font-size:12px;color:var(--muted)}
  @media(max-width:1020px){.cards{grid-template-columns:repeat(2,minmax(0,1fr))}}
  @media(max-width:740px){.shell{grid-template-columns:minmax(0,1fr)}aside{padding:15px 14px;gap:11px}.brand img{width:145px}.brand small{margin-top:4px}nav{display:flex;flex-wrap:wrap}.nav-label{display:none}.nav-link{padding:8px 10px;font-size:12px}.side-foot{margin:0;border:0;padding:0;display:flex;gap:14px;align-items:center;flex-wrap:wrap}.side-foot a{margin:0}main{padding:24px 15px 50px}.grid-2{grid-template-columns:1fr}.panel{padding:17px}}
  </style>
</head>
<body>
<div class="shell">
<aside>
  <a href="/" class="brand" aria-label="TENNILO Startseite"><img src="/assets/tennilo-logo.png" alt="TENNILO"><small>BACKOFFICE</small></a>
  <nav aria-label="Backoffice-Menü">
    <span class="nav-label">Verwaltung</span>
    <a class="nav-link" href="/admin/" <?= $view==='dashboard'?'aria-current="page"':'' ?>>Übersicht</a>
    <a class="nav-link" href="/admin/?view=subscribers" <?= $view==='subscribers'?'aria-current="page"':'' ?>>Abonnenten</a>
    <a class="nav-link" href="/admin/?view=campaigns" <?= $view==='campaigns'?'aria-current="page"':'' ?>>Kampagnen</a>
    <a class="nav-link" href="/admin/?view=settings" <?= $view==='settings'?'aria-current="page"':'' ?>>Einstellungen & Links</a>
  </nav>
  <div class="side-foot">
    <a href="https://tennilo.de/" target="_blank" rel="noopener noreferrer">Website ansehen ↗</a>
    <form method="post" action="/founders-admin.php">
      <input type="hidden" name="action" value="logout">
      <input type="hidden" name="csrf" value="<?= tnEsc($_SESSION['csrf']) ?>">
      <button type="submit">Abmelden</button>
    </form>
  </div>
</aside>
<main>
  <div class="topline">
    <div><p class="eyebrow">TENNILO · Newsletter & Founders</p><h1><?= tnEsc($pageTitle) ?></h1><p class="intro">Gemeinsame Verwaltung für den TENNILO-Newsletter und die Interessentenliste.</p></div>
    <div class="actions"><a class="button lime" href="/founders-mail.php">E-Mail verfassen</a><a class="button outline" href="/founders-admin.php">Empfängerverwaltung</a></div>
  </div>
  <?php if ($view === 'dashboard'): ?>
  <div class="cards">
    <div class="stat"><strong><?= $counts['newsletter'] ?></strong><span>Newsletter-Abonnenten</span></div>
    <div class="stat"><strong><?= $counts['beta'] ?></strong><span>Betatest-Interessenten</span></div>
    <div class="stat"><strong><?= $counts['founders'] ?></strong><span>Founders-Interessenten</span></div>
    <div class="stat"><strong><?= $pending ?></strong><span>Unbestätigte Anmeldungen</span></div>
  </div>
  <div class="grid-2">
    <section class="panel"><h2>Schnellzugriff</h2><div class="quick">
      <a href="/founders-mail.php?template=newsletter">Newsletter erstellen <small>Vorlage „Allgemeines Update“, Absender news@tennilo.de</small></a>
      <a href="/founders-mail.php">Entwürfe & Versand <small>Testmail, Vorschau, manuelle Freigabe und Versandstatus</small></a>
      <a href="/admin/?view=subscribers&topic=newsletter">Newsletter-Kontakte <small>Nur bestätigte Adressen mit separater Newsletter-Einwilligung</small></a>
      <a href="/founders-admin.php">Founders-Liste & CSV-Export <small>Geschützter Zugang zum bisherigen Kontaktbereich</small></a>
    </div></section>
    <section class="panel"><h2>Empfänger nach Thema</h2>
      <div class="table-wrap"><table><thead><tr><th>Empfängerkreis</th><th>Anzahl</th></tr></thead><tbody>
      <?php foreach ($topics as $topic => $label): ?>
      <tr><td><a href="/admin/?view=subscribers&amp;topic=<?= tnEsc($topic) ?>"><?= tnEsc($label) ?></a></td><td><?= $counts[$topic] ?></td></tr>
      <?php endforeach; ?>
      <tr><td>Bestätigte Kontakte insgesamt</td><td><?= $confirmed ?></td></tr>
      </tbody></table></div>
      <p class="footnote">Ein Kontakt kann mehreren Themen zugestimmt haben. Newsletter-Einwilligungen werden separat geprüft.</p>
    </section>
  </div>
  <section class="panel"><div class="panel-head"><h2>Letzte Kampagnen</h2><a href="/admin/?view=campaigns">Alle anzeigen</a></div>
    <?php if (!$campaigns): ?><p class="muted">Noch keine Kampagnen angelegt.</p>
    <?php else: ?><div class="table-wrap"><table><thead><tr><th>Betreff</th><th>Empfängerkreis</th><th>Status</th></tr></thead><tbody>
      <?php foreach (array_slice($campaigns,0,6) as $c): ?><tr><td><a href="/founders-mail.php?campaign=<?= (int)$c['id'] ?>"><?= tnEsc($c['subject']) ?></a></td><td><?= tnEsc($topics[$c['topic']] ?? $c['topic']) ?></td><td><span class="pill"><?= tnEsc(tnStatus((string)$c['state'])) ?></span></td></tr><?php endforeach; ?>
    </tbody></table></div><?php endif; ?>
  </section>
  <?php elseif ($view === 'subscribers'): ?>
  <div class="cards">
    <div class="stat"><strong><?= count($people) ?></strong><span>Einträge insgesamt</span></div>
    <div class="stat"><strong><?= $confirmed ?></strong><span>Bestätigt</span></div>
    <div class="stat"><strong><?= $counts['newsletter'] ?></strong><span>Newsletter-Opt-ins</span></div>
    <div class="stat"><strong><?= $pending ?></strong><span>Noch nicht bestätigt</span></div>
  </div>
  <div class="filters" aria-label="Empfängerfilter">
    <?php foreach (array_merge(['all'=>'Alle','pending'=>'Unbestätigt'],$topics) as $key => $label): ?>
    <a href="/admin/?view=subscribers&amp;topic=<?= tnEsc($key) ?>" <?= $filter===$key?'aria-current="page"':'' ?>><?= tnEsc($label) ?></a>
    <?php endforeach; ?>
  </div>
  <section class="panel">
    <div class="panel-head"><h2>Kontakte <?= $filter!=='all'?'· '.tnEsc($filter==='pending'?'Unbestätigt':($topics[$filter]??'')):'' ?></h2><a href="/founders-admin.php">CSV-Export und Gesamtliste</a></div>
    <p class="footnote">Es werden maximal 100 der neuesten passenden Einträge angezeigt. Diese Ansicht versendet keine E-Mails und verändert keine Einwilligung.</p>
    <div class="table-wrap"><table><thead><tr><th>E-Mail</th><th>Zielgruppe</th><th>Angemeldet</th><th>Status</th></tr></thead><tbody>
      <?php foreach ($visiblePeople as $p): ?>
      <tr><td><?= tnEsc($p['email']) ?></td><td><?= tnEsc($p['role'] ?? '') ?></td><td><?= tnEsc(tnDate($p['created_at'])) ?></td><td><span class="pill <?= $p['confirmed_at']===null?'pending':'' ?>"><?= $p['confirmed_at']===null?'Offen':'Bestätigt' ?></span></td></tr>
      <?php endforeach; ?>
      <?php if (!$visiblePeople): ?><tr><td colspan="4">Keine passenden Kontakte vorhanden.</td></tr><?php endif; ?>
    </tbody></table></div>
  </section>
  <?php elseif ($view === 'campaigns'): ?>
  <div class="cards">
    <div class="stat"><strong><?= count($campaigns) ?></strong><span>Letzte Kampagnen (max. 100)</span></div>
    <div class="stat"><strong><?= $drafts ?></strong><span>Offene Entwürfe</span></div>
    <div class="stat"><strong><?= $active ?></strong><span>Freigegeben / im Versand</span></div>
    <div class="stat"><strong><?= $counts['newsletter'] ?></strong><span>Newsletter-Empfängerkreis</span></div>
  </div>
  <section class="panel"><div class="panel-head"><h2>Entwürfe und Aussendungen</h2><a class="button" href="/founders-mail.php">Zum Kampagneneditor</a></div>
    <p class="muted">Neue Kampagnen werden im bestehenden Editor vorbereitet und erst nach Testmail und ausdrücklicher Freigabe in kleinen Chargen verschickt.</p>
    <div class="table-wrap"><table><thead><tr><th>Betreff</th><th>Thema</th><th>Status</th><th>Geändert</th></tr></thead><tbody>
      <?php foreach ($campaigns as $c): ?><tr>
        <td><a href="/founders-mail.php?campaign=<?= (int)$c['id'] ?>"><?= tnEsc($c['subject']) ?></a></td>
        <td><?= tnEsc($topics[$c['topic']]??$c['topic']) ?></td><td><span class="pill"><?= tnEsc(tnStatus((string)$c['state'])) ?></span></td><td><?= tnEsc(tnDate($c['updated_at'])) ?></td>
      </tr><?php endforeach; ?>
      <?php if (!$campaigns): ?><tr><td colspan="4">Noch keine Kampagnen vorhanden.</td></tr><?php endif; ?>
    </tbody></table></div>
  </section>
  <?php else: ?>
  <div class="grid-2">
    <section class="panel"><h2>Verwaltung</h2><div class="quick">
      <a href="/founders-admin.php">Empfängerverwaltung <small>Vorhandene Kontakte und sicherer CSV-Export</small></a>
      <a href="/founders-mail.php">Newsletter-Versand <small>Vorlagen, Testmails, Freigabe und Versand</small></a>
      <a href="/newsletter.php?action=manage" target="_blank" rel="noopener noreferrer">E-Mail-Themen verwalten <small>Öffentliche, E-Mail-verifizierte Präferenzen</small></a>
    </div></section>
    <section class="panel"><h2>Betrieb & Datenschutz</h2>
      <p>Absender für allgemeine Updates: <strong>news@tennilo.de</strong></p>
      <p>Absender für Founders: <strong>founders@tennilo.de</strong></p>
      <p>Empfänger, Opt-ins und Versanddaten liegen in der bestehenden privaten TENNILO-Datenbank – nicht im GitHub-Repository und nicht bei Tennis-Schiri.</p>
      <p class="footnote">Dieses Backoffice versendet selbst keine Mails. Der Versand erfolgt ausschließlich über den geschützten Kampagneneditor. Bei größerem Versandvolumen Zustellbarkeit, Backup und Datenschutz erneut prüfen.</p>
    </section>
  </div>
  <?php endif; ?>
  <p class="footnote">TENNILO Backoffice · Kein automatischer Newsletter-Versand · Interner Bereich</p>
</main>
</div>
</body>
</html>
