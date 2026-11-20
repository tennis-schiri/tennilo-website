<?php
declare(strict_types=1);
// Retired: use ALL-INKL's built-in server statistics instead.
// Kept as a redirect for previously deployed/bookmarked dashboard URLs.
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow');
header('Location: /admin/', true, 302);
exit;
