<?php
declare(strict_types=1);
/* Separate general TENNILO newsletter opt-in & email-verified topic preferences.
   Data remain in the existing private Founders database. */
header('X-Content-Type-Options: nosniff');
header('X-Robots-Tag: noindex, nofollow');
header('Referrer-Policy: no-referrer');
header('Cache-Control: no-store');
header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");
function nlPage(string $title,string $body,int $status=200): never {
 http_response_code($status);header('Content-Type: text/html; charset=UTF-8');
 echo '<!doctype html><html lang="de"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>'.htmlspecialchars($title,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8').' · TENNILO</title><style>body{font:16px/1.6 system-ui,Arial,sans-serif;margin:0;color:#092b36;background:#f6f8f4}.wrap{max-width:680px;margin:7vh auto;background:white;padding:34px;border:1px solid #dae4e0;border-radius:17px;box-shadow:0 15px 55px #092b3610}h1{font-size:28px;line-height:1.2}p{margin:12px 0 18px}.sub{color:#42616a}label{display:block;margin:15px 0 8px;font-weight:650}input[type=email]{font:inherit;box-sizing:border-box;width:100%;padding:12px;border-radius:8px;border:1px solid #b3cac5}button{background:#092b36;color:#fff;border:0;border-radius:9px;padding:12px 20px;font:inherit;cursor:pointer}fieldset{border:1px solid #dae4e0;border-radius:12px;margin:20px 0;padding:15px}fieldset label{display:flex;gap:10px;font-weight:500}a{color:#007f82}small{color:#567579}.hidden{position:absolute;left:-9999px}</style><main class="wrap"><p><a href="/">← TENNILO</a></p><h1>'.htmlspecialchars($title,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8').'</h1>'.$body.'<p><small>Datenschutzhinweise: <a href="/datenschutz.html#founders-datenschutz">TENNILO E-Mail-Informationen</a>. Kontakt: <a href="mailto:news@tennilo.de">news@tennilo.de</a>.</small></p></main></html>';exit;
}
$path=dirname(__DIR__).'/tennilo-founders-config.php';
if(!is_file($path))nlPage('Newsletter noch nicht verfügbar','<p>Die Anmeldung wird vorbereitet.</p>',503);
$cfg=require $path;
if(!is_array($cfg)||($cfg['enabled']??false)!==true||empty($cfg['database'])||empty($cfg['key']))nlPage('Newsletter noch nicht verfügbar','<p>Die Anmeldung wird vorbereitet.</p>',503);
try {
 $db=new PDO('sqlite:'.$cfg['database'],null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
 $db->exec('PRAGMA busy_timeout=5000');
 $db->exec("CREATE TABLE IF NOT EXISTS newsletter_pending (email TEXT NOT NULL, kind TEXT NOT NULL, token_hash TEXT NOT NULL UNIQUE, created_at INTEGER NOT NULL, expires_at INTEGER NOT NULL, PRIMARY KEY(email,kind))");
 $db->exec("CREATE TABLE IF NOT EXISTS subscriber_consents (subscriber_id INTEGER NOT NULL, topic TEXT NOT NULL, consented_at INTEGER NOT NULL, consent_version TEXT NOT NULL, PRIMARY KEY(subscriber_id,topic))");
 $db->exec("CREATE TABLE IF NOT EXISTS attempts (ip_hash TEXT NOT NULL,created_at INTEGER NOT NULL)");
} catch(Throwable $e) {error_log('TENNILO newsletter database: '.$e->getMessage());nlPage('Vorübergehend nicht verfügbar','<p>Bitte versuche es später erneut.</p>',503);}
$topics=['newsletter'=>'TENNILO Newsletter – Neuigkeiten und Entwicklung','beta'=>'Einladungen zu Betatests','founders'=>'Founders-Angebote','crowdfunding'=>'Informationen zu möglichem Crowdfunding'];
$now=time();$method=$_SERVER['REQUEST_METHOD']??'GET';
$action=(string)($_POST['action']??$_GET['action']??'');
$esc=static fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
$db->prepare('DELETE FROM newsletter_pending WHERE expires_at<?')->execute([$now]);
$db->prepare('DELETE FROM attempts WHERE created_at<?')->execute([$now-86400]);
function nlMail(string $email,string $subject,string $body): bool {
 if(!filter_var($email,FILTER_VALIDATE_EMAIL))return false;
 $headers=[
  'From: TENNILO News <news@tennilo.de>',
  'Reply-To: news@tennilo.de',
  'MIME-Version: 1.0',
  'Content-Type: text/plain; charset=UTF-8',
  'X-Auto-Response-Suppress: All'
 ];
 return mail($email,'=?UTF-8?B?'.base64_encode($subject).'?=',$body,implode("\r\n",$headers),'-fnews@tennilo.de');
}
function throttle(PDO $db,array $cfg,string $email,int $now): void {
 $ipKey=hash_hmac('sha256','nl-ip|'.($_SERVER['REMOTE_ADDR']??'unknown'),$cfg['key']);
 $emailKey=hash_hmac('sha256','nl-mail|'.$email,$cfg['key']);
 $q=$db->prepare('SELECT COUNT(*) FROM attempts WHERE ip_hash=? AND created_at>?');
 $q->execute([$ipKey,$now-3600]);$ipCount=(int)$q->fetchColumn();
 $q->execute([$emailKey,$now-86400]);$emailCount=(int)$q->fetchColumn();
 if($ipCount>=6||$emailCount>=4)nlPage('Zu viele Anfragen','<p>Bitte versuche es später erneut oder wende dich an news@tennilo.de.</p>',429);
 $q=$db->prepare('INSERT INTO attempts(ip_hash,created_at) VALUES(?,?)');
 $q->execute([$ipKey,$now]);$q->execute([$emailKey,$now]);
}
function pending(PDO $db,string $email,string $kind,int $now): string {
 $token=bin2hex(random_bytes(32));
 $q=$db->prepare('INSERT INTO newsletter_pending(email,kind,token_hash,created_at,expires_at) VALUES(?,?,?,?,?) ON CONFLICT(email,kind) DO UPDATE SET token_hash=excluded.token_hash, created_at=excluded.created_at, expires_at=excluded.expires_at');
 $q->execute([$email,$kind,hash('sha256',$token),$now,$kind==='subscribe'?$now+14*86400:$now+3600]);
 return $token;
}
function verifyPending(PDO $db,string $token,string $kind,int $now): array|false {
 if(!preg_match('/^[a-f0-9]{64}$/D',$token))return false;
 $q=$db->prepare('SELECT email,token_hash FROM newsletter_pending WHERE token_hash=? AND kind=? AND expires_at>=?');
 $q->execute([hash('sha256',$token),$kind,$now]);
 return $q->fetch();
}
if($method==='POST'&&in_array($action,['subscribe','manage-request'],true)){
 if(!empty($_POST['website']))nlPage('Vielen Dank','<p>Bitte prüfe dein Postfach.</p>');
 $origin=(string)($_SERVER['HTTP_ORIGIN']??'');
 if($origin!==''&&!in_array($origin,['https://tennilo.de','https://www.tennilo.de'],true))nlPage('Ungültige Anfrage','<p>Bitte benutze das Formular auf tennilo.de.</p>',403);
 $email=strtolower(trim((string)($_POST['email']??'')));
 if(!filter_var($email,FILTER_VALIDATE_EMAIL)||strlen($email)>254)nlPage('Adresse prüfen','<p>Bitte eine gültige E-Mail-Adresse eingeben.</p>',422);
 if($action==='subscribe'&&($_POST['consent']??'')!=='yes')nlPage('Einwilligung fehlt','<p>Bitte die freiwillige Newsletter-Einwilligung bestätigen.</p>',422);
 throttle($db,$cfg,$email,$now);
 if($action==='subscribe'){
  $token=pending($db,$email,'subscribe',$now);
  $url='https://tennilo.de/newsletter.php?action=confirm&token='.$token;
  $body="Bitte bestaetige deine Anmeldung zum allgemeinen TENNILO-Newsletter:\n\n".$url."\n\nDie Bestaetigung ist 14 Tage gueltig. Ohne Bestaetigung erfolgt kein Newsletter-Versand.\n\nFalls du dich nicht angemeldet hast, ignoriere diese Nachricht.\nKontakt: news@tennilo.de";
  if(!nlMail($email,'TENNILO Newsletter: Anmeldung bestaetigen',$body)){
   $db->prepare("DELETE FROM newsletter_pending WHERE token_hash=? AND kind='subscribe'")->execute([hash('sha256',$token)]);
   nlPage('Versand nicht möglich','<p>Die Bestätigungsmail konnte nicht versendet werden. Bitte später erneut versuchen.</p>',503);
  }
 } else {
  $q=$db->prepare('SELECT id FROM subscribers WHERE email=? AND confirmed_at IS NOT NULL');$q->execute([$email]);
  if($q->fetch()){
   $token=pending($db,$email,'manage',$now);
   $url='https://tennilo.de/newsletter.php?action=preferences&token='.$token;
   $body="Hier kannst du die TENNILO E-Mail-Themen fuer deine Adresse verwalten:\n\n".$url."\n\nDer Link ist 60 Minuten gueltig. Wenn du ihn nicht angefordert hast, ignoriere diese Nachricht.\nKontakt: news@tennilo.de";
   if(!nlMail($email,'TENNILO: E-Mail-Einstellungen bearbeiten',$body))error_log('TENNILO newsletter preference mail() failed');
  }
 }
 nlPage('Bitte E-Mail prüfen','<p>Falls für deine Adresse eine Aktion möglich ist, erhältst du eine E-Mail mit einem Bestätigungslink. Prüfe gegebenenfalls auch den Spamordner.</p>');
}
if($method==='GET'&&$action==='confirm'){
 $token=(string)($_GET['token']??'');$p=verifyPending($db,$token,'subscribe',$now);
 if(!$p)nlPage('Link ungültig','<p>Der Bestätigungslink ist abgelaufen oder wurde bereits verwendet.</p>',400);
 try {
  $db->exec('BEGIN IMMEDIATE');
  $p=verifyPending($db,$token,'subscribe',$now);
  if(!$p) {$db->rollBack();nlPage('Link ungültig','<p>Dieser Link wurde bereits verwendet.</p>',400);}
  $q=$db->prepare('SELECT id,interests,confirmed_at FROM subscribers WHERE email=?');$q->execute([$p['email']]);$old=$q->fetch();
  if(!$old){
   $q=$db->prepare('INSERT INTO subscribers(email,role,interests,consent_version,created_at,confirmed_at,confirm_hash,unsubscribe_hash,expires_at) VALUES(?,?,?,?,?,?,NULL,NULL,?)');
   $q->execute([$p['email'],'sonstige',json_encode(['newsletter']),'newsletter-2026-11',$now,$now,2147483647]);
   $sid=(int)$db->lastInsertId();
  } else {
   $sid=(int)$old['id'];
   $interests=$old['confirmed_at']!==null?json_decode((string)$old['interests'],true):[];
   if(!is_array($interests))$interests=[];
   $interests=array_values(array_unique(array_merge($interests,['newsletter'])));
   if($old['confirmed_at']===null){
    // Do not auto-confirm earlier Founders topics from a separate unconfirmed request.
    $interests=['newsletter'];
    $db->prepare("UPDATE subscribers SET interests=?,consent_version=?,confirmed_at=?,confirm_hash=NULL,expires_at=? WHERE id=?")
      ->execute([json_encode($interests),'newsletter-2026-11',$now,2147483647,$sid]);
   } else {
    $db->prepare('UPDATE subscribers SET interests=? WHERE id=?')->execute([json_encode($interests),$sid]);
   }
  }
  $db->prepare('INSERT INTO subscriber_consents(subscriber_id,topic,consented_at,consent_version) VALUES(?,?,?,?) ON CONFLICT(subscriber_id,topic) DO UPDATE SET consented_at=excluded.consented_at,consent_version=excluded.consent_version')
   ->execute([$sid,'newsletter',$now,'newsletter-2026-11']);
  $db->prepare('DELETE FROM newsletter_pending WHERE token_hash=?')->execute([$p['token_hash']]);
  $db->commit();
 } catch(Throwable $e) {
  if($db->inTransaction())$db->rollBack();
  error_log('TENNILO newsletter confirm error: '.$e->getMessage());
  nlPage('Vorübergehend nicht verfügbar','<p>Bitte versuche es später erneut.</p>',503);
 }
 nlPage('Newsletter bestätigt','<p>Deine Anmeldung zum allgemeinen TENNILO-Newsletter ist bestätigt. Wir informieren dich über neue Funktionen und Entwicklungsfortschritte.</p><p><a href="/newsletter.php?action=manage">E-Mail-Themen verwalten</a></p>');
}
if($method==='GET'&&$action==='preferences'){
 $token=(string)($_GET['token']??'');$p=verifyPending($db,$token,'manage',$now);
 if(!$p)nlPage('Link ungültig','<p>Der Link ist abgelaufen oder wurde bereits verwendet.</p>',400);
 $q=$db->prepare('SELECT interests FROM subscribers WHERE email=? AND confirmed_at IS NOT NULL');$q->execute([$p['email']]);$r=$q->fetch();
 if(!$r)nlPage('Nicht gefunden','<p>Diese Anmeldung ist nicht mehr aktiv.</p>',404);
 $interests=json_decode((string)$r['interests'],true);if(!is_array($interests))$interests=[];
 $form='<p>Wähle die Informationen aus, die du künftig erhalten möchtest. Zusätzliche Themen erhalten deine Zustimmung erst, wenn du die Auswahl speicherst.</p><form method="post" action="/newsletter.php"><input type="hidden" name="action" value="preferences-save"><input type="hidden" name="token" value="'.$esc($token).'"><fieldset><legend>E-Mail-Themen</legend>';
 foreach($topics as $key=>$name)$form.='<label><input type="checkbox" name="topics[]" value="'.$esc($key).'"'.(in_array($key,$interests,true)?' checked':'').'> '.$esc($name).'</label>';
 $form.='</fieldset><label><input type="checkbox" name="consent" value="yes" required> Ich stimme dem Erhalt von E-Mails zu den ausgewählten Themen zu. Einzelne Themen kann ich jederzeit wieder abbestellen.</label><p><button type="submit">Auswahl speichern</button></p></form>';
 $form.='<hr style="border:0;border-top:1px solid #dae4e0;margin:25px 0"><form method="post"><input type="hidden" name="action" value="delete-all"><input type="hidden" name="token" value="'.$esc($token).'"><p class="sub">Du kannst auch die gesamte E-Mail-Anmeldung inklusive deiner Daten löschen.</p><button type="submit">Alle E-Mails abbestellen und Daten löschen</button></form>';
 nlPage('E-Mail-Einstellungen',$form);
}
if($method==='POST'&&in_array($action,['preferences-save','delete-all'],true)){
 $token=(string)($_POST['token']??'');$p=verifyPending($db,$token,'manage',$now);
 if(!$p)nlPage('Link ungültig','<p>Dieser Link ist abgelaufen oder bereits verwendet.</p>',400);
 $wanted=[];
 if($action==='preferences-save'){
  if(($_POST['consent']??'')!=='yes')nlPage('Zustimmung erforderlich','<p>Bitte bestätige die Auswahl.</p>',422);
  $chosen=$_POST['topics']??[];if(!is_array($chosen))$chosen=[];
  $wanted=array_values(array_unique(array_intersect(array_keys($topics),array_map('strval',$chosen))));
  if(!$wanted)nlPage('Keine Themen ausgewählt','<p>Wähle mindestens ein Thema oder benutze die Schaltfläche zur vollständigen Abmeldung.</p>',422);
 }
 try {
  $db->exec('BEGIN IMMEDIATE');$p=verifyPending($db,$token,'manage',$now);
  if(!$p){$db->rollBack();nlPage('Link ungültig','<p>Dieser Link wurde schon verwendet.</p>',400);}
  $q=$db->prepare('SELECT id,interests FROM subscribers WHERE email=? AND confirmed_at IS NOT NULL');$q->execute([$p['email']]);$r=$q->fetch();
  if(!$r){$db->rollBack();nlPage('Nicht gefunden','<p>Diese Anmeldung wurde bereits gelöscht.</p>',404);}
  $sid=(int)$r['id'];
  if($action==='delete-all'){
   $db->prepare('DELETE FROM subscriber_consents WHERE subscriber_id=?')->execute([$sid]);
   $db->prepare('DELETE FROM subscribers WHERE id=?')->execute([$sid]);
  } else {
   $old=json_decode((string)$r['interests'],true);if(!is_array($old))$old=[];
   $db->prepare('UPDATE subscribers SET interests=? WHERE id=?')->execute([json_encode($wanted),$sid]);
   $q=$db->prepare('INSERT OR IGNORE INTO subscriber_consents(subscriber_id,topic,consented_at,consent_version) VALUES(?,?,?,?)');
   foreach($wanted as $topic)if(!in_array($topic,$old,true))$q->execute([$sid,$topic,$now,'preferences-2026-11']);
   $q=$db->prepare('DELETE FROM subscriber_consents WHERE subscriber_id=? AND topic=?');
   foreach(array_keys($topics) as $topic)if(!in_array($topic,$wanted,true))$q->execute([$sid,$topic]);
  }
  $db->prepare('DELETE FROM newsletter_pending WHERE email=?')->execute([$p['email']]);
  $db->commit();
 } catch(Throwable $e){
  if($db->inTransaction())$db->rollBack();
  error_log('TENNILO newsletter preferences: '.$e->getMessage());
  nlPage('Vorübergehend nicht verfügbar','<p>Bitte versuche es später noch einmal.</p>',503);
 }
 nlPage($action==='delete-all'?'Abgemeldet':'Einstellungen gespeichert',
  $action==='delete-all'?'<p>Deine Newsletter-Anmeldung und deine Daten wurden gelöscht.</p>':'<p>Deine Themenauswahl wurde aktualisiert. Du erhältst künftig nur E-Mails zu den ausgewählten Themen.</p>');
}
if($action==='manage'&&$method==='GET'){
 nlPage('E-Mail-Themen verwalten',
 '<p>Gib die E-Mail-Adresse ein, mit der du dich angemeldet hast. Du erhältst einen persönlichen Link für die Verwaltung deiner Themen und Abmeldungen.</p><form method="post"><input type="hidden" name="action" value="manage-request"><label>E-Mail-Adresse<input type="email" name="email" autocomplete="email" maxlength="254" required></label><p><button type="submit">Einstellungslink anfordern</button></p></form>');
}
nlPage('TENNILO Newsletter',
 '<p>Informationen zu neuen Funktionen, Turnierformaten und zur Entwicklung von TENNILO. Kostenlos und etwa alle vier bis sechs Wochen oder bei wichtigen Neuigkeiten.</p><form method="post"><input type="hidden" name="action" value="subscribe"><div class="hidden"><label>Website <input type="text" name="website" autocomplete="off" tabindex="-1"></label></div><label>E-Mail-Adresse<input type="email" name="email" autocomplete="email" maxlength="254" placeholder="du@tennisverein.de" required></label><label><input type="checkbox" name="consent" value="yes" required> Ich möchte den allgemeinen TENNILO-Newsletter erhalten und habe die <a href="/datenschutz.html#founders-datenschutz">Datenschutzhinweise</a> gelesen. Die Einwilligung ist jederzeit widerrufbar.</label><p><button type="submit">Newsletter kostenlos abonnieren</button></p></form><p><a href="/newsletter.php?action=manage">E-Mail-Themen verwalten oder abmelden</a></p>');
