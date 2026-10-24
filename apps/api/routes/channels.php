<?php

use App\Broadcasting\UserChannel;
use Illuminate\Support\Facades\Broadcast;

// Laravel broadcasts database notifications on "App.Models.User.{id}" (private).
// Next.js authenticates with a Sanctum bearer token; Filament users have a session ("web").
Broadcast::channel('App.Models.User.{id}', UserChannel::class, ['guards' => ['sanctum', 'web']]);
