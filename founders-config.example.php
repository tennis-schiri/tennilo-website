<?php
/* Copy to /tennilo-founders-config.php ONE LEVEL ABOVE the public tennilo.de webroot.
   NEVER upload this file to GitHub or into the publicly served directory.
   chmod 600 when supported. Enable only after testing mail(), PDO_SQLITE and https.
*/
return [
  'enabled' => false,
  'database' => '/ABSOLUTE/PRIVATE/PATH/tennilo-founders.sqlite',
  'key' => 'REPLACE_WITH_LONG_RANDOM_SECRET_64_PLUS_HEX_CHARACTERS',
  'admin_password_hash' => 'REPLACE_WITH_PASSWORD_HASH_FROM_PHP_PASSWORD_HASH'
];
