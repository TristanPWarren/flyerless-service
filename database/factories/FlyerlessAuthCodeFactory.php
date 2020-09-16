<?php

$factory->define(\Flyerless\Service\Models\FlyerlessAuthCode::class, function(\Faker\Generator $faker) {
    return [
        'api_key' => \Illuminate\Support\Str::random(15),
        'access_token' => \Illuminate\Support\Str::random(15),
        'expires_at' => $faker->dateTimeBetween('+1 minute', '+1 day')
    ];
});
