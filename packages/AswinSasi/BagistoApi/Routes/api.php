<?php

use Illuminate\Support\Facades\Route;
use AswinSasi\BagistoApi\Http\Controllers\ShopController;

Route::middleware('web')->prefix('custom-api')->group(function () {
    Route::get('categories', [ShopController::class, 'getCategories']);
    Route::get('products', [ShopController::class, 'getProducts']);
    Route::post('cart', [ShopController::class, 'addToCart']);
    Route::post('checkout', [ShopController::class, 'checkout']);
});

Route::get('test-api', function () {
    return response()->json([
        'success' => true,
        'message' => 'Test route working!',
    ]);
});
