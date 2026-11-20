<?php
declare(strict_types=1);
/* Regression: discontinued first-party analytics must remain inactive. */
$root=dirname(__DIR__);
$public=$root.'/public';
function ensure(bool $condition,string $why): void {
    if (!$condition) throw new RuntimeException($why);
}
foreach (['index.html','en/index.html','impressum.html','en/imprint.html','datenschutz.html','en/privacy.html'] as $name) {
    $html=file_get_contents($public.'/'.$name);
    ensure(!str_contains($html,'visit.php'),$name.' still calls tracking pixel');
}
$pixel=file_get_contents($public.'/visit.php');
ensure(!str_contains($pixel,'SQLite')&&!str_contains($pixel,'PDO')&&!str_contains($pixel,'page_views'),'Inactive pixel endpoint must not access database');
$dashboard=file_get_contents($public.'/admin/analytics.php');
ensure(!str_contains($dashboard,'PDO')&&!str_contains($dashboard,'page_views'),'Dashboard still reads private counters');
$admin=file_get_contents($public.'/admin/index.php');
ensure(!str_contains($admin,'/admin/analytics.php'),'Backoffice still links to retired statistics');
$de=file_get_contents($public.'/datenschutz.html');
$en=file_get_contents($public.'/en/privacy.html');
ensure(str_contains($de,'ALL-INKL')&&str_contains($en,'ALL-INKL'),'Hosting statistics disclosure missing');
echo "TENNILO uses ALL-INKL server statistics; first-party counters are inactive.\n";
