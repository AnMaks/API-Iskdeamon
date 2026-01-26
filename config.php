<?php

// где daemon
define('ISK_HOST', '127.0.0.1');
define('ISK_PORT', 31128);
define('ISK_RPC_PATH', '/RPC'); // чаще всего именно так

// ID базы daemon
define('ISK_DB_ID', 2008);

// общая папка (volume)
define('HOST_SHARED_DIR', 'D:\Lucru\api_iskdeamon\iskdaemon_data'); // Windows путь (можно через /)
define('CONTAINER_SHARED_DIR', '/opt/iskdaemon/src/src/data'); // как видит контейнер

define('UPLOAD_SUBDIR', 'upload');
