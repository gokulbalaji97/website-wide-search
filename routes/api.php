<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\SearchController;

Route::get('/search', [SearchController::class, 'search'])->middleware('log.search');
Route::get('/search/logs', [SearchController::class, 'topSearches'])->middleware('admin');
Route::get('/search/suggestions', [SearchController::class, 'suggestions']);

Route::post('/search/rebuild-index', [SearchController::class, 'rebuildIndex'])->middleware('admin');
