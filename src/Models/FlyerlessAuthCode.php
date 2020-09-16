<?php

namespace Flyerless\Service\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class FlyerlessAuthCode extends Model
{
    protected $table = 'flyerless_auth_codes';

    protected $hidden = [
      'api_key', 'expires_at', 'access_token'
    ];

    protected $fillable = [
        'api_key',
    ];

    protected $casts = [
        'expires_at' => 'datetime'
    ];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        static::creating(function($authCode) {
            if($authCode->access_token === null) {
                $authCode->access_token = '';
            }
            if($authCode->expires_at === null) {
                $authCode->expires_at = Carbon::now()->addMinutes(20);
            }
        });
    }

    public function isTokenValid()
    {
        return $this->expires_at->isFuture();
    }

}
