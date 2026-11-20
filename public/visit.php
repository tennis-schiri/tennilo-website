<?php
declare(strict_types=1);

/* TENNILO minimalist first-party page-view counter.
 * No JavaScript, cookies, local storage, IPs, full user agents, referrer records,
 * visitor identifiers or individual event histories. Only grouped daily counters.
 */
header('Content-Type: image/gif');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
header('X-Robots-Tag: noindex, nofollow');

const TN_PIXEL = 'R0lGODlhAQABAAD/ACwAAAAAAQABAAACADs=';

$allowed = ['home-de','home-en','imprint-de','imprint-en','privacy-de','privacy-en'];
$page = (string)($_GET['p'] ?? '');
if (
    ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET'
    && in_array($page, $allowed, true)
    && ($_SERVER['HTTP_DNT'] ?? '') !== '1'
    && ($_SERVER['HTTP_SEC_GPC'] ?? '') !== '1'
) {
    $ua = (string)($_SERVER['HTTP_USER_AGENT'] ?? '');
    // Ignore known automated traffic. This is approximate, not a bot-proof filter.
    $isBot = preg_match('/bot|spider|crawler|slurp|preview|monitor|lighthouse|headless|ahrefs|semrush|uptime|facebookexternalhit|curl|wget|python|github|bingpreview|gptbot/i', $ua);
    if (!$isBot && strlen($ua) < 1200) {
        $device = 'desktop';
        if (preg_match('/ipad|tablet|kindle|silk|playbook|sm-t[0-9]/i', $ua)) {
            $device = 'tablet';
        } elseif (preg_match('/mobile|iphone|ipod|android|windows phone/i', $ua)) {
            $device = 'mobile';
        }
        try {
            // Private folder above the public document root; never upload the database.
            $database = dirname(__DIR__).'/tennilo-analytics.sqlite';
            $db = new PDO('sqlite:'.$database, null, null, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_TIMEOUT => 3
            ]);
            $db->exec('PRAGMA busy_timeout=3000');
            $db->exec('CREATE TABLE IF NOT EXISTS page_views (
                day TEXT NOT NULL,
                page TEXT NOT NULL,
                device TEXT NOT NULL,
                views INTEGER NOT NULL DEFAULT 0,
                PRIMARY KEY (day, page, device)
            )');
            $day = (new DateTimeImmutable('now', new DateTimeZone('Europe/Berlin')))->format('Y-m-d');
            $stmt = $db->prepare('INSERT INTO page_views (day,page,device,views) VALUES (?,?,?,1)
                ON CONFLICT(day,page,device) DO UPDATE SET views=views+1');
            $stmt->execute([$day, $page, $device]);
            if (random_int(1, 200) === 1) {
                $db->exec("DELETE FROM page_views WHERE day < date('now', '-400 days')");
            }
        } catch (Throwable $e) {
            // Never leak filesystem/database information to public visitors.
            error_log('TENNILO aggregated statistics unavailable: '.get_class($e));
        }
    }
}
echo base64_decode(TN_PIXEL, true);
