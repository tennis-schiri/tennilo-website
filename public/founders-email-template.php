<?php
declare(strict_types=1);
/* TENNILO brand colors mirror public/assets/styles.css.
 * The original /assets/tennilo-logo.png is used for previews and embedded
 * as an inline MIME asset in outbound mail (no third-party image requests). */
function tennilo_email_content(string $message): string {
 $message=trim(str_replace(["\r\n","\r"],"\n",$message));
 $paragraphs=preg_split('/\n\s*\n/', $message) ?: [];
 $html='';
 foreach ($paragraphs as $p) {
  $safe=htmlspecialchars(trim($p),ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
  if($safe==='')continue;
  $html.='<p style="color:#42616a;font:16px/1.65 Arial,Helvetica,sans-serif;margin:0 0 20px">'.nl2br($safe,false).'</p>';
 }
 return $html;
}
function tennilo_email_inner(string $subject,string $message,?string $unsubscribeUrl=null,?string $logoSrc=null,string $topic='founders'): string {
 $safeTitle=htmlspecialchars($subject,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
 $link=$unsubscribeUrl===null?'https://tennilo.de/founders.php?action=unsubscribe-request':$unsubscribeUrl;
 $safeLink=htmlspecialchars($link,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
 $logoSrc=$logoSrc??'https://tennilo.de/assets/tennilo-logo.png';
 $safeLogo=htmlspecialchars($logoSrc,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
 $main=tennilo_email_content($message);
 $titleLabel=$topic==='newsletter'?'TENNILO NEWSLETTER':'TENNILO FOUNDERS';
 $sourceLabel=$topic==='newsletter'?'den allgemeinen TENNILO-Newsletter':'entsprechende Informationen von TENNILO Founders';
 $mailLink=$topic==='newsletter'?'news@tennilo.de':'founders@tennilo.de';
 return '<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background:#f6f8f4"><tr><td align="center" style="padding:32px 12px">'
  .'<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="max-width:580px;background:#ffffff;border:1px solid #dae4e0;border-radius:16px;overflow:hidden">'
  .'<tr><td style="background:#f6f8f4;padding:27px 34px 21px">'
  .'<a href="https://tennilo.de/" style="text-decoration:none"><img src="'.$safeLogo.'" width="184" height="34" alt="TENNILO" border="0" style="display:block;width:184px;height:auto;max-width:100%;border:0;outline:0;text-decoration:none"></a>'
  .'<p style="color:#42616a;font:700 10px/1.5 Arial,Helvetica,sans-serif;letter-spacing:1.6px;margin:13px 0 0">VEREINSTURNIERE. EINFACH ORGANISIERT.</p></td></tr>'
  .'<tr><td style="height:4px;line-height:4px;font-size:0;background:#04c9c2">&nbsp;</td></tr>'
  .'<tr><td style="padding:34px 34px 22px">'
  .'<p style="font:700 11px/1.5 Arial,Helvetica,sans-serif;letter-spacing:1.7px;color:#ef7146;margin:0 0 13px">'.$titleLabel.'</p>'
  .'<h1 style="color:#092b36;font:700 27px/1.2 Arial,Helvetica,sans-serif;letter-spacing:-0.3px;margin:0 0 26px">'.$safeTitle.'</h1>'
  .$main.'</td></tr>'
  .'<tr><td style="background:#f6f8f4;padding:24px 34px;border-top:1px solid #dae4e0">'
  .'<p style="font:13px/1.65 Arial,Helvetica,sans-serif;color:#42616a;margin:0 0 12px">Du erhältst diese Nachricht, weil du '.$sourceLabel.' angefordert hast.</p>'
  .'<p style="font:13px/1.65 Arial,Helvetica,sans-serif;margin:0 0 14px"><a style="color:#008b88;text-decoration:underline" href="'.$safeLink.'">'.($topic==='newsletter'?'Newsletter abbestellen':'Von der Founders-Warteliste abmelden').'</a> · <a href="https://tennilo.de/newsletter.php?action=manage" style="color:#008b88">E-Mail-Themen verwalten</a></p>'
  .'<p style="font:12px/1.5 Arial,Helvetica,sans-serif;color:#60767b;margin:0">TENNILO · Ein Projekt von Tennis-Schiri<br><a href="https://tennilo.de/impressum.html" style="color:#008b88">Impressum</a> · <a href="https://tennilo.de/datenschutz.html" style="color:#008b88">Datenschutz</a> · <a href="mailto:'.$mailLink.'" style="color:#008b88">Kontakt</a></p>'
  .'</td></tr></table></td></tr></table>';
}
function tennilo_email_html(string $subject,string $message,?string $unsubscribeUrl=null,?string $logoSrc=null,string $topic='founders'): string {
 return '<!doctype html><html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>'
  .htmlspecialchars($subject,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8')
  .'</title></head><body style="padding:0;margin:0;background:#f6f8f4">'
   .tennilo_email_inner($subject,$message,$unsubscribeUrl,$logoSrc,$topic).'</body></html>';
}
