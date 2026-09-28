<?php

use Illuminate\Support\Str;

$dbUrl = env('MYSQL_URL') ?: env('DATABASE_URL') ?: env('DB_URL');
$dbUrlParsed = $dbUrl ? parse_url($dbUrl) : [];

$isRailwayRuntime = ! empty(env('RAILWAY_ENVIRONMENT')) || getenv('RAILWAY_ENVIRONMENT') !== false || getenv('RAILWAY_PROJECT_ID') !== false;
$mysqlUrl = $dbUrl;
$mysqlHost = $dbUrlParsed['host'] ?? env('DB_HOST', env('MYSQL_HOST', env('MYSQLHOST', env('RAILWAY_MYSQL_HOST', '127.0.0.1'))));

if (! $isRailwayRuntime && is_string($mysqlHost) && str_contains($mysqlHost, 'railway.internal')) {
    $mysqlHost = '127.0.0.1';
}

$mysqlPort = $dbUrlParsed['port'] ?? env('DB_PORT', env('MYSQL_PORT', env('MYSQLPORT', env('RAILWAY_MYSQL_PORT', '3306'))));
$mysqlDatabase = isset($dbUrlParsed['path']) ? ltrim($dbUrlParsed['path'], '/') : env('DB_DATABASE', env('MYSQL_DATABASE', env('MYSQLDATABASE', env('RAILWAY_MYSQL_DATABASE', 'laravel'))));
$mysqlUsername = $dbUrlParsed['user'] ?? env('DB_USERNAME', env('MYSQL_USER', env('MYSQLUSER', env('RAILWAY_MYSQL_USER', 'root'))));
$mysqlPassword = $dbUrlParsed['pass'] ?? env('DB_PASSWORD', env('MYSQL_PASSWORD', env('MYSQLPASSWORD', env('RAILWAY_MYSQL_PASSWORD', ''))));

if (is_string($mysqlHost) && preg_match('/^\$\{([^}]+)\}$/', $mysqlHost, $matches) && env($matches[1]) !== null) {
    $mysqlHost = env($matches[1]);
}

if (is_string($mysqlPort) && preg_match('/^\$\{([^}]+)\}$/', $mysqlPort, $matches) && env($matches[1]) !== null) {
    $mysqlPort = env($matches[1]);
}

if (is_string($mysqlDatabase) && preg_match('/^\$\{([^}]+)\}$/', $mysqlDatabase, $matches) && env($matches[1]) !== null) {
    $mysqlDatabase = env($matches[1]);
}

if (is_string($mysqlUsername) && preg_match('/^\$\{([^}]+)\}$/', $mysqlUsername, $matches) && env($matches[1]) !== null) {
    $mysqlUsername = env($matches[1]);
}

if (is_string($mysqlPassword) && preg_match('/^\$\{([^}]+)\}$/', $mysqlPassword, $matches) && env($matches[1]) !== null) {
    $mysqlPassword = env($matches[1]);
}

