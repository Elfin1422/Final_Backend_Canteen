<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
| Catch-all route that returns the React SPA for any URL not handled by
| the API. This lets React Router manage /admin/*, /cashier/*, etc.
| on page refresh or direct URL access.
|--------------------------------------------------------------------------
*/

Route::get('/{any}', function () {
    return file_get_contents(public_path('index.html'));
})->where('any', '.*');
