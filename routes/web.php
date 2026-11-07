<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\StripeWebhookController;
use App\Livewire\Admin\Customers;
use App\Livewire\Admin\EditOperator;
use App\Livewire\Admin\Operators;
use App\Livewire\Admin\Services;
use App\Livewire\Admin\ShowCustomer;
use App\Livewire\Bookings\CreateBooking;
use App\Livewire\Bookings\MyBookings;
use App\Livewire\Bookings\RescheduleBooking;
use App\Livewire\Staff\Agenda;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

Route::post('stripe/webhook', StripeWebhookController::class)->name('stripe.webhook');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    Route::livewire('bookings', MyBookings::class)->name('bookings.index');
    Route::livewire('bookings/create', CreateBooking::class)->name('bookings.create');
    Route::livewire('bookings/{booking}/reschedule', RescheduleBooking::class)->name('bookings.reschedule');

    Route::livewire('agenda', Agenda::class)->name('agenda');

    Route::prefix('admin')->name('admin.')->group(function () {
        Route::livewire('services', Services::class)->name('services');
        Route::livewire('operators', Operators::class)->name('operators');
        Route::livewire('operators/{operator}', EditOperator::class)->name('operators.edit');
        Route::livewire('customers', Customers::class)->name('customers');
        Route::livewire('customers/{customer}', ShowCustomer::class)->name('customers.show');
    });
});

require __DIR__.'/settings.php';
