<?php
declare(strict_types=1);
header('Content-Type: text/html; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
header('X-Robots-Tag: noindex, nofollow, noarchive');
header('Referrer-Policy: no-referrer');
header('Cache-Control: private, no-store, max-age=0');
header('X-Frame-Options: DENY');
header("Content-Security-Policy: default-src 'none'; img-src 'self'; style-src 'unsafe-inline'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");
session_name('tennilo_founders_portal_v2');
session_set_cookie_params(['httponly'=>true,'secure'=>true,'samesite'=>'Strict','path'=>'/']);
session_start();
if(empty($_SESSION['authenticated'])||(int)($_SESSION['auth_at']??0)<time()-8*3600){
 $_SESSION=[];header('Location: /founders-admin.php',true,303);exit;
}
if(empty($_SESSION['csrf']))$_SESSION['csrf']=bin2hex(random_bytes(24));
require __DIR__.'/founders-fulfilment-lib.php';
function e(mixed $x):string{return htmlspecialchars((string)$x,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
function hidden(string $action,int $id=0):string{return '<input type="hidden" name="csrf" value="'.e($_SESSION['csrf']).'"><input type="hidden" name="action" value="'.e($action).'"><input type="hidden" name="id" value="'.$id.'">';}
function redirect(string $msg,string $suffix=''):never{$_SESSION['ff_flash']=$msg;header('Location: /admin/founders.php'.$suffix,true,303);exit;}
function options(array $packages,string $selected=''):string{$html='';foreach($packages as $code=>$p)$html.='<option value="'.e($code).'"'.($code===$selected?' selected':'').'>'.e($p[0]).($code==='free'?'':' · '.$p[1].' €').'</option>';return $html;}
function csvDownload(string $filename,array $head,array $rows):never{
 header('Content-Type: text/csv; charset=UTF-8');header('Content-Disposition: attachment; filename="'.$filename.'"');
 $out=fopen('php://output','w');fwrite($out,"\xEF\xBB\xBF");fputcsv($out,$head,';','"','');
 foreach($rows as $r)fputcsv($out,array_map(static fn($x)=>ffCsvSafe((string)$x),$r),';','"','');
 exit;
}
try{
 $cfg=require dirname(__DIR__,2).'/tennilo-founders-config.php';
 if(!is_array($cfg)||($cfg['enabled']??false)!==true||empty($cfg['database'])||empty($cfg['key']))throw new RuntimeException('config');
 $db=new PDO('sqlite:'.$cfg['database'],null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
 ffSchema($db);
}catch(Throwable $ex){error_log('Founders fulfilment storage unavailable');http_response_code(503);exit('Founders-Verwaltung nicht verfügbar.');}
$packages=ffPackages();$tasks=ffTaskNames();$id=max(0,(int)($_GET['id']??0));$error='';$flash=(string)($_SESSION['ff_flash']??'');unset($_SESSION['ff_flash']);
if(isset($_SESSION['ff_preview'])&&(int)($_SESSION['ff_preview']['time']??0)<time()-900)unset($_SESSION['ff_preview']);
try{
 if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
  $csrf=$_POST['csrf']??null;
  if(!is_string($csrf)||!hash_equals($_SESSION['csrf'],$csrf)){http_response_code(403);exit('Ungültige Sitzung.');}
  $action=(string)($_POST['action']??'');$postedId=max(0,(int)($_POST['id']??0));
  if($action==='preview'){
   unset($_SESSION['ff_preview']);
   $raw=(string)($_POST['csv']??'');
   if(isset($_FILES['file'])&&$_FILES['file']['error']!==UPLOAD_ERR_NO_FILE){
    if($_FILES['file']['error']!==UPLOAD_ERR_OK||(int)$_FILES['file']['size']>1000000||!is_uploaded_file($_FILES['file']['tmp_name']))throw new InvalidArgumentException('Datei nicht lesbar oder zu groß.');
    $raw=file_get_contents($_FILES['file']['tmp_name']);if($raw===false)throw new InvalidArgumentException('Datei nicht lesbar.');
   }
   $mapping=[];foreach(['reference','name','email','package','lang'] as $k)$mapping[$k]=ffField($_POST['column_'.$k]??'',120,'Spaltenname');
   $rows=ffCSV($raw,$mapping);$_SESSION['ff_preview']=['rows'=>$rows,'time'=>time(),'token'=>bin2hex(random_bytes(16))];redirect('Import geprüft. Bitte die Vorschau bestätigen.','?view=import');
  }
  if($action==='discard'){unset($_SESSION['ff_preview']);redirect('Vorschau verworfen.','?view=import');}
  if($action==='import'){
   $preview=$_SESSION['ff_preview']??null;
   if(!$preview||!is_string($_POST['preview_token']??null)||!hash_equals($preview['token'],$_POST['preview_token'])||($_POST['confirmed']??'')!=='yes')throw new InvalidArgumentException('Bitte eine gültige Vorschau und erfolgreiche Finanzierung bestätigen.');
   [$added,$skipped]=ffImportRows($db,$preview['rows']);unset($_SESSION['ff_preview']);redirect("$added Einträge übernommen, $skipped bereits vorhanden.");
  }
  if($action==='manual'){
   if(($_POST['confirmed']??'')!=='yes')throw new InvalidArgumentException('Bitte bestätige die abgewickelte Startnext-Unterstützung.');
   [$a,$s]=ffImportRows($db,[$_POST]);redirect($a?'Unterstützung angelegt.':'Referenz bereits vorhanden, nichts geändert.');
  }
  if($action==='issue'){
   $r=ffFind($db,$postedId);ffTemplate($r);ffIssue($db,$postedId);redirect('Zertifikatsnummer vergeben. PDF oder Druckansicht öffnen.','?id='.$postedId);
  }
  if($action==='details'){
   $r=ffFind($db,$postedId);
   $n=ffNormalize(['reference'=>$r['reference'],'package'=>$r['package'],'name'=>$_POST['name']??'','email'=>$_POST['email']??'','lang'=>$_POST['lang']??'']);
   $notes=ffField($_POST['notes']??'',600,'Notiz');
   if($r['name']!==$n['name']||$r['lang']!==$n['lang']){
    if($r['certificate_number']!==null)throw new InvalidArgumentException('Name oder Sprache nach Ausstellung gesperrt. Zertifikat zuerst bewusst zurücksetzen.');
   }
   $db->prepare('UPDATE founders_supporters SET name=?,email=?,lang=?,notes=? WHERE id=?')->execute([$n['name'],$n['email'],$n['lang'],$notes,$postedId]);
   redirect('Details gespeichert.','?id='.$postedId);
  }
  if($action==='reset_certificate'){
   $r=ffFind($db,$postedId);
   if(($_POST['confirmed']??'')!=='yes'||$r['state']!=='active')throw new InvalidArgumentException('Rücksetzung bestätigen.');
   // Retain issued identifier and date; allow correcting the existing certificate, not issuing a new number.
   $n=ffNormalize(['reference'=>$r['reference'],'package'=>$r['package'],'name'=>$_POST['name']??'','email'=>$r['email'],'lang'=>$_POST['lang']??'']);
   $db->beginTransaction();$db->prepare('UPDATE founders_supporters SET name=?,lang=? WHERE id=?')->execute([$n['name'],$n['lang'],$postedId]);
   $db->prepare("UPDATE founders_fulfilment SET state='open',completed_at=NULL WHERE supporter_id=? AND task='certificate'")->execute([$postedId]);$db->commit();
   redirect('Zertifikat korrigiert, Nummer und Datum bleiben gleich; Übergabe wieder offen.','?id='.$postedId);
  }
  if($action==='task'){
   $r=ffFind($db,$postedId);if($r['state']!=='active')throw new InvalidArgumentException('Stornierter Eintrag.');
   $key=ffField($_POST['task']??'',60,'Aufgabe');$state=ffField($_POST['state']??'',10,'Status');$due=ffField($_POST['due_date']??'',10,'Termin');
   if(!in_array($key,$packages[$r['package']][2],true)||!in_array($state,['open','done','waived'],true))throw new InvalidArgumentException('Ungültige Aufgabe.');
   if($key==='certificate'&&$state==='done'&&$r['certificate_number']===null)throw new InvalidArgumentException('Zuerst Zertifikat ausstellen.');
   if($due!==''&&(!preg_match('/^\d{4}-\d{2}-\d{2}$/D',$due)||!checkdate((int)substr($due,5,2),(int)substr($due,8,2),(int)substr($due,0,4))))throw new InvalidArgumentException('Termin ungültig.');
   $db->prepare('UPDATE founders_fulfilment SET state=?,completed_at=?,due_date=? WHERE supporter_id=? AND task=?')->execute([$state,$state==='open'?null:time(),$due===''?null:$due,$postedId,$key]);
   redirect('Aufgabe aktualisiert.','?id='.$postedId);
  }
  if($action==='publication'){
   $r=ffFind($db,$postedId);if(!ffPublicEligible($r))throw new InvalidArgumentException('Keine Veröffentlichung für dieses Paket.');
   $display=ffField($_POST['public_name']??'',160,'Öffentlicher Name');$note=ffField($_POST['consent_note']??'',500,'Einwilligungsnachweis');
   $publish=($_POST['publish']??'')==='yes';$consent=($_POST['consent']??'')==='yes';
   if($publish&&(!$consent||$display===''||$note===''))throw new InvalidArgumentException('Name, dokumentierte Zustimmung zur Veröffentlichung von Name und Paket sowie Freigabe sind nötig.');
   // Store proof only when explicitly confirmed. Unchecking consent revokes publication.
   $db->prepare('UPDATE founders_supporters SET public_name=?,consent_note=?,consent_at=?,publish=? WHERE id=?')->execute([$consent?$display:'',$consent?$note:'',$consent?($r['consent_at']??time()):null,$publish?1:0,$postedId]);
   redirect('Freigabe gespeichert.','?id='.$postedId);
  }
  if($action==='cancel'){
   if(($_POST['confirmed']??'')!=='yes')throw new InvalidArgumentException('Bitte Stornierung bestätigen.');
   $db->prepare("UPDATE founders_supporters SET state='cancelled',publish=0 WHERE id=?")->execute([$postedId]);redirect('Als storniert markiert; keine Auszahlung oder Rückzahlung ausgelöst.','?id='.$postedId);
  }
  if($action==='delete'){
   if(($_POST['confirmed']??'')!=='yes')throw new InvalidArgumentException('Bitte Löschung bestätigen.');
   $db->prepare('DELETE FROM founders_supporters WHERE id=?')->execute([$postedId]);redirect('Datensatz und Aufgaben gelöscht. Startnext bleibt unverändert.');
  }
  throw new InvalidArgumentException('Unbekannte Aktion.');
 }
 if(($_GET['download']??'')==='template')csvDownload('tennilo-startnext-import.csv',['reference','name','email','package','lang'],[]);
 if(($_GET['download']??'')==='csv'){
  $rows=$db->query('SELECT reference,name,email,package,lang,state,certificate_number,issued_at FROM founders_supporters ORDER BY id')->fetchAll(PDO::FETCH_NUM);
  csvDownload('tennilo-founders-abwicklung.csv',['Referenz','Name','E-Mail','Paketcode','Sprache','Status','Zertifikatsnummer','Ausgestellt (Unix-Zeit)'],$rows);
 }
 if(($_GET['download']??'')==='public'){
  $rows=array_map(static fn($r)=>[$r['public_name'],ffPackages()[$r['package']][0]],ffPublicRows($db));csvDownload('tennilo-founders-freigegebene-namen.csv',['Name','Paket'],$rows);
 }
 if(in_array($_GET['download']??'',['pdf','print'],true)){
  $r=ffFind($db,$id);if($r['state']!=='active'||empty($r['certificate_number']))throw new InvalidArgumentException('Kein ausgestelltes Zertifikat.');
  if($_GET['download']==='pdf'){
   $pdf=ffCertificatePdf($r);header('Content-Type: application/pdf');header('Content-Disposition: attachment; filename="'.e($r['certificate_number']).'.pdf"');header('Content-Length: '.strlen($pdf));echo $pdf;exit;
  }
  ffTemplate($r);$image='/assets/founders-certificates/'.$r['lang'].'-'.$r['package'].'.jpg';$date=gmdate($r['lang']==='de'?'d.m.Y':'Y-m-d',(int)$r['issued_at']);
  ?><!doctype html><html lang="<?=e($r['lang'])?>"><meta charset="utf-8"><title><?=e($r['certificate_number'])?></title><style>@page{size:A4 landscape;margin:0}*{box-sizing:border-box}body{margin:0;background:#e0e5e5;font-family:system-ui}.notice{padding:12px;text-align:center}.sheet{position:relative;width:min(100vw,1122px);aspect-ratio:841.89/595.28;margin:auto;background:#fff;container-type:inline-size}.bg{position:absolute;width:100%;height:100%}.name{position:absolute;left:6.65%;top:41.4%;width:55%;max-height:15%;font-family:Georgia,serif;font-size:clamp(12px,3.2cqw,42px);color:#092b36;overflow-wrap:anywhere;line-height:1.1}.meta{position:absolute;top:83.2%;font-size:1.45cqw;color:#092b36;font-weight:bold}.number{left:6.65%}.date{left:41%}@media print{body{background:#fff}.notice{display:none}.sheet{width:297mm;height:210mm;margin:0;print-color-adjust:exact;-webkit-print-color-adjust:exact}}</style><div class="notice">Im Browser drucken / als PDF speichern · A4 quer · Kopf- und Fußzeilen deaktivieren. <a href="/admin/founders.php?id=<?=$id?>">Zurück</a></div><div class="sheet"><img class="bg" src="<?=e($image)?>" alt=""><div class="name" style="font-size:<?=min(3.8,max(1.5,80/max(1,strlen($r['name']))))?>cqw"><?=e($r['name'])?></div><div class="meta number"><?=e($r['certificate_number'])?></div><div class="meta date"><?=e($date)?></div></div></html><?php exit;
 }
}catch(InvalidArgumentException $ex){$error=$ex->getMessage();if($id===0&&isset($postedId))$id=$postedId;}
catch(Throwable $ex){if($db->inTransaction())$db->rollBack();error_log('Founders fulfilment operation failed');$error='Vorgang konnte nicht abgeschlossen werden. Bitte erneut prüfen.';}
$view=(string)($_GET['view']??'list');if(($_POST['action']??'')==='preview')$view='import';if($id>0)$view='detail';
$stats=$db->query("SELECT COUNT(*) total,SUM(CASE WHEN state='active' THEN 1 ELSE 0 END) active,SUM(CASE WHEN state='active' AND certificate_number IS NOT NULL THEN 1 ELSE 0 END) issued,SUM(CASE WHEN state='active' AND package='free' THEN 1 ELSE 0 END) free FROM founders_supporters")->fetch();
$open=(int)$db->query("SELECT COUNT(*) FROM founders_fulfilment t JOIN founders_supporters s ON s.id=t.supporter_id WHERE s.state='active' AND t.state='open'")->fetchColumn();
$public=ffPublicRows($db);
?>
<!doctype html><html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Founders · TENNILO Backoffice</title><style>
:root{--ink:#092b36;--muted:#56717b;--gold:#ad7c27;--bg:#f2f7f5;--line:#dce8e6}*{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--ink);font:15px/1.5 system-ui,-apple-system,sans-serif}a{color:#086b70}header{background:#092b36;color:white;padding:16px 24px;display:flex;align-items:center;gap:24px;flex-wrap:wrap}header a{color:white}header img{width:190px;height:auto;background:#f7f4ed;padding:7px;border-radius:5px}main{max-width:1220px;margin:auto;padding:26px 18px 70px}h1{margin:0 0 8px;font-size:clamp(25px,4vw,36px)}h2{font-size:21px;margin:0 0 14px}h3{font-size:16px}p{margin:6px 0 13px}.muted,small{color:var(--muted)}.panel{background:white;border:1px solid var(--line);padding:22px;border-radius:12px;margin:18px 0}.stats{display:grid;grid-template-columns:repeat(4,1fr);gap:10px}.stat{padding:15px;background:white;border:1px solid var(--line);border-radius:10px}.stat strong{display:block;font-size:27px}.nav{display:flex;gap:8px;flex-wrap:wrap;margin:20px 0}.nav a,button,.btn{display:inline-block;background:var(--ink);color:#fff;border:0;padding:10px 14px;border-radius:7px;text-decoration:none;font:inherit;font-weight:700;cursor:pointer}.nav a{background:white;color:var(--ink);border:1px solid var(--line)}.nav a[aria-current=page]{border-color:var(--gold);box-shadow:inset 0 -3px var(--gold)}.btn.secondary,button.secondary{background:#e7f0ed;color:var(--ink)}button.danger{background:#963e33}input:not([type=checkbox]),select,textarea{border:1px solid #aac2bf;border-radius:6px;padding:9px;font:inherit;min-width:0;width:100%;background:white;color:var(--ink)}label{display:block;font-weight:650;margin:5px 0 10px}label small{font-weight:400}.grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px 18px}.check{display:flex;align-items:flex-start;gap:8px;font-weight:400}.check input{margin-top:4px}.actions{display:flex;flex-wrap:wrap;gap:10px;align-items:center}.scroll{overflow-x:auto}table{border-collapse:collapse;width:100%;font-size:14px}th,td{padding:10px 8px;text-align:left;border-bottom:1px solid var(--line);vertical-align:top}th{color:var(--muted);white-space:nowrap}.status{font-size:12px;background:#e7f0ed;padding:3px 7px;border-radius:8px}.msg{padding:13px;border-left:4px solid #087c74;background:#e3f2eb}.err{padding:13px;border-left:4px solid #ad4035;background:#fff0ed}.task{display:grid;grid-template-columns:minmax(150px,1fr) 140px 150px auto;gap:10px;align-items:end;border-top:1px solid var(--line);padding:10px 0}.task label{margin:0;font-size:12px}.task strong{align-self:center;font-size:14px}.task button{font-size:13px}.pill{color:#88621d;font-weight:750}.warning{border-left:4px solid #ad7c27;padding:10px;background:#f9f3e6}details{margin:10px 0}summary{cursor:pointer;font-weight:750}a:focus-visible,button:focus-visible,input:focus-visible,select:focus-visible{outline:3px solid #b88a3b;outline-offset:2px}@media(max-width:700px){.stats{grid-template-columns:repeat(2,1fr)}.grid{grid-template-columns:1fr}.task{grid-template-columns:1fr 1fr}.task strong{grid-column:1/-1}.panel{padding:15px}header{gap:12px;padding:12px}}
</style></head><body><header><img src="/assets/tennilo-founders-tf12-light.png" alt="TENNILO Founders"><a href="/admin/">← Backoffice</a><a href="/founders-admin.php">Interessentenliste</a><a href="/founders-mail.php">E-Mail-Verwaltung</a></header><main>
<h1>Founders-Verwaltung</h1><p class="muted">Abwicklung bestätigter Startnext-Unterstützungen. Keine Zahlungen, keine automatischen Rundmails und keine Übernahme in den Newsletter.</p>
<div class="stats"><div class="stat"><strong><?=(int)$stats['active']?></strong>Aktive Einträge</div><div class="stat"><strong><?=(int)$stats['free']?></strong>Freie Unterstützungen</div><div class="stat"><strong><?=(int)$stats['issued']?></strong>Zertifikate ausgestellt</div><div class="stat"><strong><?=$open?></strong>Offene Aufgaben</div></div>
<nav class="nav"><a href="/admin/founders.php" <?=$view==='list'?'aria-current="page"':''?>>Übersicht</a><a href="?view=import" <?=$view==='import'?'aria-current="page"':''?>>Startnext-Import</a><a href="?view=add" <?=$view==='add'?'aria-current="page"':''?>>Manuell hinzufügen</a><a href="?view=public" <?=$view==='public'?'aria-current="page"':''?>>Öffentliche Liste (<?=count($public)?>)</a></nav>
<?php if($flash!==''):?><p class="msg" role="status"><?=e($flash)?></p><?php endif;if($error!==''):?><p class="err" role="alert"><?=e($error)?></p><?php endif;?>
<?php if($view==='import'):?>
<section class="panel"><h2>Startnext-Export importieren</h2><p>Nach erfolgreicher Finanzierung die Excel-Unterstützerliste aus Startnext als CSV UTF-8 speichern. Eine Zeile je Dankeschön oder freier Unterstützung; bei mehreren Dankeschöns je Bestellung eindeutige Referenzen wie 12345-1 und 12345-2 verwenden. Die Datei darf nur erfolgreich abgewickelte Einträge enthalten. Keine Adressen oder Bankdaten erforderlich.</p><p><a href="?download=template">Leere CSV-Vorlage herunterladen</a></p>
<form method="post" enctype="multipart/form-data"><?=hidden('preview')?><label>CSV-Datei <input type="file" name="file" accept=".csv,text/csv,text/plain"></label><label>Alternativ CSV-Text einfügen <textarea name="csv" rows="4" placeholder="reference;name;email;package;lang"></textarea></label>
<details><summary>Spaltenzuordnung (bei abweichenden Überschriften)</summary><div class="grid"><?php foreach(['reference'=>'Referenz (Pflicht)','name'=>'Name (Pflichtspalte)','email'=>'E-Mail (optional)','package'=>'Paket (Pflicht)','lang'=>'Sprache de/en (optional)'] as $key=>$label):?><label><?=e($label)?><input name="column_<?=e($key)?>" placeholder="Exakte Spaltenüberschrift"></label><?php endforeach;?></div></details>
<p><small>Pakete: Codes FB01–FB05, FC01–FC04 oder exakte Paketnamen. Freie Beiträge: free. Max. 500 Zeilen / 1 MB. Wiederholte Referenzen werden beim Import nicht überschrieben.</small></p><button>Vorschau prüfen</button></form></section>
<?php if(isset($_SESSION['ff_preview'])):$preview=$_SESSION['ff_preview'];$existing=$db->prepare('SELECT 1 FROM founders_supporters WHERE reference=?');?>
<section class="panel"><h2>Importvorschau: <?=count($preview['rows'])?> Einträge</h2><div class="scroll"><table><thead><tr><th>Referenz</th><th>Name</th><th>E-Mail</th><th>Paket</th><th>Sprache</th><th>Aktion</th></tr></thead><tbody><?php foreach($preview['rows'] as $r):$existing->execute([$r['reference']]);?><tr><td><?=e($r['reference'])?></td><td><?=e($r['name'])?></td><td><?=e($r['email'])?></td><td><?=e($packages[$r['package']][0])?></td><td><?=e($r['lang'])?></td><td><?=$existing->fetchColumn()?'Überspringen':'Neu'?></td></tr><?php endforeach;?></tbody></table></div>
<form method="post"><?=hidden('import')?><input type="hidden" name="preview_token" value="<?=e($preview['token'])?>"><label class="check"><input type="checkbox" name="confirmed" value="yes" required> Ich habe die erfolgreiche Finanzierung und Abwicklung dieser Startnext-Einträge geprüft.</label><button>Bestätigt importieren</button></form><form method="post" style="margin-top:10px"><?=hidden('discard')?><button class="secondary">Vorschau verwerfen</button></form><small>Die Vorschau verfällt nach 15 Minuten.</small></section><?php endif;?>
<?php elseif($view==='add'):?>
<section class="panel"><h2>Startnext-Unterstützung einzeln erfassen</h2><form method="post"><?=hidden('manual')?><div class="grid"><label>Startnext-Referenz *<input name="reference" required maxlength="150"></label><label>Paket *<select name="package"><?=options($packages,'fb01')?></select></label><label>Name (bei Dankeschön erforderlich)<input name="name" maxlength="160"></label><label>E-Mail (optional)<input name="email" type="email"></label><label>Zertifikatssprache<select name="lang"><option value="de">Deutsch</option><option value="en">English</option></select></label></div><label class="check"><input type="checkbox" name="confirmed" value="yes" required> Erfolgreich abgewickelte Startnext-Unterstützung geprüft.</label><button>Speichern</button></form></section>
<?php elseif($view==='public'):?>
<section class="panel"><h2>Öffentlich freigegebene Founders-Namen</h2><p>Nur aktive Einträge mit ausdrücklicher, dokumentierter Zustimmung zur Veröffentlichung von Name und Paket werden gezeigt. Widerruf oder Stornierung entfernt den Eintrag. Beträge, E-Mail-Adressen und Referenzen werden nicht veröffentlicht.</p><div class="actions"><a class="btn" href="/founders/supporters.php" target="_blank" rel="noopener">Öffentliche Liste öffnen</a><a class="btn secondary" href="?download=public">Freigabeliste als CSV</a></div><div class="scroll"><table><thead><tr><th>Name</th><th>Paket</th></tr></thead><tbody><?php foreach($public as $r):?><tr><td><?=e($r['public_name'])?></td><td><?=e($packages[$r['package']][0])?></td></tr><?php endforeach;if(!$public):?><tr><td colspan="2">Noch keine Freigaben.</td></tr><?php endif;?></tbody></table></div></section>
<?php elseif($view==='detail'):
try{$r=ffFind($db,$id);}catch(Throwable $ex){$r=null;}
if($r):$q=$db->prepare('SELECT * FROM founders_fulfilment WHERE supporter_id=? ORDER BY rowid');$q->execute([$id]);$list=$q->fetchAll();?>
<p><a href="/admin/founders.php">← Zurück zur Übersicht</a></p><section class="panel"><h2><?=e($r['name']?:'Freie Unterstützung')?> <span class="status"><?=e($r['state']==='active'?'Aktiv':'Storniert')?></span></h2><p><span class="pill"><?=e($packages[$r['package']][0])?></span> · Startnext-Referenz <?=e($r['reference'])?></p>
<form method="post"><?=hidden('details',$id)?><div class="grid"><label>Name<input name="name" value="<?=e($r['name'])?>" maxlength="160" <?=$r['certificate_number']?'readonly':''?>></label><label>E-Mail<input name="email" type="email" value="<?=e($r['email'])?>"></label><label>Sprache<select name="lang" <?=$r['certificate_number']?'disabled':''?>><option value="de" <?=$r['lang']==='de'?'selected':''?>>Deutsch</option><option value="en" <?=$r['lang']==='en'?'selected':''?>>English</option></select><?php if($r['certificate_number']):?><input type="hidden" name="lang" value="<?=e($r['lang'])?>"><?php endif;?></label><label>Interne Notiz<input name="notes" maxlength="600" value="<?=e($r['notes'])?>"></label></div><button>Details speichern</button></form></section>
<?php if($r['state']==='active'&&$r['package']!=='free'):?>
<section class="panel"><h2>Zertifikat</h2><?php if(!$r['certificate_number']):?><p>Die Nummer wird einmalig bei der Ausstellung vergeben. Die Übergabe bleibt separat offen.</p><form method="post"><?=hidden('issue',$id)?><button>Zertifikat ausstellen</button></form><?php else:?><p><strong><?=e($r['certificate_number'])?></strong> · <?=e(gmdate('d.m.Y',(int)$r['issued_at']))?></p><div class="actions"><a class="btn" href="?id=<?=$id?>&amp;download=pdf">PDF herunterladen</a><a class="btn secondary" href="?id=<?=$id?>&amp;download=print" target="_blank" rel="noopener">Druckansicht (Unicode)</a></div><details><summary>Name oder Sprache auf Zertifikat korrigieren</summary><form method="post"><?=hidden('reset_certificate',$id)?><div class="grid"><label>Name<input name="name" value="<?=e($r['name'])?>" required maxlength="160"></label><label>Sprache<select name="lang"><option value="de" <?=$r['lang']==='de'?'selected':''?>>Deutsch</option><option value="en" <?=$r['lang']==='en'?'selected':''?>>English</option></select></label></div><label class="check"><input type="checkbox" name="confirmed" value="yes" required> Korrektur bestätigt; Nummer und Datum bleiben erhalten.</label><button>Korrektur speichern</button></form></details><?php endif;?><small>PDF direkt für westeuropäische Zeichen; bei anderen Schriftzeichen bitte Browser-Druckansicht nutzen. Keine automatische Zustellung.</small></section>
<section class="panel"><h2>Gegenleistungen & Aufgaben</h2><p>Jede Aufgabe separat abhaken. Bei Diamond: Gespräche je 30 Minuten; Platinum: 20 Minuten. Lizenzdauer: Club 1 Jahr, Club Plus / Partner 3 Jahre, Premium Partner 5 Jahre ab nutzbarer Freigabe.</p><?php foreach($list as $t):?><form class="task" method="post"><?=hidden('task',$id)?><input type="hidden" name="task" value="<?=e($t['task'])?>"><strong><?=e($tasks[$t['task']]??$t['task'])?></strong><label>Status<select name="state"><?php foreach(['open'=>'Offen','done'=>'Erledigt','waived'=>'Entfällt (dokumentieren)'] as $key=>$label):?><option value="<?=e($key)?>" <?=$t['state']===$key?'selected':''?>><?=e($label)?></option><?php endforeach;?></select></label><label>Termin (optional)<input type="date" name="due_date" value="<?=e($t['due_date'])?>"></label><button class="secondary">Speichern</button></form><?php endforeach;?></section>
<?php endif;if(ffPublicEligible($r)):?><section class="panel"><h2>Namens- und Partnerfreigabe</h2><p>Nur ausdrücklich für die Veröffentlichung von Name und Paket erteilte Einwilligungen erfassen. Bei Widerruf Häkchen entfernen und speichern.</p><form method="post"><?=hidden('publication',$id)?><div class="grid"><label>Öffentlicher Name<input name="public_name" maxlength="160" value="<?=e($r['public_name'])?>"></label><label>Nachweis (Datum, Kanal, Fundstelle)<input name="consent_note" maxlength="500" value="<?=e($r['consent_note'])?>"></label></div><label class="check"><input type="checkbox" name="consent" value="yes" <?=$r['consent_at']?'checked':''?>> Zustimmung zu Name und Paket ist nachweislich vorhanden.</label><label class="check"><input type="checkbox" name="publish" value="yes" <?=$r['publish']?'checked':''?>> Auf der öffentlichen Founders-Liste veröffentlichen.</label><button>Freigabe speichern</button></form></section><?php endif;?>
<section class="panel"><h2>Stornierung & Datenpflege</h2><p class="warning">Nur interner Status, keine Zahlung oder Rückzahlung. Vor Löschung gesetzliche Aufbewahrungspflichten prüfen. Eine Löschung verändert Startnext nicht.</p><?php if($r['state']==='active'):?><form method="post"><?=hidden('cancel',$id)?><label class="check"><input type="checkbox" name="confirmed" value="yes" required> Stornierung / Rückabwicklung auf Startnext geprüft.</label><button class="secondary">Als storniert markieren</button></form><?php endif;?><details><summary>Datensatz löschen</summary><form method="post"><?=hidden('delete',$id)?><label class="check"><input type="checkbox" name="confirmed" value="yes" required> Ich habe Aufbewahrungspflichten geprüft und bestätige die dauerhafte Löschung.</label><button class="danger">Datensatz und Aufgaben löschen</button></form></details></section>
<?php else:?><p class="err">Eintrag nicht gefunden.</p><?php endif;?>
<?php else:
$search=trim((string)($_GET['q']??''));$filter=(string)($_GET['filter']??'all');$pack=(string)($_GET['package']??'');$page=max(1,min(100000,(int)($_GET['page']??1)));$where=[];$args=[];
if($search!==''){$where[]='(s.name LIKE ? OR s.email LIKE ? OR s.reference LIKE ? OR s.certificate_number LIKE ?)';$needle='%'.substr($search,0,160).'%';$args=array_merge($args,[$needle,$needle,$needle,$needle]);}
if(isset($packages[$pack])){$where[]='s.package=?';$args[]=$pack;}
if($filter==='active'||$filter==='cancelled'){$where[]='s.state=?';$args[]=$filter;}
if($filter==='open')$where[]="s.state='active' AND EXISTS(SELECT 1 FROM founders_fulfilment t WHERE t.supporter_id=s.id AND t.state='open')";
$condition=$where?' WHERE '.implode(' AND ',$where):'';$q=$db->prepare('SELECT COUNT(*) FROM founders_supporters s'.$condition);$q->execute($args);$count=(int)$q->fetchColumn();$pages=max(1,(int)ceil($count/50));$page=min($page,$pages);
$q=$db->prepare("SELECT s.*,(SELECT COUNT(*) FROM founders_fulfilment t WHERE t.supporter_id=s.id AND t.state='open') AS open_tasks FROM founders_supporters s".$condition." ORDER BY s.id DESC LIMIT 50 OFFSET ".(($page-1)*50));$q->execute($args);$rows=$q->fetchAll();
?>
<section class="panel"><h2>Unterstützungen (<?=$count?>)</h2><div class="actions"><a class="btn" href="?view=import">Startnext importieren</a><a class="btn secondary" href="?view=add">Eintrag hinzufügen</a><a class="btn secondary" href="?download=csv">Geschützter CSV-Export</a></div><form method="get" style="margin:16px 0"><div class="grid"><label>Suche<input name="q" value="<?=e($search)?>" placeholder="Name, E-Mail, Referenz oder Zertifikat"></label><label>Paket<select name="package"><option value="">Alle</option><?=options($packages,$pack)?></select></label><label>Status<select name="filter"><?php foreach(['all'=>'Alle','active'=>'Aktiv','cancelled'=>'Storniert','open'=>'Offene Aufgaben'] as $k=>$label):?><option value="<?=e($k)?>" <?=$filter===$k?'selected':''?>><?=e($label)?></option><?php endforeach;?></select></label></div><button class="secondary">Filtern</button></form><div class="scroll"><table><thead><tr><th>Name / Referenz</th><th>Paket</th><th>Status</th><th>Zertifikat</th><th>Offen</th><th></th></tr></thead><tbody><?php foreach($rows as $r):?><tr><td><strong><?=e($r['name']?:'Freie Unterstützung')?></strong><br><small><?=e($r['reference'])?></small></td><td><?=e($packages[$r['package']][0])?></td><td><?=e($r['state']==='active'?'Aktiv':'Storniert')?></td><td><?=e($r['certificate_number']??'–')?></td><td><?=$r['state']==='active'?(int)$r['open_tasks']:'–'?></td><td><a href="?id=<?=(int)$r['id']?>">Öffnen →</a></td></tr><?php endforeach;if(!$rows):?><tr><td colspan="6">Noch keine passenden Einträge. Nach erfolgreicher Startnext-Abwicklung importieren.</td></tr><?php endif;?></tbody></table></div><div class="actions" style="margin-top:12px"><?php if($page>1):?><a href="?<?=e(http_build_query(['q'=>$search,'filter'=>$filter,'package'=>$pack,'page'=>$page-1]))?>">← Zurück</a><?php endif;?><small>Seite <?=$page?> von <?=$pages?></small><?php if($page<$pages):?><a href="?<?=e(http_build_query(['q'=>$search,'filter'=>$filter,'package'=>$pack,'page'=>$page+1]))?>">Weiter →</a><?php endif;?></div></section>
<?php endif;?><p class="muted"><small>Personenbezogene Daten nur zur Abwicklung verwenden, geschützt aufbewahren und nach Ende des Zwecks bzw. gesetzlicher Aufbewahrungsfrist löschen. Keine Newsletter-Einwilligung aus Startnext ableiten.</small></p></main></body></html>
