<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    $path = public_path('spa.html');

    abort_unless(file_exists($path), 404, 'spa.html not found');

    return response(file_get_contents($path), 200, [
        'Content-Type' => 'text/html; charset=UTF-8',
    ]);
});