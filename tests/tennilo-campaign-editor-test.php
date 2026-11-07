<?php
declare(strict_types=1);
/*
 * Regression: upgrade an existing TENNILO SQLite campaign without losing
 * drafts, then save and duplicate new layout fields through the real editor.
 * Runs in an isolated temporary directory, never against a production DB.
 */
if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
    echo "SKIP: PDO SQLite auf diesem PHP-Runner nicht verfügbar.\n";
    exit(0);
}
function checkTest(bool $ok, string $message): void {
    if (!$ok) { fwrite(STDERR, "FAIL: ".$message."\n"); exit(1); }
}
$base = sys_get_temp_dir().'/tennilo_mail_test_'.bin2hex(random_bytes(6));
$public = $base.'/public';
mkdir($public, 0700, true);
mkdir($base.'/sessions', 0700, true);
$source = dirname(__DIR__).'/public';
copy($source.'/founders-mail.php', $public.'/founders-mail.php');
copy($source.'/founders-email-template.php', $public.'/founders-email-template.php');
$database = $base.'/test.sqlite';
$db = new PDO('sqlite:'.$database, null, null, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$db->exec("CREATE TABLE founders_campaigns (id INTEGER PRIMARY KEY AUTOINCREMENT, subject TEXT NOT NULL, body TEXT NOT NULL, topic TEXT NOT NULL, state TEXT NOT NULL DEFAULT 'draft', created_at INTEGER NOT NULL, updated_at INTEGER NOT NULL)");
$db->exec("INSERT INTO founders_campaigns(subject,body,topic,state,created_at,updated_at) VALUES('Alter Entwurf','Bisheriger Inhalt','newsletter','draft',100,100)");
$db->exec("CREATE TABLE subscribers (id INTEGER PRIMARY KEY AUTOINCREMENT,email TEXT,interests TEXT,confirmed_at INTEGER)");
$db->exec("INSERT INTO subscribers(email,interests,confirmed_at) VALUES('own@example.org','[\"newsletter\"]',123)");
file_put_contents($base.'/tennilo-founders-config.php', "<?php\nreturn ".var_export([
    'enabled'=>true,
    'database'=>$database,
    'key'=>str_repeat('a',64),
    'admin_password_hash'=>'demo-do-not-use'
], true).";\n");
$runner = <<<'PHP'
<?php
declare(strict_types=1);
$base = dirname(__DIR__);
session_save_path($base.'/sessions');
session_name('tennilo_founders_portal_v2');
session_id('tennilotest123');
session_start();
$_SESSION = ['authenticated'=>true,'auth_at'=>time(),'csrf'=>'csrf-test'];
session_write_close();
$_COOKIE['tennilo_founders_portal_v2']='tennilotest123';
$_GET=['campaign'=>'1'];
$mode=$argv[1]??'get';
$_SERVER['REQUEST_METHOD']=$mode==='get'?'GET':'POST';
if($mode!=='get') {
 $_POST=[
  'csrf'=>'csrf-test', 'campaign'=>'1', 'action'=>$mode==='duplicate'?'duplicate':'save',
  'subject'=>'Neuer Betreff', 'body'=>'Aktueller Stand', 'topic'=>'newsletter',
  'headline'=>'Ein neuer Blick', 'preheader'=>'Kurz und informativ',
  'button_label'=>'Mehr erfahren', 'button_url'=>$mode==='bad'?'javascript:alert(1)':'https://tennilo.de/#entwicklung',
  'layout'=>'spotlight'
 ];
}
require __DIR__.'/founders-mail.php';
PHP;
file_put_contents($public.'/run.php', $runner);

function runEditor(string $runner, string $mode): string {
    $cmd=escapeshellarg(PHP_BINARY).' '.escapeshellarg($runner).' '.escapeshellarg($mode);
    exec($cmd.' 2>&1', $lines, $exit);
    checkTest($exit===0, 'Editor erfolgreich gestartet: '.$mode.' '.implode(' ',$lines));
    return implode("\n",$lines);
}
try {
    $html=runEditor($public.'/run.php','get');
    checkTest(str_contains($html,'Bisheriger Inhalt'), 'Alter Entwurf sichtbar');
    checkTest(str_contains($html,'name="headline"'), 'Neue Überschrift im Formular');
    checkTest(str_contains($html,'name="preheader"'), 'Preheader im Formular');
    checkTest(str_contains($html,'name="layout"'), 'Layout-Auswahl im Formular');

    $cols=$db->query('PRAGMA table_info(founders_campaigns)')->fetchAll(PDO::FETCH_ASSOC);
    $names=array_column($cols,'name');
    foreach(['headline','preheader','button_label','button_url','layout'] as $name) {
        checkTest(in_array($name,$names,true), 'Nicht-destruktive Datenbankmigration für '.$name);
    }

    runEditor($public.'/run.php','save');
    $saved=$db->query('SELECT * FROM founders_campaigns WHERE id=1')->fetch(PDO::FETCH_ASSOC);
    checkTest($saved['subject']==='Neuer Betreff', 'Betreff gespeichert');
    checkTest($saved['body']==='Aktueller Stand', 'Nachrichtentext gespeichert');
    checkTest($saved['headline']==='Ein neuer Blick', 'Überschrift gespeichert');
    checkTest($saved['preheader']==='Kurz und informativ', 'Preheader gespeichert');
    checkTest($saved['layout']==='spotlight', 'Layout gespeichert');
    checkTest($saved['button_url']==='https://tennilo.de/#entwicklung', 'Button gespeichert');

    $bad=runEditor($public.'/run.php','bad');
    checkTest(str_contains($bad,'gültigen HTTPS-Link'), 'Unsicherer Link abgelehnt');
    $same=$db->query('SELECT button_url FROM founders_campaigns WHERE id=1')->fetchColumn();
    checkTest($same==='https://tennilo.de/#entwicklung', 'Ungültiges Formular ändert gespeicherten Entwurf nicht');

    runEditor($public.'/run.php','duplicate');
    $copy=$db->query('SELECT * FROM founders_campaigns WHERE id=2')->fetch(PDO::FETCH_ASSOC);
    checkTest(is_array($copy)&&$copy['headline']==='Ein neuer Blick', 'Entwurf samt Zusatzfeldern kopiert');
    checkTest($copy['layout']==='spotlight', 'Gestaltungsvariante kopiert');

    echo "TENNILO Kampagneneditor: Migration, Speichern, Schutz und Kopie erfolgreich.\n";
} finally {
    unset($db);
    foreach (glob($base.'/sessions/*') ?: [] as $entry) @unlink($entry);
    foreach (glob($public.'/*') ?: [] as $entry) @unlink($entry);
    @unlink($base.'/tennilo-founders-config.php');
    @unlink($database);
    @rmdir($base.'/sessions');
    @rmdir($public);
    @rmdir($base);
}
