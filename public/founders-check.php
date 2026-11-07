<?php
/* Temporary, deliberately low-information PHP compatibility check.
   No secrets or absolute filesystem paths are printed. */
header('Content-Type: text/plain; charset=UTF-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
header('X-Robots-Tag: noindex, nofollow');
echo "TENNILO Founders – Servercheck\n\n";
echo "PHP-Ausfuehrung: OK\n";
echo "PHP 8.1 oder neuer: ".(version_compare(PHP_VERSION,'8.1','>=')?'JA':'NEIN')."\n";
echo "PDO SQLite: ".(class_exists('PDO') && in_array('sqlite',PDO::getAvailableDrivers())?'JA':'NEIN')."\n";
echo "Mail-Funktion vorhanden: ".(function_exists('mail')?'JA':'NEIN')."\n";
$base=dirname(__DIR__);
echo "Private Konfigurationsdatei vorhanden: ".(is_file($base.'/tennilo-founders-config.php')?'JA':'NEIN')."\n";
echo "Private Konfigurationsdatei lesbar: ".(is_readable($base.'/tennilo-founders-config.php')?'JA':'NEIN')."\n";
echo "Private Konfigurationsdatei beschreibbar: ".(is_writable($base.'/tennilo-founders-config.php')?'JA':'NEIN')."\n";
echo "Privater Datenordner vorhanden: ".(is_dir($base.'/tennilo-founders-data')?'JA':'NEIN')."\n";
echo "Privater Datenordner beschreibbar: ".(is_writable($base.'/tennilo-founders-data')?'JA':'NEIN')."\n";
echo "\nKeine Konfigurationsinhalte, Zugangsdaten oder Serverpfade werden ausgegeben.\n";
