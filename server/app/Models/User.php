<?php

namespace App\Models;

use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;
    protected $table = 'user';
    public $timestamps = false;
    // Укажите, какие поля можно заполнять
    protected $fillable = [
        'name',
        'password',
        'role',
    ];

    // Скрытые поля
    protected $hidden = [
        'password',
        'remember_token',
    ];

    // Приведение типов
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'role' => 'string',
        ];
    }
}