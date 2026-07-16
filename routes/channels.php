<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Provider inbox — any authenticated clinician or admin may subscribe
Broadcast::channel('provider-inbox', function ($user) {
    return $user->hasAnyRole(['clinician', 'admin', 'super_admin']);
});
