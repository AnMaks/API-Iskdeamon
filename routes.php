<?php

use App\Kernal\Router\Route;
use App\Controllers\ApiController;
use App\Controllers\WebController;


return [
    Route::get('/', [WebController::class, 'home']),
    Route::get('/test', [WebController::class, 'test']),

    Route::get('/api/health', [ApiController::class, 'health']),
    Route::post('/api/init', [ApiController::class, 'init']),
    Route::post('/api/reset', [ApiController::class, 'reset']),

    Route::post('/api/images', [ApiController::class, 'addImage']),
    Route::post('/api/search', [ApiController::class, 'searchUpload']),

    Route::get('/api/images/{id}/matches', [ApiController::class, 'matchesById']),
    Route::delete('/api/images/{id}', [ApiController::class, 'deleteById']),
];
