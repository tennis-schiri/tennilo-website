<?php
declare(strict_types=1);
/* One-time browser installer. Control of founders@tennilo.de is required. */
header('Content-Type: text/html; charset=UTF-8');
header('Cache-Control: no-store, max-age=0');
header('Pragma: no-cache');
header('Referrer-Policy: no-referrer');
header('X-Content-Type-Options: nosniff');
header('X-Robots-Tag: noindex, nofollow');
header('Content-Security-Policy: default-src \'none\'; style-src \'unsafe-inline\'; form-action \'self\'; base-uri \'none\'; frame-ancestors \'none\'');
if (($_SERVER['HTTPS']??'')!=='on' && ($_SERVER['HTTP_X_FORWARDED_PROTO']??'')!=='https') {
 http_response_code(403); exit('Die Einrichtung ist nur über HTTPS erlaubt.');
}
$base=realpath(dirname(__DIR__));
$configPath=$base.'/tennilo-founders-config.php';
$dataDir=$base.'/tennilo-founders-data';
$challengePath=$dataDir.'/.setup-challenge';
function out(string $heading,string $content,int $status=200): never {
 http_response_code($status);
 echo '<!doctype html><html lang="de"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>TENNILO · Einrichtung</title><style>body{margin:0;background:#f6f8f4;color:#092b36;font:16px/1.6 system-ui}.panel{max-width:650px;margin:8vh auto;background:white;border-radius:18px;padding:36px;box-shadow:0 15px 50px #092b3612}h1{line-height:1.15}input{font:inherit;padding:12px;width:100%;box-sizing:border-box;margin:7px 0 18px;border-radius:8px;border:1px solid #8aa8a6}button{font:inherit;border:0;border-radius:8px;padding:13px 20px;background:#092b36;color:white;cursor:pointer}a{color:#087b80}small{color:#527478}</style><main class="panel"><h1>'.htmlspecialchars($heading,ENT_QUOTES,'UTF-8').'</h1>'.$content.'<p><small>TENNILO Founders · Einmalige Einrichtung</small></p></main></html>';exit;
}
if (!is_file($configPath)||!is_dir($dataDir)||!is_writable($dataDir)) out('Vorbereitung fehlt','<p>Die private Konfigurationsdatei oder ein beschreibbarer Datenordner fehlen. Die Dateien müssen neben dem öffentlichen Verzeichnis <code>tennilo-website</code> liegen.</p>',503);
$cfg=require $configPath;
if (!is_array($cfg)||!array_key_exists('enabled',$cfg)) out('Konfigurationsfehler','<p>Die hochgeladene Vorlage ist ungültig.</p>',503);
if (($cfg['setup_complete']??false)===true||($cfg['enabled']??false)===true) out('Bereits eingerichtet','<p>Die Ersteinrichtung ist abgeschlossen. <a href="/founders-admin.php">Zur Verwaltung</a>.</p>',403);
if (!class_exists('PDO')||!in_array('sqlite',PDO::getAvailableDrivers(),true)) out('SQLite fehlt','<p>PHP PDO_SQLITE muss beim Hoster eingeschaltet werden.</p>',503);
if (!is_writable($configPath)) out('Datei nicht beschreibbar','<p>PHP darf die bestehende Konfigurationsdatei nicht aktualisieren. Bitte Dateiberechtigungen bzw. Dateibesitzer bei ALL-INKL prüfen. Nicht pauschal Berechtigungen auf 777 setzen.</p>',503);
$now=time();
$challenge=null;
if (is_file($challengePath)) {
 $raw=file_get_contents($challengePath);
 $challenge=is_string($raw)?json_decode($raw,true):null;
 if (!is_array($challenge)) $challenge=null;
}
$method=$_SERVER['REQUEST_METHOD']??'GET';
if($method==='POST' && ($_POST['action']??'')==='send') {
 if ($challenge && ($challenge['created']??0)>$now-900) out('E-Mail bereits versendet','<p>Eine Einrichtungs-E-Mail wurde vor Kurzem angefordert. Bitte prüfe das Postfach founders@tennilo.de und gegebenenfalls den Spamordner.</p><p>Nach 15 Minuten kann eine neue Mail angefordert werden.</p>',429);
 $token=bin2hex(random_bytes(32));
 $challenge=['hash'=>hash('sha256',$token),'created'=>$now,'expires'=>$now+1800];
 $challengeJson=json_encode($challenge,JSON_THROW_ON_ERROR);
 if(file_put_contents($challengePath,$challengeJson,LOCK_EX)===false) out('Speicherfehler','<p>Einrichtungsdatei konnte nicht geschrieben werden.</p>',503);
 @chmod($challengePath,0600);
 $url='https://tennilo.de/founders-setup.php?token='.$token;
 $headers=['From: TENNILO Founders <founders@tennilo.de>','Reply-To: founders@tennilo.de','MIME-Version: 1.0','Content-Type: text/plain; charset=UTF-8'];
 $ok=mail('founders@tennilo.de','TENNILO: Einmaligen Einrichtungslink bestaetigen',"Einrichtungslink (30 Minuten gueltig):\n\n".$url."\n\nWenn du die Einrichtung nicht gestartet hast, ignoriere diese E-Mail.",implode("\r\n",$headers),'-ffounders@tennilo.de');
 if(!$ok){@unlink($challengePath);out('Mailversand fehlgeschlagen','<p>Der PHP-Mailtransport konnte die Einrichtungsnachricht nicht annehmen. Bitte die Mailfunktion bei ALL-INKL prüfen.</p>',503);}
 out('E-Mail verschickt','<p>Wir haben den einmaligen Einrichtungslink an <strong>founders@tennilo.de</strong> gesendet. Öffne den Link aus dieser Nachricht innerhalb von 30 Minuten.</p>');
}
$token=(string)($_POST['token']??$_GET['token']??'');
$authorized=(bool)($challenge && preg_match('/^[a-f0-9]{64}$/D',$token) && ($challenge['expires']??0)>=$now && hash_equals((string)$challenge['hash'],hash('sha256',$token)));
if ($method==='POST' && ($_POST['action']??'')==='finish') {
 if(!$authorized)out('Link ungültig','<p>Bitte fordere eine neue Einrichtungs-E-Mail an.</p>',403);
 $password=(string)($_POST['password']??'');
 $confirmation=(string)($_POST['confirm']??'');
 if(strlen($password)<16||strlen($password)>200||!hash_equals($password,$confirmation))out('Passwort prüfen','<p>Das Passwort muss mindestens 16 Zeichen enthalten und beide Eingaben müssen übereinstimmen.</p>',422);
 $dbPath=$dataDir.'/tennilo-founders.sqlite';
 try {
  $db=new PDO('sqlite:'.$dbPath,null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
  $db->exec('PRAGMA busy_timeout=5000');
  $db->exec('CREATE TABLE IF NOT EXISTS subscribers (id INTEGER PRIMARY KEY,email TEXT NOT NULL UNIQUE,role TEXT NOT NULL,interests TEXT NOT NULL,consent_version TEXT NOT NULL,created_at INTEGER NOT NULL,confirmed_at INTEGER,confirm_hash TEXT,unsubscribe_hash TEXT,expires_at INTEGER NOT NULL)');
  $db->exec('CREATE TABLE IF NOT EXISTS attempts (ip_hash TEXT NOT NULL,created_at INTEGER NOT NULL)');
  @chmod($dbPath,0600);
 } catch(Throwable $e){error_log('TENNILO setup storage: '.$e->getMessage());out('Datenbank nicht verfügbar','<p>Die private SQLite-Datenbank konnte nicht angelegt werden. Bitte Ordnerrechte prüfen.</p>',503);}
 $settings=['enabled'=>true,'setup_complete'=>true,'database'=>$dbPath,'key'=>bin2hex(random_bytes(32)),'admin_password_hash'=>password_hash($password,PASSWORD_DEFAULT)];
 $php="<?php\n/* TENNILO PRIVATE: Never serve over HTTP or commit to Git. */\nreturn ".var_export($settings,true).";\n";
 $temp=tempnam($base,'.founders-config-');
 if($temp===false || file_put_contents($temp,$php,LOCK_EX)===false){out('Konfiguration nicht schreibbar','<p>Die Konfiguration konnte nicht vorbereitet werden.</p>',503);}
 @chmod($temp,0600);
 if(!rename($temp,$configPath)){@unlink($temp);out('Konfiguration nicht speicherbar','<p>Die private Datei konnte nicht ersetzt werden. Bitte Dateirechte prüfen.</p>',503);}
 @unlink($challengePath);
 out('Einrichtung abgeschlossen','<p>Die private Datenbank und das Admin-Passwort sind eingerichtet. Bitte jetzt eine Testanmeldung auf der Startseite durchführen, die Bestätigungsmail prüfen und die Anmeldung in der Verwaltung kontrollieren.</p><p><a href="/founders-admin.php">Zur Founders-Verwaltung</a> · <a href="/">Zur Startseite</a></p><p><strong>Wichtig:</strong> Vor öffentlicher Bewerbung bitte auch den Abmeldeablauf und den Datenschutz prüfen.</p>');
}
if($authorized){
 out('Admin-Passwort festlegen','<p>Die E-Mail-Adresse wurde bestätigt. Jetzt ein eigenes, mindestens 16 Zeichen langes Passwort für die Founders-Verwaltung festlegen.</p><form method="post"><input type="hidden" name="action" value="finish"><input type="hidden" name="token" value="'.htmlspecialchars($token,ENT_QUOTES,'UTF-8').'"><label>Admin-Passwort<input name="password" type="password" required minlength="16" autocomplete="new-password"></label><label>Passwort wiederholen<input name="confirm" type="password" required minlength="16" autocomplete="new-password"></label><button type="submit">Private Datenbank und Zugang einrichten</button></form>');
}
out('Founders einmalig einrichten','<p>Die Einrichtung wird durch einen einmaligen Link an <strong>founders@tennilo.de</strong> geschützt. Damit werden ein privater Datenbankpfad, ein Sicherheitsschlüssel und dein Admin-Zugang automatisch erstellt.</p><form method="post"><input type="hidden" name="action" value="send"><button type="submit">Einrichtungs-E-Mail anfordern</button></form><p><small>Bitte nur selbst starten. Die Warteliste ist noch nicht veröffentlicht, solange der Website-Upload nicht durchgeführt wurde.</small></p>');
