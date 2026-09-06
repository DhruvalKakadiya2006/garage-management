<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Database Connection Name
    |--------------------------------------------------------------------------
    |
    | The default connection is set to sqlite, which is the connection
    | used by this application in the local development environment.
    |
    */

    'default' => env('DB_CONNECTION', 'sqlite'),

    /*
    |--------------------------------------------------------------------------
    | Database Connections
    |--------------------------------------------------------------------------
    |
    | Below are all of the database connections defined for your application.
    | An example configuration is provided for each database system which
    | is supported by Laravel. You're free to add / remove connections.
    | All values are static; do not use env() here so that the config
    | remains valid when cached (php artisan config:cache).
    |
    */

    'connections' => [

        'legacy' => [
            'driver' => 'sqlsrv',
            'host' => env('LEGACY_DB_HOST', 'localhost\SQLEXPRESS'),
            'database' => env('LEGACY_DB_DATABASE', 'VSMS4700011083'),
            'username' => env('LEGACY_DB_USERNAME', ''),
            'password' => env('LEGACY_DB_PASSWORD', ''),
            'trust_server_certificate' => true,
        ],

        'sqlite' => [

            'driver' => 'sqlite',
            'url' => env('DB_URL'),
            'database' => env('DB_DATABASE', database_path('database.sqlite')),
            'prefix' => '',
            'foreign_key_constraints' => env('DB_FOREIGN_KEYS', true),
            // Wait up to 5s for a writer's lock instead of failing instantly
            // ("database is locked") when two requests write concurrently.
            'busy_timeout' => 5000,
            'journal_mode' => 'wal',
            'synchronous' => null,
            'transaction_mode' => 'DEFERRED',

        ],

        'mysql' => [

            'driver' => 'mysql',
            'url' => '',
            'host' => '127.0.0.1',
            'port' => '3306',
            'database' => 'laravel',
            'username' => 'root',
            'password' => '',
            'unix_socket' => '',
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            'options' => [],

        ],

        'mariadb' => [

            'driver' => 'mariadb',
            'url' => '',
            'host' => '127.0.0.1',
            'port' => '3306',
            'database' => 'laravel',
            'username' => 'root',
            'password' => '',
            'unix_socket' => '',
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            'options' => [],

        ],

        'pgsql' => [

            'driver' => 'pgsql',
            'url' => '',
            'host' => '127.0.0.1',
            'port' => '5432',
            'database' => 'laravel',
            'username' => 'root',
            'password' => '',
            'charset' => 'utf8',
            'prefix' => '',
            'prefix_indexes' => true,
            'search_path' => 'public',
            'sslmode' => 'prefer',

        ],

        'sqlsrv' => [

            'driver' => 'sqlsrv',
            'url' => '',
            'host' => 'localhost',
            'port' => '1433',
            'database' => 'laravel',
            'username' => 'root',
            'password' => '',
            'charset' => 'utf8',
            'prefix' => '',
            'prefix_indexes' => true,

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
    | All values are static; do not use env() here so that the config
    | remains valid when cached (php artisan config:cache).
    |
    */

    'redis' => [

        'client' => 'phpredis',

        'options' => [

            'cluster' => 'redis',
            'prefix' => 'laravel-database-',
            'persistent' => false,

        ],

        'default' => [

            'url' => '',
            'host' => '127.0.0.1',
            'username' => '',
            'password' => '',
            'port' => '6379',
            'database' => '0',
            'max_retries' => 3,
            'backoff_algorithm' => 'decorrelated_jitter',
            'backoff_base' => 100,
            'backoff_cap' => 1000,

        ],

        'cache' => [

            'url' => '',
            'host' => '127.0.0.1',
            'username' => '',
            'password' => '',
            'port' => '6379',
            'database' => '1',
            'max_retries' => 3,
            'backoff_algorithm' => 'decorrelated_jitter',
            'backoff_base' => 100,
            'backoff_cap' => 1000,

        ],

    ],

];
