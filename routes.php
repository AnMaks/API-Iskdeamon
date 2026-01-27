<?php
use App\Kernal\Router\Route;
use App\Controllers\ApiController;
use App\Controllers\WebController;

return [
    Route::get('/', [WebController::class, 'test']),

    // API
    Route::get('/api/health', [ApiController::class, 'health']),
    Route::post('/api/init', [ApiController::class, 'init']),
    Route::post('/api/reset', [ApiController::class, 'reset']),

    Route::post('/api/images', [ApiController::class, 'addImage']),
    Route::get('/api/images/random', [ApiController::class, 'random']),

    Route::post('/api/search', [ApiController::class, 'searchUpload']),
    Route::get('/api/images/{id}/matches', [ApiController::class, 'matchesById']),
    Route::get('/api/images/{id}/file', [ApiController::class, 'fileById']),
    Route::delete('/api/images/{id}', [ApiController::class, 'deleteById']),
];
