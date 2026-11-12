<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Firebase Cloud Messaging (HTTP v1)
    |--------------------------------------------------------------------------
    |
    | The service account JSON path and project id are deliberately read from
    | the environment. The previous implementation hardcoded a project id, so
    | every send was rejected by Google with a 403 because the key belonged to
    | a different Firebase project than the one the Android app was built for.
    |
    */

    'credentials' => env('FIREBASE_CREDENTIALS', base_path('base.json')),

    'project_id' => env('FIREBASE_PROJECT_ID'),

    /*
    | Send notifications inline (queue driver is "sync" by default) unless a
    | real queue is configured. Set to true once a worker is running.
    */
    'queue' => env('FIREBASE_QUEUE', false),

];
