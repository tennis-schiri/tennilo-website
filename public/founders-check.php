<?php
declare(strict_types=1);
// Der temporäre Servercheck wurde nach erfolgreicher Prüfung deaktiviert.
http_response_code(404);
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow');
exit;
