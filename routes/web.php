<?php

use App\Jobs\ProcessRabbitMQMessage;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/test-queue', function () {
    ProcessRabbitMQMessage::dispatch();
    return 'Job dispatched!';
});
