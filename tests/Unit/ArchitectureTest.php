<?php

declare(strict_types=1);

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;

arch('all API controllers must be final')
    ->expect('App\Http\Controllers\Api')
    ->toBeFinal();

arch('all form requests must extend Laravel FormRequest')
    ->expect('App\Http\Requests')
    ->toExtend(FormRequest::class);

arch('all API controllers must end with Controller')
    ->expect('App\Http\Controllers\Api')
    ->toHaveSuffix('Controller');

arch('all form requests must end with Request')
    ->expect('App\Http\Requests')
    ->toHaveSuffix('Request');

arch('all API resources must extend JsonResource')
    ->expect('App\Http\Resources')
    ->toExtend(JsonResource::class);

arch('API controllers should not depend on the DB facade')
    ->expect('App\Http\Controllers\Api')
    ->not->toUse(DB::class);

arch('application code must not contain debugging functions')
    ->expect('App')
    ->not->toUse([
        'dd',
        'dump',
        'var_dump',
        'print_r',
        'die',
        'exit',
    ]);
