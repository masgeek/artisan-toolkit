<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Command Overrides
    |--------------------------------------------------------------------------
    |
    | Each entry maps a built-in Artisan command name to a replacement class.
    |
    | Set a command to false to leave the built-in untouched.
    | Swap in any class that extends the original to provide your own logic.
    |
    | Example:
    |   'schema:dump' => false,                              // disabled
    |   'schema:dump' => App\Console\SchemaDump::class,     // custom class
    |
    */

    'overrides' => [

        'schema:dump' => \Masgeek\ArtisanOverrides\Commands\SchemaDumpCommand::class,

    ],

];
