<?php

use App\Http\Controllers\ImageController;

Route::get('/', function () {
    return redirect()->route('images.index');
});

Route::resource('images', ImageController::class);
// 👉 TAMBAHKAN INI
Route::get('/image/{path}', function ($path) {
    $file = Storage::disk('s3')->get($path);
    $mime = Storage::disk('s3')->mimeType($path);

    return response($file)
        ->header('Content-Type', $mime);
})->where('path', '.*');