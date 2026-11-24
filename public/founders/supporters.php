<?php
declare(strict_types=1);
header('Content-Type: text/html; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');
header('Referrer-Policy: no-referrer');
header('X-Robots-Tag: noindex, nofollow');
header("Content-Security-Policy: default-src 'none'; img-src 'self'; style-src 'unsafe-inline'; base-uri 'none'; frame-ancestors 'none'");
require dirname(__DIR__).'/admin/founders-fulfilment-lib.php';
$rows=[];$lang=($_GET['lang']??'')==='en'?'en':'de';$de=$lang==='de';
try{
 $config=dirname(__DIR__,2).'/tennilo-founders-config.php';
 if(!is_file($config))throw new RuntimeException('Unavailable');
 $cfg=require $config;
 if(is_array($cfg)&&($cfg['enabled']??false)===true&&!empty($cfg['database'])&&is_file($cfg['database'])){
  $db=new PDO('sqlite:'.$cfg['database'],null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
  $db->exec('PRAGMA query_only=ON');$rows=ffPublicRows($db);
 }
}catch(Throwable $ex){$rows=[];}
function ep(mixed $v):string{return htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
$packages=ffPackages();
?><!doctype html><html lang="<?=$lang?>"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>TENNILO Founders</title><style>body{margin:0;background:#f7f4ed;color:#092b36;font:16px/1.55 system-ui,sans-serif}main{max-width:960px;padding:30px 22px 70px;margin:auto}img{max-width:300px;width:75%;height:auto}a{color:#8b621d}h1{font-size:34px}h2{font-size:22px;margin:30px 0 8px}ul{display:flex;gap:12px;flex-wrap:wrap;list-style:none;padding:0}li{padding:12px 18px;border:1px solid #d5c39d;background:#fff;border-radius:8px;overflow-wrap:anywhere;max-width:100%}.note{color:#576a70}footer{margin-top:40px}@media(prefers-color-scheme:dark){body{background:#071f32;color:#f7f4ed}li{background:#173746;border-color:#ad7c27}a{color:#e6bd70}.note{color:#c2d1d6}picture img{background:#f7f4ed;padding:8px;border-radius:6px}}</style></head><body><main><a href="<?=$de?'/founders/':'/en/founders/'?>"><img src="/assets/tennilo-founders-tf12-light.png" alt="TENNILO Founders"></a><p><a href="<?=$de?'/founders/':'/en/founders/'?>"><?=$de?'← Zur Kampagne':'← Back to the campaign'?></a> · <a href="?lang=<?=$de?'en':'de'?>"><?=$de?'EN':'DE'?></a></p><h1><?=$de?'Unsere Founders':'Our Founders'?></h1><p><?=$de?'Danke an alle, die die TENNILO-Produktfamilie mitgestalten.':'Thank you to everyone helping shape the TENNILO product family.'?></p><p class="note"><?=$de?'Wir zeigen hier ausschließlich ausdrücklich freigegebene Namen und Paketstufen.':'Only names and package tiers explicitly approved for publication are shown here.'?></p>
<?php foreach(array_reverse($packages,true) as $code=>$p):$group=array_filter($rows,static fn($r)=>$r['package']===$code);if(!$group)continue;?><h2><?=ep($p[0])?></h2><ul><?php foreach($group as $r):?><li><?=ep($r['public_name'])?></li><?php endforeach;?></ul><?php endforeach;if(!$rows):?><p><?=$de?'Noch keine öffentlich freigegebenen Einträge.':'No entries approved for public display yet.'?></p><?php endif;?><footer><a href="<?=$de?'/datenschutz.html':'/en/privacy.html'?>"><?=$de?'Datenschutz':'Privacy'?></a> · <a href="<?=$de?'/impressum.html':'/en/imprint.html'?>"><?=$de?'Impressum':'Legal notice'?></a></footer></main></body></html>
