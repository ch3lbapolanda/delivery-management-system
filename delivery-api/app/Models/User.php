<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    public function customerOrders(): HasMany
    {
        return $this->hasMany(Order::class, 'customer_id');
    }

    public function assignedOrders(): HasMany
    {
        return $this->hasMany(Order::class, 'courier_id');
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

  public function isCourierAvailable(): bool
{
    if ($this->availability_status !== 'available') {
        return false;
    }

    return !$this->assignedOrders()
        ->whereIn('status', [
            'assigned',
            'picked_up',
            'in_transit',
        ])
        ->exists();
}

public function hasActiveOrder(): bool
{
    return $this->assignedOrders()
        ->whereIn('status', [
            'assigned',
            'picked_up',
            'in_transit',
        ])
        ->exists();
}

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'availability_status' => 'string',
        ];
    }

  public function courierLocations(): HasMany
{
    return $this->hasMany(CourierLocation::class, 'courier_id');
}
}
