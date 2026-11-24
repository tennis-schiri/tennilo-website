<?php
declare(strict_types=1);
/* TENNILO Founders fulfilment: separate from marketing subscriptions. No checkout. */
function ffPackages(): array {
 return [
 'free'=>['Freie Unterstützung',0,[]],
 'fb01'=>['Bronze',10,['certificate','updates']],
 'fb02'=>['Silver',29,['certificate','updates','acknowledgement']],
 'fb03'=>['Gold',69,['certificate','updates','acknowledgement','presentation','surveys']],
 'fb04'=>['Platinum',149,['certificate','updates','acknowledgement','presentation','surveys','meeting']],
 'fb05'=>['Diamond',299,['certificate','updates','acknowledgement','presentation','exclusive','surveys','meeting','meeting2','insights']],
 'fc01'=>['Club',69,['certificate','licence','acknowledgement']],
 'fc02'=>['Club Plus',199,['certificate','licence','acknowledgement','presentation']],
 'fc03'=>['Founding Partner',599,['certificate','licence','presentation','acknowledgement','introduction','instagram']],
 'fc04'=>['Founding Premium Partner',999,['certificate','licence','presentation','acknowledgement','introduction','instagram','promotion']]
 ];
}
function ffTaskNames(): array {
 return ['certificate'=>'Zertifikat & Abzeichen übergeben','updates'=>'Entwicklungsupdates (gebündelt, Abschluss bestätigen)','acknowledgement'=>'Namens-/Partnerpräsenz: Zustimmung klären, ggf. umsetzen','presentation'=>'Gemeinsame Produktvorstellung','exclusive'=>'Exklusive Produktvorstellung','surveys'=>'Produktumfragen','meeting'=>'Persönliches Gespräch 1','meeting2'=>'Persönliches Gespräch 2','insights'=>'Einblicke in neue Funktionen','licence'=>'Tournament-Lizenz ab Produktfreigabe','introduction'=>'Tournament-Einführung (45 Min.)','instagram'=>'Instagram-Vorstellung','promotion'=>'Gemeinsame Werbemaßnahmen'];
}
function ffSchema(PDO $db): void {
 $db->exec('PRAGMA foreign_keys=ON');
 $db->exec('PRAGMA busy_timeout=5000');
 $db->exec("CREATE TABLE IF NOT EXISTS founders_supporters (
 id INTEGER PRIMARY KEY AUTOINCREMENT, reference TEXT NOT NULL UNIQUE, name TEXT NOT NULL DEFAULT '', email TEXT NOT NULL DEFAULT '',
 package TEXT NOT NULL, lang TEXT NOT NULL DEFAULT 'de' CHECK(lang IN ('de','en')), state TEXT NOT NULL DEFAULT 'active' CHECK(state IN ('active','cancelled')),
 created_at INTEGER NOT NULL, certificate_number TEXT UNIQUE, issued_at INTEGER,
 public_name TEXT NOT NULL DEFAULT '', consent_note TEXT NOT NULL DEFAULT '', consent_at INTEGER, publish INTEGER NOT NULL DEFAULT 0 CHECK(publish IN (0,1)),
 notes TEXT NOT NULL DEFAULT '')");
 $db->exec("CREATE TABLE IF NOT EXISTS founders_fulfilment (
 supporter_id INTEGER NOT NULL REFERENCES founders_supporters(id) ON DELETE CASCADE, task TEXT NOT NULL,
 state TEXT NOT NULL DEFAULT 'open' CHECK(state IN ('open','done','waived')), completed_at INTEGER, due_date TEXT,
 PRIMARY KEY(supporter_id,task))");
}
function ffField(mixed $v, int $max, string $label): string {
 if (!is_string($v)) throw new InvalidArgumentException($label.' ist ungültig.');
 $v=trim($v);
 if(!preg_match('//u',$v)||strlen($v)>$max||preg_match('/[\x00-\x1F\x7F]/u',$v)) throw new InvalidArgumentException($label.' ist ungültig oder zu lang.');
 return $v;
}
function ffPackage(mixed $value): string {
 $v=strtolower(ffField($value,120,'Paket'));
 foreach(ffPackages() as $code=>$p) if($v===$code||$v===strtolower($p[0])||$v===strtolower('TENNILO Founders '.$p[0]))return $code;
 if(in_array($v,['free support','free contribution','freie unterstützung','ohne dankeschön','ohne paket','freie unterstuetzung','ohne gegenleistung'],true))return 'free';
 throw new InvalidArgumentException('Unbekanntes Paket: '.$v);
}
function ffNormalize(array $row): array {
 $ref=ffField($row['reference']??'',150,'Startnext-Referenz');
 if($ref==='')throw new InvalidArgumentException('Startnext-Referenz fehlt.');
 $package=ffPackage($row['package']??'');
 $name=ffField($row['name']??'',160,'Name');
 if($package!=='free'&&$name==='')throw new InvalidArgumentException('Name für Dankeschön fehlt.');
 $email=ffField($row['email']??'',254,'E-Mail');
 if($email!==''&&!filter_var($email,FILTER_VALIDATE_EMAIL))throw new InvalidArgumentException('E-Mail-Adresse ungültig.');
 $lang=strtolower(ffField($row['lang']??'de',10,'Sprache'));
 if($lang==='')$lang='de';
 if(!in_array($lang,['de','en'],true))throw new InvalidArgumentException('Sprache muss de oder en sein.');
 return compact('ref','package','name','email','lang');
}
function ffImportRows(PDO $db,array $rows): array {
 $inserted=0;$skipped=0;$db->beginTransaction();
 try {
  $exists=$db->prepare('SELECT id FROM founders_supporters WHERE reference=?');
  $insert=$db->prepare('INSERT INTO founders_supporters(reference,name,email,package,lang,created_at) VALUES(?,?,?,?,?,?)');
  $task=$db->prepare('INSERT INTO founders_fulfilment(supporter_id,task) VALUES(?,?)');
  foreach($rows as $raw){
   $row=ffNormalize($raw);
   $exists->execute([$row['ref']]); if($exists->fetchColumn()!==false){$skipped++;continue;}
   $insert->execute([$row['ref'],$row['name'],$row['email'],$row['package'],$row['lang'],time()]);
   $id=(int)$db->lastInsertId();
   foreach(ffPackages()[$row['package']][2] as $key)$task->execute([$id,$key]);
   $inserted++;
  }
  $db->commit();
 }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
 return [$inserted,$skipped];
}
function ffCSV(string $raw, array $mapping=[]): array {
 if(strlen($raw)>1000000)throw new InvalidArgumentException('Maximal 1 MB CSV pro Import.');
 $raw=preg_replace('/^\xEF\xBB\xBF/','',$raw);
 if(!preg_match('//u',$raw))throw new InvalidArgumentException('Bitte als CSV UTF-8 speichern.');
 $first=strtok($raw,"\r\n");
 if($first===false)throw new InvalidArgumentException('CSV ist leer.');
 $delimiter=';';$max=0;foreach([';',',',"\t"] as $d){$n=count(str_getcsv($first,$d,'"',''));if($n>$max){$max=$n;$delimiter=$d;}}
 $f=fopen('php://temp','w+');fwrite($f,$raw);rewind($f);
 $header=fgetcsv($f,0,$delimiter,'"','');
 $cols=[];foreach($header?:[] as $i=>$name){$k=strtolower(trim((string)$name));if(isset($cols[$k]))throw new InvalidArgumentException('Doppelte Spaltenüberschrift: '.$k);$cols[$k]=$i;}
 $aliases=['reference'=>['reference','referenz','startnext-referenz','bestellnummer','bestell-id','order id'],'name'=>['name','name / verein','unterstützer','unterstützer:in'],'email'=>['email','e-mail','e-mail-adresse'],'package'=>['package','paket','dankeschön','dankeschoen','reward'],'lang'=>['lang','sprache','language']];
 $idx=[];foreach($aliases as $key=>$choices){$custom=trim((string)($mapping[$key]??''));if($custom!=='')$choices=[strtolower($custom)];foreach($choices as $choice){if(array_key_exists($choice,$cols)){$idx[$key]=$cols[$choice];break;}}}
 foreach(['reference','name','package'] as $k)if(!isset($idx[$k]))throw new InvalidArgumentException('Spalte fehlt: '.$k.'. Gefundene Überschriften: '.implode(', ',array_keys($cols)));
 $rows=[];$seen=[];$line=1;
 while(($cells=fgetcsv($f,0,$delimiter,'"',''))!==false){
  $line++;if($cells===[null]||count(array_filter($cells,static fn($v)=>trim((string)$v)!==''))===0)continue;
  if(count($rows)>=500)throw new InvalidArgumentException('Maximal 500 Einträge je Import.');
  $rawRow=[];foreach($idx as $key=>$col)$rawRow[$key]=(string)($cells[$col]??'');
  try{$n=ffNormalize($rawRow);if(isset($seen[$n['ref']]))throw new InvalidArgumentException('Referenz mehrfach in Datei; je Dankeschön eine eindeutige Referenz vergeben.');$seen[$n['ref']]=true;
   $rows[]=['reference'=>$n['ref'],'name'=>$n['name'],'email'=>$n['email'],'package'=>$n['package'],'lang'=>$n['lang']];
  }catch(InvalidArgumentException $e){throw new InvalidArgumentException('Zeile '.$line.': '.$e->getMessage());}
 }
 fclose($f);if(!$rows)throw new InvalidArgumentException('Keine Datensätze gefunden.');return $rows;
}
function ffFind(PDO $db,int $id): array {
 $q=$db->prepare('SELECT * FROM founders_supporters WHERE id=?');$q->execute([$id]);$r=$q->fetch();
 if(!$r)throw new InvalidArgumentException('Eintrag nicht gefunden.');return $r;
}
function ffIssue(PDO $db,int $id): array {
 $r=ffFind($db,$id);if($r['state']!=='active'||$r['package']==='free')throw new InvalidArgumentException('Kein Zertifikatsanspruch.');
 if($r['certificate_number']!==null)return $r;
 $now=time();$number=sprintf('TF-%s-%06d',gmdate('Y',$now),$id);
 $q=$db->prepare("UPDATE founders_supporters SET certificate_number=?,issued_at=? WHERE id=? AND certificate_number IS NULL AND state='active'");
 $q->execute([$number,$now,$id]);return ffFind($db,$id);
}
function ffPublicEligible(array $r): bool {
 return $r['state']==='active' && in_array('acknowledgement',ffPackages()[$r['package']][2]??[],true);
}
function ffPublicRows(PDO $db): array {
 $rows=$db->query("SELECT package,public_name,state,consent_note,consent_at,publish FROM founders_supporters WHERE state='active' AND publish=1 AND consent_at IS NOT NULL AND public_name<>'' AND consent_note<>'' ORDER BY public_name COLLATE NOCASE")->fetchAll(PDO::FETCH_ASSOC);
 return array_values(array_filter($rows,'ffPublicEligible'));
}
function ffTemplate(array $r): string {
 if(!isset(ffPackages()[$r['package']])||$r['package']==='free'||!in_array($r['lang'],['de','en'],true))throw new InvalidArgumentException('Zertifikatsvorlage ungültig.');
 $file=dirname(__DIR__).'/assets/founders-certificates/'.$r['lang'].'-'.$r['package'].'.jpg';
 if(!is_file($file))throw new RuntimeException('Zertifikatsvorlage fehlt.');return $file;
}
function ffPdfText(string $text): string {
 $encoded=@iconv('UTF-8','Windows-1252',$text);
 if($encoded===false||@iconv('Windows-1252','UTF-8',$encoded)!==$text)throw new InvalidArgumentException('Der Name enthält Zeichen außerhalb des PDF-Zeichensatzes. Bitte die Druckansicht verwenden.');
 return $encoded;
}
function ffCertificatePdf(array $r): string {
 if($r['state']!=='active'||empty($r['certificate_number'])||empty($r['issued_at']))throw new InvalidArgumentException('Zertifikat noch nicht ausgestellt oder storniert.');
 $image=ffTemplate($r);$jpeg=file_get_contents($image);$dims=getimagesize($image);
 if(!$jpeg||!$dims||$dims[2]!==IMAGETYPE_JPEG)throw new RuntimeException('Vorlage ungültig.');
 $name=ffPdfText($r['name']);$lines=strlen($name)>48?explode("\n",wordwrap($name,48,"\n",true)):[$name];
 if(count($lines)>3)throw new InvalidArgumentException('Sehr langer Name: bitte Druckansicht verwenden.');
 $commands="q 841.89 0 0 595.28 0 0 cm /Im0 Do Q\n0.035 0.15 0.21 rg\n";
 $lineY=321;
 foreach($lines as $line){$size=min(32,max(12,440/(max(1,strlen($line))*.83)));$commands.=sprintf("BT /F1 %.2F Tf 56 %d Td <%s> Tj ET\n",$size,$lineY,bin2hex($line));$lineY-=32;}
 $date=gmdate($r['lang']==='de'?'d.m.Y':'Y-m-d',(int)$r['issued_at']);
 foreach([[56,$r['certificate_number']],[345,$date]] as [$x,$value])$commands.=sprintf("BT /F2 12 Tf %d 90 Td <%s> Tj ET\n",$x,bin2hex(ffPdfText($value)));
 $objects=['<< /Type /Catalog /Pages 2 0 R >>','<< /Type /Pages /Kids [3 0 R] /Count 1 >>','<< /Type /Page /Parent 2 0 R /MediaBox [0 0 841.89 595.28] /Resources << /Font << /F1 4 0 R /F2 5 0 R >> /XObject << /Im0 6 0 R >> >> /Contents 7 0 R >>','<< /Type /Font /Subtype /Type1 /BaseFont /Times-Roman /Encoding /WinAnsiEncoding >>','<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>',
 '<< /Type /XObject /Subtype /Image /Width '.$dims[0].' /Height '.$dims[1].' /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length '.strlen($jpeg)." >>\nstream\n".$jpeg."\nendstream",
 '<< /Length '.strlen($commands)." >>\nstream\n".$commands."endstream"];
 $pdf="%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";$offsets=[0];foreach($objects as $i=>$obj){$offsets[]=strlen($pdf);$pdf.=($i+1)." 0 obj\n".$obj."\nendobj\n";}
 $xref=strlen($pdf);$pdf.="xref\n0 ".count($offsets)."\n0000000000 65535 f \n";foreach(array_slice($offsets,1) as $offset)$pdf.=sprintf("%010d 00000 n \n",$offset);
 return $pdf."trailer\n<< /Size ".count($offsets)." /Root 1 0 R >>\nstartxref\n".$xref."\n%%EOF\n";
}
function ffCsvSafe(string $v): string {return preg_match('/^[\s]*[=+\-@]/u',$v)?"'".$v:$v;}
