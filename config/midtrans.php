<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Midtrans Payment Gateway
    |--------------------------------------------------------------------------
    |
    | Dipakai untuk checkout upgrade paket SaaS (bukan transaksi POS toko).
    | Ambil server_key & client_key dari dashboard.midtrans.com (Settings >
    | Access Keys). Gunakan kredensial SANDBOX selama development.
    |
    */

    'server_key' => env('MIDTRANS_SERVER_KEY'),

    'client_key' => env('MIDTRANS_CLIENT_KEY'),

    'is_production' => env('MIDTRANS_IS_PRODUCTION', false),

];
