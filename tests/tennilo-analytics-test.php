<?php
declare(strict_types=1);
/* Isolated, repeatable smoke test for TENNILO's anonymous PHP page-view counter. */
function check(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
}
$root = sys_get_temp_dir().'/tennilo-analytics-test-'.bin2hex(random_bytes(8));
mkdir($root.'/public', 0700, true);
$source = dirname(__DIR__).'/public/visit.php';
check(copy($source, $root.'/public/visit.php'), 'Cannot copy tracking endpoint');
function hit(string $file, string $page, string $ua, string $dnt='', string $gpc=''): void {
    $code = '$_GET["p"]='.var_export($page, true).';'
        .'$_SERVER["REQUEST_METHOD"]="GET";'
        .'$_SERVER["HTTP_USER_AGENT"]='.var_export($ua, true).';'
        .'$_SERVER["HTTP_DNT"]='.var_export($dnt, true).';'
        .'$_SERVER["HTTP_SEC_GPC"]='.var_export($gpc, true).';'
        .'ob_start();require '.var_export($file, true).';ob_end_clean();';
    $lines = [];
    $status = -1;
    exec(escapeshellarg(PHP_BINARY).' -r '.escapeshellarg($code).' 2>&1', $lines, $status);
    check($status === 0, 'Endpoint failed: '.implode(' | ', $lines));
}
try {
    $file=$root.'/public/visit.php';
    $desktop='Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 Chrome/129.0.0.0 Safari/537.36';
    $mobile='Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 Mobile/15E148 Safari/604.1';
    $bot='Googlebot/2.1 (+http://www.google.com/bot.html)';
    hit($file,'home-de',$desktop);
    hit($file,'home-de',$mobile);
    hit($file,'home-de',$desktop);
    hit($file,'home-en',$mobile);
    hit($file,'home-de',$bot);
    hit($file,'home-de',$desktop,'1');
    hit($file,'home-de',$desktop,'','1');
    hit($file,'invalid-page',$desktop);
    check(is_file($root.'/tennilo-analytics.sqlite'), 'Private SQLite DB not created');
    check(!is_file($root.'/public/tennilo-analytics.sqlite'), 'SQLite DB was accidentally created in public root');
    $db=new PDO('sqlite:'.$root.'/tennilo-analytics.sqlite',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
    $rows=$db->query('SELECT page,device,views FROM page_views ORDER BY page,device')->fetchAll(PDO::FETCH_ASSOC);
    $count=(int)$db->query('SELECT SUM(views) FROM page_views')->fetchColumn();
    check($count===4, 'Expected 4 page views, got '.$count);
    check(count($rows)===3, 'Expected three grouped rows, got '.count($rows));
    $get=function(string $page,string $device)use($rows):int{
        foreach($rows as $row) if($row['page']===$page&&$row['device']===$device) return (int)$row['views'];
        return 0;
    };
    check($get('home-de','desktop')===2,'Desktop grouping wrong');
    check($get('home-de','mobile')===1,'Mobile grouping wrong');
    check($get('home-en','mobile')===1,'English page grouping wrong');
    check(!in_array('HTTP_USER_AGENT', $db->query('PRAGMA table_info(page_views)')->fetchAll(PDO::FETCH_COLUMN),true),'Unexpected raw UA field');
    $db->exec("INSERT INTO page_views(day,page,device,views) VALUES(date('now','-500 days'),'home-de','desktop',12)");
    $db=null;
    hit($file,'home-de',$mobile);
    $db=new PDO('sqlite:'.$root.'/tennilo-analytics.sqlite');
    check((int)$db->query('SELECT SUM(views) FROM page_views')->fetchColumn()===5,'Expiry cleanup failed');
    check((int)$db->query("SELECT COUNT(*) FROM page_views WHERE day<date('now','-400 days')")->fetchColumn()===0,'Expired data retained');
    echo "TENNILO analytics smoke test passed: private aggregate DB, DNT/GPC, bots, device groups, 400-day retention.\n";
} finally {
    foreach (glob($root.'/tennilo-analytics.sqlite*') ?: [] as $f) @unlink($f);
    @unlink($root.'/public/visit.php');
    @rmdir($root.'/public');
    @rmdir($root);
}
