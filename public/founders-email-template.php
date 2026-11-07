<?php
declare(strict_types=1);
/*
 * TENNILO Founders email composition.
 * Plain text is kept as an alternative; no remote images, trackers or scripts.
 */
function tennilo_email_content(string $message): string {
 $message=trim(str_replace(["\r\n","\r"],"\n",$message));
 $paragraphs=preg_split('/\n\s*\n/', $message) ?: [];
 $html='';
 foreach ($paragraphs as $p) {
  $safe=htmlspecialchars(trim($p),ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
  if($safe==='')continue;
  $html.='<p style="color:#233e47;font:16px/1.65 Arial,Helvetica,sans-serif;margin:0 0 20px">'.nl2br($safe,false).'</p>';
 }
 return $html;
}
function tennilo_email_inner(string $subject,string $message,?string $unsubscribeUrl=null): string {
 $safeTitle=htmlspecialchars($subject,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
 $link=$unsubscribeUrl===null?'https://tennilo.de/founders.php?action=unsubscribe-request':$unsubscribeUrl;
 $safeLink=htmlspecialchars($link,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
 $main=tennilo_email_content($message);
 return '<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background:#eef3ef"><tr><td align="center" style="padding:32px 14px">'
  .'<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="max-width:580px;background:#ffffff;border-radius:18px;overflow:hidden">'
  .'<tr><td style="background:#092b36;padding:28px 34px 26px"><p style="color:#c4f163;font:bold 28px/1.2 Arial,Helvetica,sans-serif;letter-spacing:1px;margin:0">TENNILO<span style="color:white">.</span></p>'
  .'<p style="color:#d4e7e4;font:11px/1.5 Arial,Helvetica,sans-serif;letter-spacing:1.8px;margin:9px 0 0">VEREINSTURNIERE. EINFACH ORGANISIERT.</p></td></tr>'
  .'<tr><td style="padding:34px 34px 18px"><p style="font:700 11px/1.5 Arial,Helvetica,sans-serif;letter-spacing:1.6px;color:#007e83;margin:0 0 12px">TENNILO FOUNDERS</p>'
  .'<h1 style="color:#092b36;font:700 26px/1.23 Arial,Helvetica,sans-serif;margin:0 0 26px">'.$safeTitle.'</h1>'
  .$main.'</td></tr>'
  .'<tr><td style="background:#f5f8f6;padding:24px 34px;border-top:1px solid #e5ece8">'
  .'<p style="font:13px/1.65 Arial,Helvetica,sans-serif;color:#52686e;margin:0 0 12px">Du erhältst diese Nachricht, weil du Informationen von TENNILO Founders zu diesem Thema angefordert hast.</p>'
  .'<p style="font:13px/1.65 Arial,Helvetica,sans-serif;color:#52686e;margin:0 0 12px"><a style="color:#007e83;text-decoration:underline" href="'.$safeLink.'">Von der Founders-Warteliste abmelden</a></p>'
  .'<p style="font:12px/1.5 Arial,Helvetica,sans-serif;color:#60777b;margin:0">TENNILO · Ein Projekt von Tennis-Schiri<br><a href="https://tennilo.de/impressum.html" style="color:#007e83">Impressum</a> · <a href="https://tennilo.de/datenschutz.html" style="color:#007e83">Datenschutz</a> · <a href="mailto:founders@tennilo.de" style="color:#007e83">Kontakt</a></p>'
  .'</td></tr></table></td></tr></table>';
}
function tennilo_email_html(string $subject,string $message,?string $unsubscribeUrl=null): string {
 return '<!doctype html><html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>'
  .htmlspecialchars($subject,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8')
  .'</title></head><body style="padding:0;margin:0;background:#eef3ef">'
  .tennilo_email_inner($subject,$message,$unsubscribeUrl).'</body></html>';
}
