<?php

// daemon
define('ISK_HOST', '127.0.0.1');
define('ISK_PORT', 31128);
define('ISK_RPC_PATH', '/RPC');

// ID базы daemon
define('ISK_DB_ID', 2008);

// общая папка (volume)
define('HOST_SHARED_DIR', rtrim(str_replace('\\', '/', __DIR__ . '/iskdaemon_data'), '/'));
define('CONTAINER_SHARED_DIR', '/opt/iskdaemon/src/src/data'); // как видит контейнер

define('UPLOAD_SUBDIR', 'upload');
define('THUMBS_SUBDIR', 'thumbs');

//MySQL
define('DB_DSN',  'mysql:host=127.0.0.1;port=3306;dbname=isk_api;charset=utf8mb4');
define('DB_USER', 'root');
define('DB_PASS', '');