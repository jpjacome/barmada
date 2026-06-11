<?php

use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

// The "numbers" feature is deprecated (its routes redirect to the dashboard).
// Deny broadcast authorization rather than authorizing every user.
Broadcast::channel('numbers', function () {
    return false;
});
