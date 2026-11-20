<?php

use Illuminate\Support\Facades\Route;

Route::get('/', fn () => response()->json(['application' => 'Afra', 'status' => 'development', 'documentation' => 'See repository README.']));
