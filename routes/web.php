<?php

use App\Http\Controllers\OrderController;
use Illuminate\Support\Facades\Route;

// 網頁路由
Route::get('/', function () {
    return view('welcome');
});