return [

    /*
    |--------------------------------------------------------------------------
    | Default Database Connection Name
    |--------------------------------------------------------------------------
    |
    | Here you may specify which of the database connections below you wish
    | to use as your default connection for database operations. This is
    | the connection which will be utilized unless another connection
    | is explicitly specified when you execute a query / statement.
    |
    */

    'default' => env('DB_CONNECTION', 'mysql'),

    /*
    |--------------------------------------------------------------------------
    | Database Connections
    |--------------------------------------------------------------------------
    |
    | Below are all of the database connections defined for your application.
    | An example configuration is provided for each database system which
    | is supported by Laravel. You're free to add / remove connections.
    |
    */

    'connections' => [

        'sqlite' => [
            'driver' => 'sqlite',
            'url' => env('DB_URL', env('DATABASE_URL')),
            'database' => env('DB_DATABASE', database_path('database.sqlite')),
            'prefix' => '',
            'foreign_key_constraints' => env('DB_FOREIGN_KEYS', true),
            'busy_timeout' => null,
            'journal_mode' => null,
            'synchronous' => null,
            'transaction_mode' => 'DEFERRED',
        ],

        'mysql' => [
            'driver' => 'mysql',
            'url' => $mysqlUrl,
            'host' => $mysqlHost,
            'port' => $mysqlPort,
            'database' => $mysqlDatabase,
            'username' => $mysqlUsername,
            'password' => $mysqlPassword,
            'unix_socket' => env('DB_SOCKET', ''),
            'charset' => env('DB_CHARSET', 'utf8mb4'),
            'collation' => env('DB_COLLATION', 'utf8mb4_unicode_ci'),
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                (PHP_VERSION_ID >= 80500 ? Mysql::ATTR_SSL_CA : PDO::MYSQL_ATTR_SSL_CA) => env('MYSQL_ATTR_SSL_CA'),
            ]) : [],
        ],

        'mariadb' => [
            'driver' => 'mariadb',
            'url' => env('DB_URL'),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '3306'),
            'database' => env('DB_DATABASE', 'laravel'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'unix_socket' => env('DB_SOCKET', ''),
            'charset' => env('DB_CHARSET', 'utf8mb4'),
            'collation' => env('DB_COLLATION', 'utf8mb4_unicode_ci'),
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                (PHP_VERSION_ID >= 80500 ? Mysql::ATTR_SSL_CA : PDO::MYSQL_ATTR_SSL_CA) => env('MYSQL_ATTR_SSL_CA'),
            ]) : [],
        ],

        'pgsql' => [
            'driver' => 'pgsql',
            'url' => env('DB_URL', env('DATABASE_URL')),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '5432'),
            'database' => env('DB_DATABASE', 'laravel'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => env('DB_CHARSET', 'utf8'),
            'prefix' => '',
            'prefix_indexes' => true,
            'search_path' => 'public',
            'sslmode' => env('DB_SSLMODE', 'prefer'),
        ],

        'sqlsrv' => [
            'driver' => 'sqlsrv',
            'url' => env('DB_URL', env('DATABASE_URL')),
            'host' => env('DB_HOST', 'localhost'),
            'port' => env('DB_PORT', '1433'),
            'database' => env('DB_DATABASE', 'laravel'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => env('DB_CHARSET', 'utf8'),
            'prefix' => '',
            'prefix_indexes' => true,
            // 'encrypt' => env('DB_ENCRYPT', 'yes'),
            // 'trust_server_certificate' => env('DB_TRUST_SERVER_CERTIFICATE', 'false'),
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Migration Repository Table
    |--------------------------------------------------------------------------
    |
    | This table keeps track of all the migrations that have already run for
    | your application. Using this information, we can determine which of
    | the migrations on disk haven't actually been run on the database.
    |
    */

    'migrations' => [
        'table' => 'migrations',
        'update_date_on_publish' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Redis Databases
    |--------------------------------------------------------------------------
    |
    | Redis is an open source, fast, and advanced key-value store that also
    | provides a richer body of commands than a typical key-value system
    | such as Memcached. You may define your connection settings here.
    |
    */

    'redis' => [

        'client' => env('REDIS_CLIENT', 'phpredis'),

        'options' => [
            'cluster' => env('REDIS_CLUSTER', 'redis'),
            'prefix' => env('REDIS_PREFIX', Str::slug((string) env('APP_NAME', 'laravel')).'-database-'),
            'persistent' => env('REDIS_PERSISTENT', false),
        ],

        'default' => [
            'url' => env('REDIS_URL'),
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'username' => env('REDIS_USERNAME'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_DB', '0'),
            'max_retries' => env('REDIS_MAX_RETRIES', 3),
            'backoff_algorithm' => env('REDIS_BACKOFF_ALGORITHM', 'decorrelated_jitter'),
            'backoff_base' => env('REDIS_BACKOFF_BASE', 100),
            'backoff_cap' => env('REDIS_BACKOFF_CAP', 1000),
        ],

        'cache' => [
            'url' => env('REDIS_URL'),
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'username' => env('REDIS_USERNAME'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_CACHE_DB', '1'),
            'max_retries' => env('REDIS_MAX_RETRIES', 3),
            'backoff_algorithm' => env('REDIS_BACKOFF_ALGORITHM', 'decorrelated_jitter'),
            'backoff_base' => env('REDIS_BACKOFF_BASE', 100),
            'backoff_cap' => env('REDIS_BACKOFF_CAP', 1000),
        ],

    ],

];
