<?php

use App\Broadcasting\UserChannel;
use Illuminate\Support\Facades\Broadcast;

// Laravel broadcasts database notifications on "App.Models.User.{id}" (private).
// The sanctum guard authenticates the bearer token sent by the Next.js client.
Broadcast::channel('App.Models.User.{id}', UserChannel::class, ['guards' => ['sanctum']]);
