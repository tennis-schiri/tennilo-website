<?php
declare(strict_types=1);
/*
 * TENNILO Newsletter-Layouts. For the private mail editor only.
 * Real TENNILO wordmark is shown in the browser and embedded into outbound
 * multipart email as a CID image. No external tracking, scripts or fonts.
 */
function tennilo_email_content(string $message): string {
    $message = trim(str_replace(["\r\n", "\r"], "\n", $message));
    $paragraphs = preg_split('/\n\s*\n/', $message) ?: [];
    $html = '';
    foreach ($paragraphs as $p) {
        $safe = htmlspecialchars(trim($p), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        if ($safe !== '') {
            $html .= '<p style="color:#42616a;font:16px/1.65 Arial,Helvetica,sans-serif;margin:0 0 20px">'
                .nl2br($safe, false).'</p>';
        }
    }
    return $html;
}
function tennilo_email_button_url(string $url): bool {
    if ($url === '' || strlen($url) > 2048 || preg_match('/[\x00-\x20\x7f]/', $url)
        || !filter_var($url, FILTER_VALIDATE_URL)) return false;
    $parts = parse_url($url);
    if (!is_array($parts) || ($parts['scheme'] ?? null) !== 'https'
        || empty($parts['host']) || isset($parts['user']) || isset($parts['pass'])
        || isset($parts['port'])) return false;
    $host = (string)$parts['host'];
    return strpos($host, '.') !== false
        && filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) !== false;
}
function tennilo_email_inner(
    string $subject,
    string $message,
    ?string $unsubscribeUrl = null,
    ?string $logoSrc = null,
    string $topic = 'founders',
    array $options = []
): string {
    $esc = static fn(mixed $v): string => htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $heading = trim((string)($options['headline'] ?? ''));
    if ($heading === '') $heading = $subject;
    $layout = (string)($options['layout'] ?? 'standard');
    if (!in_array($layout, ['standard', 'spotlight', 'compact'], true)) $layout = 'standard';
    $label = match ($topic) {
        'newsletter' => 'TENNILO NEWSLETTER',
        'beta' => 'TENNILO BETATEST',
        'crowdfunding' => 'TENNILO CROWDFUNDING',
        default => 'TENNILO FOUNDERS'
    };
    $sourceLabel = $topic === 'newsletter'
        ? 'den allgemeinen TENNILO-Newsletter'
        : 'entsprechende Informationen von TENNILO Founders';
    $mailLink = $topic === 'newsletter' ? 'news@tennilo.de' : 'founders@tennilo.de';
    $unsubscribe = $unsubscribeUrl ?? ($topic === 'newsletter'
        ? 'https://tennilo.de/newsletter.php?action=manage'
        : 'https://tennilo.de/founders.php?action=unsubscribe-request');
    $logoSrc = $logoSrc ?? 'https://tennilo.de/assets/tennilo-logo.png';
    $main = tennilo_email_content($message);

    $buttonLabel = trim((string)($options['button_label'] ?? ''));
    $buttonUrl = trim((string)($options['button_url'] ?? ''));
    $cta = '';
    if ($buttonLabel !== '' && tennilo_email_button_url($buttonUrl)) {
        $buttonBg = $layout === 'spotlight' ? '#e7ff6a' : '#092b36';
        $buttonText = $layout === 'spotlight' ? '#092b36' : '#ffffff';
        $cta = '<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:9px 0 22px"><tr><td bgcolor="'.$buttonBg.'" style="background:'.$buttonBg.';border-radius:9px">'
            .'<a href="'.$esc($buttonUrl).'" target="_blank" style="display:inline-block;padding:15px 22px;font:700 15px/1.3 Arial,Helvetica,sans-serif;text-decoration:none;color:'.$buttonText.'">'
            .$esc($buttonLabel).' &#8599;</a></td></tr></table>';
    }
    $stripe = $layout === 'spotlight' ? '#ef7146' : ($layout === 'compact' ? '#e7ff6a' : '#04c9c2');
    $articleBackground = $layout === 'spotlight' ? '#f5faf7' : '#ffffff';
    $articlePadding = $layout === 'compact' ? '26px 32px 14px' : '34px 34px 22px';
    $titleSize = $layout === 'compact' ? '24px' : '29px';
    $titleIntro = $layout === 'spotlight'
        ? '<p style="font:700 12px/1.5 Arial,Helvetica,sans-serif;letter-spacing:1.5px;color:#ef7146;margin:0 0 13px">WENIGER ORGA. MEHR TENNIS.</p>'
        : '';
    return '<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background:#f6f8f4"><tr><td align="center" style="padding:32px 12px">'
      .'<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="max-width:580px;background:#ffffff;border:1px solid #dae4e0;border-radius:16px;overflow:hidden">'
      .'<tr><td style="background:#f6f8f4;padding:27px 34px 21px">'
      .'<a href="https://tennilo.de/" style="text-decoration:none"><img src="'.$esc($logoSrc).'" width="184" height="34" alt="TENNILO" border="0" style="display:block;width:184px;height:auto;max-width:100%;border:0;outline:0;text-decoration:none"></a>'
      .'<p style="color:#42616a;font:700 10px/1.5 Arial,Helvetica,sans-serif;letter-spacing:1.6px;margin:13px 0 0">VEREINSTURNIERE. EINFACH ORGANISIERT.</p></td></tr>'
      .'<tr><td style="height:5px;line-height:5px;font-size:0;background:'.$stripe.'">&nbsp;</td></tr>'
      .'<tr><td style="background:'.$articleBackground.';padding:'.$articlePadding.'">'
      .$titleIntro
      .'<p style="font:700 11px/1.5 Arial,Helvetica,sans-serif;letter-spacing:1.7px;color:#ef7146;margin:0 0 13px">'.$label.'</p>'
      .'<h1 style="color:#092b36;font:700 '.$titleSize.'/1.2 Arial,Helvetica,sans-serif;letter-spacing:-0.4px;margin:0 0 25px">'.$esc($heading).'</h1>'
      .$main.$cta.'</td></tr>'
      .'<tr><td style="background:#f6f8f4;padding:24px 34px;border-top:1px solid #dae4e0">'
      .'<p style="font:13px/1.65 Arial,Helvetica,sans-serif;color:#42616a;margin:0 0 12px">Du erhältst diese Nachricht, weil du '.$sourceLabel.' angefordert hast.</p>'
      .'<p style="font:13px/1.65 Arial,Helvetica,sans-serif;margin:0 0 14px"><a style="color:#008b88;text-decoration:underline" href="'.$esc($unsubscribe).'">'.($topic === 'newsletter' ? 'Newsletter abbestellen' : 'Von der Founders-Warteliste abmelden').'</a> · <a href="https://tennilo.de/newsletter.php?action=manage" style="color:#008b88">E-Mail-Themen verwalten</a></p>'
      .'<p style="font:12px/1.5 Arial,Helvetica,sans-serif;color:#60767b;margin:0">TENNILO · Ein Projekt von Tennis-Schiri<br><a href="https://tennilo.de/impressum.html" style="color:#008b88">Impressum</a> · <a href="https://tennilo.de/datenschutz.html" style="color:#008b88">Datenschutz</a> · <a href="mailto:'.$mailLink.'" style="color:#008b88">Kontakt</a></p>'
      .'</td></tr></table></td></tr></table>';
}
function tennilo_email_html(
    string $subject,
    string $message,
    ?string $unsubscribeUrl = null,
    ?string $logoSrc = null,
    string $topic = 'founders',
    array $options = []
): string {
    $esc = static fn(mixed $s): string => htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $preheader = trim((string)($options['preheader'] ?? ''));
    if ($preheader === '') $preheader = 'Neues rund um TENNILO – Vereinsturniere einfacher organisieren.';
    return '<!doctype html><html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>'
        .$esc($subject).'</title></head><body style="padding:0;margin:0;background:#f6f8f4">'
        .'<div style="display:none!important;visibility:hidden;opacity:0;color:transparent;height:0;width:0;overflow:hidden;font-size:1px;line-height:1px">'
        .$esc($preheader).'</div>'
        .tennilo_email_inner($subject,$message,$unsubscribeUrl,$logoSrc,$topic,$options)
        .'</body></html>';
}
