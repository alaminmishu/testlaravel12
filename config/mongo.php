<?php

return [

    /*
    |--------------------------------------------------------------------------
    | MongoDB Order Source
    |--------------------------------------------------------------------------
    |
    | Connection details for the MongoDB instance that holds the canonical
    | order documents. The database name is not fixed - it changes over time
    | as new snapshots/environments are provisioned - so it must always be
    | read from the environment, never hardcoded.
    |
    */

    'uri' => env('MONGO_URI', 'mongodb://127.0.0.1:27017'),

    'database' => env('MONGO_DATABASE'),

    'orders_collection' => env('MONGO_ORDERS_COLLECTION', 'orders'),

];
