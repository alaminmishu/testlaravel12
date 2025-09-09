<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Http;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/server-info', function () {
    return response()->json([
        // Laravel & PHP
        'laravel_version' => app()->version(),
        'php_version'     => PHP_VERSION,
        'php_sapi'        => php_sapi_name(),
        'php_extensions'  => get_loaded_extensions(),

        // Application
        'app_env'         => config('app.env'),
        'app_debug'       => config('app.debug'),
        'app_url'         => config('app.url'),
        'timezone'        => config('app.timezone'),
        'locale'          => config('app.locale'),
        'fallback_locale' => config('app.fallback_locale'),
        'key_length'      => strlen(config('app.key')),

        // Cache / Queue
        'cache_driver'    => config('cache.default'),
        'queue_connection'=> config('queue.default'),
        'session_driver'  => config('session.driver'),

        // Database
        'db_connection'   => config('database.default'),
        'db_host'         => config("database.connections.".config('database.default').".host"),
        'db_port'         => config("database.connections.".config('database.default').".port"),
        'db_database'     => config("database.connections.".config('database.default').".database"),

        // Server Info
        'server_ip'       => request()->server('SERVER_ADDR'),
        'server_name'     => request()->server('SERVER_NAME'),
        'server_software' => request()->server('SERVER_SOFTWARE'),
        'server_protocol' => request()->server('SERVER_PROTOCOL'),
        'server_os'       => PHP_OS,
        'memory_limit'    => ini_get('memory_limit'),
        'max_execution'   => ini_get('max_execution_time'),
        'upload_max_filesize' => ini_get('upload_max_filesize'),
        'post_max_size'   => ini_get('post_max_size'),

        // Request Info
        'client_ip'       => request()->ip(),
        'user_agent'      => request()->userAgent(),
        'request_method'  => request()->method(),
        'request_uri'     => request()->getRequestUri(),
    ]);
});


Route::get('/test-orders', function () {
    $query = <<<'GRAPHQL'
        query GetOrders($limit: Int!, $start: ShortDate!, $end: ShortDate!) {
            getOrders(
                pagination: { limit: $limit }
                filter: {
                    orderDate: {
                        start: $start
                        end: $end
                    }
                }
            ) {
                message
                statusCode
                result {
                    count
                    orders {
                        uid
                        posSyncId
                        createdAt
                        isPreBookEnable
                    }
                }
            }
        }
    GRAPHQL;

    $response = Http::withHeaders([
        'Authorization' => 'Bearer ' . env('ORDERS_API_KEY'),
        'Content-Type'  => 'application/json',
    ])->post(env('ORDERS_GRAPHQL_URL'), [
        'query'     => $query,
        'variables' => [
            'limit' => 5,
            'start' => '2025-08-01', // must be YYYY-MM-DD
            'end'   => '2025-08-30',
        ],
    ]);

    if ($response->successful()) {
        return response()->json($response->json());
    }

    return response()->json([
        'error' => 'API call failed',
        'details' => $response->body(),
    ], 500);
});
