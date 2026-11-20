<?php
declare(strict_types=1);
// Retired TENNILO first-party counter. This no-op file overwrites any
// previously deployed counting endpoint during the manual FTPS upload.
// The website now uses ALL-INKL's built-in server statistics.
header('Content-Type: image/gif');
header('Cache-Control: no-store, max-age=0');
header('X-Robots-Tag: noindex, nofollow');
echo base64_decode('R0lGODlhAQABAAD/ACwAAAAAAQABAAACADs=', true);
