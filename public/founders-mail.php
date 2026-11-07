<?php
declare(strict_types=1);
/* TENNILO Founders: opt-in-only newsletter management. All sends require an admin POST. */
header('Content-Type: text/html; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
header('X-Robots-Tag: noindex, nofollow');
header('Referrer-Policy: no-referrer');
header('Cache-Control: no-store');
header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");
$private = dirname(__DIR__).'/tennilo-founders-config.php';
if (!is_file($private)) { http_response_code(503); exit('Konfiguration nicht verfügbar.'); }
$cfg = require $private;
if (!is_array($cfg) || ($cfg['enabled']??false)!==true || empty($cfg['database']) || empty($cfg['key'])) {
 http_response_code(503); exit('Founders-Verwaltung nicht verfügbar.');
}
require_once __DIR__.'/founders-email-template.php';
session_name('tennilo_founders_portal_v2');
session_set_cookie_params(['httponly'=>true,'secure'=>true,'samesite'=>'Strict','path'=>'/']);
session_start();
if (empty($_SESSION['authenticated']) || (int)($_SESSION['auth_at']??0)<time()-8*3600) {
 $_SESSION=[];
 header('Location: /founders-admin.php',true,303); exit;
}
if (empty($_SESSION['csrf'])) $_SESSION['csrf']=bin2hex(random_bytes(24));
try {
 $db=new PDO('sqlite:'.$cfg['database'],null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
 $db->exec('PRAGMA busy_timeout=5000');
 $db->exec("CREATE TABLE IF NOT EXISTS founders_campaigns (
  id INTEGER PRIMARY KEY AUTOINCREMENT, subject TEXT NOT NULL, body TEXT NOT NULL,
  topic TEXT NOT NULL, state TEXT NOT NULL DEFAULT 'draft', created_at INTEGER NOT NULL, updated_at INTEGER NOT NULL)");
 $db->exec("CREATE TABLE IF NOT EXISTS founders_deliveries (
  campaign_id INTEGER NOT NULL, subscriber_id INTEGER NOT NULL, recipient_key TEXT NOT NULL,
  state TEXT NOT NULL DEFAULT 'pending', attempted_at INTEGER,
  PRIMARY KEY (campaign_id,subscriber_id))");
} catch(Throwable $e) { error_log('Founders campaigns setup failed: '.$e->getMessage()); http_response_code(503); exit('Datenbank vorübergehend nicht verfügbar.'); }
function h(mixed $value): string { return htmlspecialchars((string)$value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); }
function finish(string $message,int $id=0): never {
 header('Location: /founders-mail.php?'.($id>0?'campaign='.$id.'&':'').'info='.rawurlencode($message),true,303); exit;
}
function page(string $contents): never {
 echo '<!doctype html><html lang="de"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex"><title>TENNILO Founders · E-Mail-Verwaltung</title><style>
 :root{color-scheme:light}*{box-sizing:border-box}body{margin:0;background:#eef3ef;color:#092b36;font:15px/1.55 system-ui}main{max-width:1050px;margin:36px auto;padding:0 17px}.panel{background:white;border:1px solid #d9e5df;border-radius:14px;padding:24px;margin-bottom:17px}h1{font-size:29px}h2{font-size:21px;margin:0 0 15px}p{margin:8px 0 16px}a{color:#007b7c}label{display:block;font-weight:700;margin:13px 0 6px}input[type=text],textarea,select{font:inherit;border:1px solid #b5c9c3;border-radius:8px;padding:11px;width:100%;background:white;color:#092b36}textarea{min-height:180px}button{background:#092b36;color:#fff;border:0;border-radius:8px;padding:11px 16px;font:inherit;cursor:pointer;margin:8px 8px 8px 0}button:hover{background:#19505b}.muted,small{color:#526e70}.warning{border-left:4px solid #eb8959;padding:10px 15px;background:#fff8f2}.success{background:#e8f5e7;padding:12px;border-radius:8px}.row{display:flex;gap:16px;flex-wrap:wrap;align-items:center}.row>*{flex:1}.topic{font-weight:700}table{border-collapse:collapse;width:100%}th,td{border-bottom:1px solid #d9e5df;padding:10px;text-align:left}code{font-size:12px} .danger{color:#9c482b} @media(max-width:640px){.panel{padding:17px}table{font-size:12px}}
 </style><main><p><a href="/founders-admin.php">← Zur Founders-Verwaltung</a></p><h1>Founders · E-Mail-Verwaltung</h1>'.$contents.'</main></html>'; exit;
}
function csrf(): string { return '<input type="hidden" name="csrf" value="'.h($_SESSION['csrf']).'">'; }
function safeEmail(string $email): bool { return (bool)filter_var($email,FILTER_VALIDATE_EMAIL) && strlen($email)<=254 && !str_contains($email,"\r") && !str_contains($email,"\n"); }
function subjectHeader(string $subject): string {
 if (function_exists('mb_encode_mimeheader')) return mb_encode_mimeheader($subject,'UTF-8','B',"\r\n");
 return '=?UTF-8?B?'.base64_encode($subject).'?=';
}
function sendMail(string $to,string $subject,string $message,?string $unsubscribeUrl=null): bool {
 if (!safeEmail($to)) return false;
 $headers=[
  'From: TENNILO Founders <founders@tennilo.de>',
  'Reply-To: founders@tennilo.de',
  'MIME-Version: 1.0'
 ];
 $plain=rtrim($message);
 if ($unsubscribeUrl!==null) {
  $plain.="\n\n---\nTENNILO Founders · Ein Projekt von Tennis-Schiri\n";
  $plain.="Du erhaeltst diese Nachricht, weil du Informationen zu diesem Thema angefordert hast.\n";
  $plain.="Abmelden: ".$unsubscribeUrl."\nKontakt: founders@tennilo.de";
  $headers[]='List-Unsubscribe: <'.$unsubscribeUrl.'>';
 } else {
  $plain.="\n\n---\nTENNILO Founders · Ein Projekt von Tennis-Schiri\n";
  $plain.="Abmelden: https://tennilo.de/founders.php?action=unsubscribe-request\nKontakt: founders@tennilo.de";
 }
 $html=tennilo_email_html($subject,$message,$unsubscribeUrl);
 $boundary='tennilo_'.bin2hex(random_bytes(16));
 $headers[]='Content-Type: multipart/alternative; boundary="'.$boundary.'"';
 $mime='--'.$boundary."\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: quoted-printable\r\n\r\n".quoted_printable_encode($plain)."\r\n";
 $mime.='--'.$boundary."\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: quoted-printable\r\n\r\n".quoted_printable_encode($html)."\r\n";
 $mime.='--'.$boundary."--\r\n";
 return mail($to,subjectHeader($subject),$mime,implode("\r\n",$headers),'-ffounders@tennilo.de');
}
function opted(string $encoded,string $topic): bool {
 $topics=json_decode($encoded,true);
 return is_array($topics) && in_array($topic,$topics,true);
}
function recipients(PDO $db,string $topic): array {
 $rows=$db->query('SELECT id,email,interests FROM subscribers WHERE confirmed_at IS NOT NULL ORDER BY id')->fetchAll();
 return array_values(array_filter($rows,static fn($r)=>opted((string)$r['interests'],$topic)&&safeEmail((string)$r['email'])));
}
function footer(string $body,int $id,string $email,string $key): array {
 $sig=hash_hmac('sha256',$id.'|'.$email,$key);
 $url='https://tennilo.de/founders.php?action=unsubscribe-signed&id='.$id.'&sig='.$sig;
 return [rtrim($body),$url];
}
$topics=['beta'=>'Betatests','founders'=>'Founders-Angebote','crowdfunding'=>'Startnext-Crowdfunding'];
$method=$_SERVER['REQUEST_METHOD']??'GET';
if($method==='POST'){
 if(!hash_equals((string)$_SESSION['csrf'],(string)($_POST['csrf']??''))){http_response_code(403);page('<section class="panel">Sitzung abgelaufen oder Anfrage ungültig.</section>');}
 $action=(string)($_POST['action']??'');
 $id=filter_var($_POST['campaign']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]])?:0;
 $now=time();
 if($action==='create'||$action==='save'){
  $subject=trim((string)($_POST['subject']??''));
  $body=trim((string)($_POST['body']??''));
  $topic=(string)($_POST['topic']??'');
  if($subject===''||strlen($subject)>160||preg_match('/[\r\n]/',$subject)||$body===''||strlen($body)>10000||!array_key_exists($topic,$topics))
   page('<section class="panel warning">Bitte Betreff (maximal 160 Zeichen), Text (maximal 10.000 Zeichen) und gültiges Thema prüfen.</section>');
  if($action==='create'){
   $q=$db->prepare("INSERT INTO founders_campaigns(subject,body,topic,state,created_at,updated_at) VALUES(?,?,?,'draft',?,?)");
   $q->execute([$subject,$body,$topic,$now,$now]);
   finish('Entwurf gespeichert.',(int)$db->lastInsertId());
  }
  $q=$db->prepare("UPDATE founders_campaigns SET subject=?,body=?,topic=?,updated_at=? WHERE id=? AND state='draft'");
  $q->execute([$subject,$body,$topic,$now,$id]);
  finish($q->rowCount()?'Änderungen gespeichert.':'Nur unveröffentlichte Entwürfe können bearbeitet werden.',$id);
 }
 if(!$id)finish('Ungültige Kampagne.');
 $q=$db->prepare('SELECT * FROM founders_campaigns WHERE id=?');$q->execute([$id]);$campaign=$q->fetch();
 if(!$campaign)finish('Kampagne nicht gefunden.');
 if($action==='test'){
  if($campaign['state']!=='draft')finish('Testsendung nur vor Freigabe möglich.',$id);
  $sample=$campaign['body']."\n\n---\nTESTNACHRICHT – noch keine Aussendung an Wartelistenkontakte.\nIm echten Versand wird automatisch ein persönlicher Abmeldelink angehängt.";
  $ok=sendMail('founders@tennilo.de','[TEST] '.$campaign['subject'],$sample);
  finish($ok?'Testmail an founders@tennilo.de vom Server angenommen.':'Testmail wurde vom Server abgelehnt.',$id);
 }
 if($action==='test-one'){
  if($campaign['state']!=='draft')finish('Einzeltest nur aus einem Entwurf möglich.',$id);
  $email=strtolower(trim((string)($_POST['test_email']??'')));
  if(!safeEmail($email)||($_POST['test_confirmation']??'')!=='EINZELTEST')finish('Bitte eine gültige Testadresse und EINZELTEST eingeben.',$id);
  $q=$db->prepare('SELECT id,email,interests,confirmed_at FROM subscribers WHERE email=? AND confirmed_at IS NOT NULL');
  $q->execute([$email]);$recipient=$q->fetch();
  if(!$recipient||!opted((string)$recipient['interests'],(string)$campaign['topic']))
   finish('Kein bestätigter Founders-Eintrag mit Einwilligung zum gewählten Thema für diese Adresse vorhanden.',$id);
  [$message,$url]=footer($campaign['body'],(int)$recipient['id'],(string)$recipient['email'],(string)$cfg['key']);
  $ok=sendMail((string)$recipient['email'],'[EINZELTEST] '.$campaign['subject'],$message,$url);
  finish($ok?'Einzeltest an die ausgewählte Testadresse vom Mailserver angenommen (Zustellung prüfen).':'Einzeltest wurde vom Mailserver abgelehnt.',$id);
 }
 if($action==='approve'){
  if($campaign['state']!=='draft'||($_POST['confirmation']??'')!=='SENDEN')finish('Freigabe nicht durchgeführt.',$id);
  $people=recipients($db,$campaign['topic']);
  if(count($people)===0)finish('Keine bestätigten Kontakte mit passendem Interesse.',$id);
  try {
   $db->exec('BEGIN IMMEDIATE');
   $q=$db->prepare("INSERT OR IGNORE INTO founders_deliveries(campaign_id,subscriber_id,recipient_key,state) VALUES(?,?,?,'pending')");
   foreach($people as $person)$q->execute([$id,(int)$person['id'],hash_hmac('sha256',strtolower((string)$person['email']),$cfg['key'])]);
   $db->prepare("UPDATE founders_campaigns SET state='ready',updated_at=? WHERE id=? AND state='draft'")->execute([$now,$id]);
   $db->commit();
  }catch(Throwable $e){if($db->inTransaction())$db->rollBack();error_log('Founders approve failed: '.$e->getMessage());finish('Freigabe fehlgeschlagen.',$id);}
  finish('Versand freigegeben für '.count($people).' passende bestätigte Kontakte. Noch keine E-Mails verschickt.',$id);
 }
 if($action==='send'){
  if(!in_array($campaign['state'],['ready','sending'],true))finish('Versand nicht freigegeben.',$id);
  $sent=0;$failed=0;$skipped=0;
  for($i=0;$i<5;$i++){
   try {
    $db->exec('BEGIN IMMEDIATE');
    $q=$db->prepare("SELECT d.subscriber_id,d.recipient_key,s.email,s.interests,s.confirmed_at FROM founders_deliveries d LEFT JOIN subscribers s ON s.id=d.subscriber_id WHERE d.campaign_id=? AND d.state='pending' ORDER BY d.subscriber_id LIMIT 1");
    $q->execute([$id]);$r=$q->fetch();
    if(!$r){$db->commit();break;}
    $db->prepare("UPDATE founders_deliveries SET state='sending',attempted_at=? WHERE campaign_id=? AND subscriber_id=? AND state='pending'")->execute([time(),$id,(int)$r['subscriber_id']]);
    $db->prepare("UPDATE founders_campaigns SET state='sending',updated_at=? WHERE id=?")->execute([time(),$id]);
    $db->commit();
   }catch(Throwable $e){if($db->inTransaction())$db->rollBack();error_log('Founders queue claim failed: '.$e->getMessage());finish('Warteschlange konnte nicht verarbeitet werden.',$id);}
   $valid=$r['confirmed_at']!==null&&is_string($r['email'])&&safeEmail($r['email'])&&hash_equals((string)$r['recipient_key'],hash_hmac('sha256',strtolower($r['email']),$cfg['key']))&&opted((string)$r['interests'],$campaign['topic']);
   if(!$valid){
    $db->prepare("UPDATE founders_deliveries SET state='skipped' WHERE campaign_id=? AND subscriber_id=?")->execute([$id,(int)$r['subscriber_id']]);
    $skipped++;continue;
   }
   [$message,$url]=footer($campaign['body'],(int)$r['subscriber_id'],$r['email'],$cfg['key']);
   $ok=sendMail($r['email'],$campaign['subject'],$message,$url);
   $db->prepare("UPDATE founders_deliveries SET state=? WHERE campaign_id=? AND subscriber_id=? AND state='sending'")->execute([$ok?'accepted':'failed',$id,(int)$r['subscriber_id']]);
   if($ok)$sent++;else $failed++;
  }
  $q=$db->prepare("SELECT COUNT(*) FROM founders_deliveries WHERE campaign_id=? AND state='pending'");$q->execute([$id]);
  $remaining=(int)$q->fetchColumn();
  if($remaining===0) $db->prepare("UPDATE founders_campaigns SET state='finished',updated_at=? WHERE id=?")->execute([time(),$id]);
  finish("Chargenversand: $sent vom Server angenommen, $failed abgelehnt, $skipped übersprungen, $remaining noch ausstehend.",$id);
 }
 finish('Unbekannte Aktion.',$id);
}
$notice=isset($_GET['info'])?'<p class="success">'.h(substr((string)$_GET['info'],0,300)).'</p>':'';
$id=filter_var($_GET['campaign']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]])?:0;
if($id){
 $q=$db->prepare('SELECT * FROM founders_campaigns WHERE id=?');$q->execute([$id]);$c=$q->fetch();
 if(!$c)page('<section class="panel">Kampagne nicht gefunden.</section>');
 $q=$db->prepare('SELECT state,COUNT(*) AS n FROM founders_deliveries WHERE campaign_id=? GROUP BY state');$q->execute([$id]);
 $counts=[];foreach($q->fetchAll() as $r)$counts[$r['state']]=(int)$r['n'];
 $state=$c['state'];
 $n=count(recipients($db,$c['topic']));
 $body='<section class="panel"><h2>Kampagne #'.(int)$c['id'].'</h2>'.$notice.'<p><strong>Status:</strong> '.h($state).' · <strong>Thema:</strong> '.h($topics[$c['topic']]??$c['topic']).' · <strong>Aktuell passende bestätigte Kontakte:</strong> '.$n.'</p>';
 if($state==='draft'){
  $body.='<form method="post">'.csrf().'<input type="hidden" name="action" value="save"><input type="hidden" name="campaign" value="'.$id.'"><label>Betreff</label><input type="text" maxlength="160" name="subject" value="'.h($c['subject']).'" required><label>Thema / Empfängerkreis</label><select name="topic">';
  foreach($topics as $key=>$name)$body.='<option value="'.h($key).'"'.($c['topic']===$key?' selected':'').'>'.h($name).'</option>';
  $body.='</select><label>Nachricht (Nur Text)</label><textarea name="body" maxlength="10000" required>'.h($c['body']).'</textarea><small>Persönliche Abmeldelinks und Absenderangaben werden beim Versand automatisch ergänzt.</small><p><button type="submit">Entwurf speichern</button></p></form><form method="post">'.csrf().'<input type="hidden" name="action" value="test"><input type="hidden" name="campaign" value="'.$id.'"><button type="submit">Testmail nur an founders@tennilo.de</button></form>';
  $body.='<div class="warning"><strong>Sicherer Einzeltest:</strong> Nur an die unten eingetragene Adresse. Diese muss sich bereits auf der Warteliste bestätigt und dem ausgewählten Thema zugestimmt haben. Kein Versand an andere Personen, keine Kampagnenfreigabe. Entwurf vor dem Test speichern.</div><form method="post">'.csrf().'<input type="hidden" name="action" value="test-one"><input type="hidden" name="campaign" value="'.$id.'"><label>Bestätigte eigene Testadresse</label><input type="text" name="test_email" maxlength="254" placeholder="Eigene bestätigte Adresse" autocomplete="off" required><label>Zur Bestätigung EINZELTEST eintippen</label><input type="text" name="test_confirmation" autocomplete="off" required><p><button type="submit">Echte Nachricht mit Abmeldelink nur an diese Adresse senden</button></p></form>';
  $body.='<div class="warning"><strong>Versandfreigabe:</strong> Erst eine Testmail prüfen. Bei Freigabe wird eine feste Empfängerliste der zu diesem Zeitpunkt bestätigten Interessenten erstellt. Eine spätere Abmeldung wird vor jedem Sendeversuch erneut berücksichtigt.</div><form method="post">'.csrf().'<input type="hidden" name="action" value="approve"><input type="hidden" name="campaign" value="'.$id.'"><label>Zum Freigeben SENDEN eintippen</label><input type="text" name="confirmation" autocomplete="off" placeholder="SENDEN" required><button type="submit">Empfängerliste freigeben – noch nicht verschicken</button></form>';
 }else{
  $body.='<h3>'.h($c['subject']).'</h3><pre style="white-space:pre-wrap;font:inherit">'.h($c['body']).'</pre><p>Versandstatistik: '.h(json_encode($counts,JSON_UNESCAPED_UNICODE)).'</p><p class="muted">„Vom Server angenommen“ bestätigt keine Zustellung im Postfach. Bei Verbindungsabbruch können Einträge im Zustand „sending“ bleiben; diese werden zur Vermeidung unbemerkter Doppelversendungen nicht automatisch erneut versendet.</p>';
  if(in_array($state,['ready','sending'],true))$body.='<form method="post">'.csrf().'<input type="hidden" name="action" value="send"><input type="hidden" name="campaign" value="'.$id.'"><button type="submit">Nächste maximal 5 E-Mails versenden</button></form>';
 }
 $body.='</section>';
 page($body);
}
$all=$db->query('SELECT id,subject,topic,state,created_at FROM founders_campaigns ORDER BY id DESC LIMIT 50')->fetchAll();
$body='<section class="panel"><h2>Neuen E-Mail-Entwurf anlegen</h2>'.$notice.'<p>Nur Personen mit bestätigter Anmeldung und ausdrücklichem Interesse am gewählten Thema erhalten später eine Nachricht. Der Versand startet niemals automatisch.</p><form method="post">'.csrf().'<input type="hidden" name="action" value="create"><label>Betreff</label><input type="text" name="subject" maxlength="160" required><label>Interessengebiet</label><select name="topic">';
foreach($topics as $key=>$name)$body.='<option value="'.h($key).'">'.h($name).'</option>';
$body.='</select><label>Text der Nachricht</label><textarea name="body" maxlength="10000" required></textarea><p><button type="submit">Als Entwurf speichern</button></p></form></section>';
$body.='<section class="panel"><h2>Letzte Kampagnen</h2><table><thead><tr><th>Betreff</th><th>Thema</th><th>Status</th></tr></thead><tbody>';
foreach($all as $row)$body.='<tr><td><a href="?campaign='.(int)$row['id'].'">'.h($row['subject']).'</a></td><td>'.h($topics[$row['topic']]??$row['topic']).'</td><td>'.h($row['state']).'</td></tr>';
$body.='</tbody></table></section>';
page($body);
