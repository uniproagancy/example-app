<?php

use App\Http\Controllers\FeedController;
use App\Http\Controllers\PaymentController;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use Mcamara\LaravelLocalization\Facades\LaravelLocalization;

Route::get('/feed/facebook.xml', [FeedController::class, 'facebook'])->name('feed.facebook');
Route::get('/feed/google.xml', [FeedController::class, 'google'])->name('feed.google');

Route::controller(PaymentController::class)->prefix('payment')->name('payment.')->group(function () {
    Route::match(['get', 'post', 'put'], 'callback/{driver}', 'callback')
        ->middleware('throttle:120,1')
        ->name('callback');
    Route::get('return/{number}', 'return')->name('return');
    Route::get('link/{number}', 'link')->middleware('signed')->name('link');
    Route::post('bog/installment/{number}', 'bogInstallment')->middleware('throttle:20,1')->name('bog.installment');
    Route::get('credo/{number}', 'credo')->name('credo.form');
    Route::get('credo-split/{number}', 'credoSplit')->name('credo_split.form');
});

Route::prefix(LaravelLocalization::setLocale())
    ->middleware(['localize', 'localeSessionRedirect', 'localizationRedirect', 'localeViewPath'])
    ->group(function () {
        Livewire::setUpdateRoute(fn ($handle, $path) => Route::post($path, $handle)->name('livewire.update'));

        Route::livewire('/', 'pages::home')->name('home');

        Route::livewire('about', 'pages::about')->name('about');

        Route::livewire('contact', 'pages::contact')->name('contact');

        Route::livewire('info/{slug}', 'pages::info')->name('page');

        Route::livewire('checkout', 'pages::checkout')->name('checkout');

        Route::livewire('order/{number}', 'pages::order')->name('order');

        Route::livewire('catalog', 'pages::catalog')->name('catalog');

        Route::livewire('categories/{slug}', 'pages::catalog')->name('category');

        Route::livewire('product/{slug}', 'pages::product')->name('product');

        Route::livewire('reset-password/{token}', 'pages::reset-password')
            ->middleware('guest')
            ->name('password.reset');

        Route::livewire('account', 'pages::account')
            ->middleware('auth')
            ->name('account');

        Route::fallback(fn () => abort(404));
    });
