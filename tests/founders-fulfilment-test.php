<?php
declare(strict_types=1);
require __DIR__.'/../public/admin/founders-fulfilment-lib.php';
function ok(bool $v,string $m):void {if(!$v)throw new RuntimeException($m);}
function rejects(callable $f):void {try{$f();}catch(InvalidArgumentException $e){return;}throw new RuntimeException('Expected validation error');}
$db=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
ffSchema($db);ffSchema($db);
$csv="\xEF\xBB\xBFreference;name;email;package;lang\nDEMO-1;Müller Test;demo@example.invalid;Diamond;de\nDEMO-2;;;free;en\n";
$rows=ffCSV($csv);ok(count($rows)===2,'CSV rows');
ok(ffImportRows($db,$rows)===[2,0],'import');
ok(ffImportRows($db,$rows)===[0,2],'idempotent');
ok(count(ffCSV("Nummer,Person,Paketwahl\nT1,Test Club,Club Plus\n",['reference'=>'Nummer','name'=>'Person','package'=>'Paketwahl']))===1,'column mapping');
rejects(fn()=>ffCSV("reference;name;package\nT1;Test;unknown\n"));
rejects(fn()=>ffCSV("reference;name;package\nT1;Test;fb01\nT1;Test;fb01\n"));
rejects(fn()=>ffCSV("reference;name;package\n;Test;fb01\n"));
rejects(fn()=>ffImportRows($db,[['reference'=>'rollback','name'=>'Test','package'=>'fb01'],['reference'=>'bad','name'=>'','package'=>'fb03']]));
ok((int)$db->query("SELECT COUNT(*) FROM founders_supporters WHERE reference='rollback'")->fetchColumn()===0,'atomic rollback');
ok((int)$db->query("SELECT COUNT(*) FROM founders_fulfilment WHERE supporter_id=1")->fetchColumn()===count(ffPackages()['fb05'][2]),'tasks assigned');
ok((int)$db->query("SELECT COUNT(*) FROM founders_fulfilment WHERE supporter_id=2")->fetchColumn()===0,'free no tasks');
rejects(fn()=>ffIssue($db,2));$r=ffIssue($db,1);ok(preg_match('/^TF-\d{4}-000001$/',$r['certificate_number'])===1,'stable identifier');
ok(ffIssue($db,1)['certificate_number']===$r['certificate_number'],'repeat issuance');
ok(ffPublicRows($db)===[],'private by default');
$db->exec("UPDATE founders_supporters SET publish=1,public_name='Test' WHERE id=1");ok(ffPublicRows($db)===[],'publish without proof denied');
$db->exec("UPDATE founders_supporters SET consent_at=123,consent_note='Test consent' WHERE id=1");ok(count(ffPublicRows($db))===1,'explicit consent');
$db->exec("UPDATE founders_supporters SET state='cancelled' WHERE id=1");ok(ffPublicRows($db)===[],'cancel hidden');rejects(fn()=>ffCertificatePdf(ffFind($db,1)));
$db->exec("UPDATE founders_supporters SET state='active',publish=0 WHERE id=1");
foreach(['de','en'] as $lang)foreach(array_keys(ffPackages()) as $code){if($code==='free')continue;$sample=array_merge($r,['lang'=>$lang,'package'=>$code,'name'=>'Müller & Test e. V.']);$pdf=ffCertificatePdf($sample);ok(str_starts_with($pdf,'%PDF-1.4')&&str_contains($pdf,'/DCTDecode')&&str_ends_with($pdf,"%%EOF\n"),'PDF '.$lang.' '.$code);ok(strlen($pdf)>10000,'PDF template content');}
rejects(fn()=>ffPdfText('山田'));
ok(ffCsvSafe('=1+2')==="'=1+2",'spreadsheet escape');
ok(ffCsvSafe('  @bad')==="'  @bad",'spreadsheet escape whitespace');
$db->exec("DELETE FROM founders_supporters WHERE id=1");
ok((int)$db->query("SELECT COUNT(*) FROM founders_fulfilment WHERE supporter_id=1")->fetchColumn()===0,'cascade delete');
ok((int)$db->query("SELECT COUNT(*) FROM sqlite_master WHERE name='subscribers'")->fetchColumn()===0,'newsletter isolation');
$source=file_get_contents(__DIR__.'/../public/admin/founders.php');
ok(strpos($source,"empty(\$_SESSION['authenticated'])")<strpos($source,'ffSchema($db)'),'authentication before data access');
ok(str_contains($source,'hash_equals($_SESSION['),"CSRF required");
$manifest=file_get_contents(__DIR__.'/../deploy-files.txt');
foreach(['public/admin/founders.php','public/admin/founders-fulfilment-lib.php','public/founders/supporters.php'] as $file)ok(str_contains($manifest,$file."\n"),'deploy '.$file);
echo "Founders fulfilment: CSV validation, duplicate protection, tasks, consent, certificates and isolation passed.\n";
