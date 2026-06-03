<?php

use Mostafax\DualLayer\Infrastructure\Transformers\DefaultTransformer;

it('handles() returns the model class', function () {
    $t = new DefaultTransformer('App\Models\User');
    expect($t->handles())->toBe('App\Models\User');
});

it('collection() pluralises the snake-cased class basename', function () {
    expect((new DefaultTransformer('App\Models\User'))->collection())->toBe('users');
    expect((new DefaultTransformer('App\Models\OrderItem'))->collection())->toBe('order_items');
    expect((new DefaultTransformer('App\Models\Category'))->collection())->toBe('categories');
});

it('transform() returns standard envelope', function () {
    $t   = new DefaultTransformer('App\Models\User');
    $doc = $t->transform(['id' => 5, 'name' => 'Alice', 'email' => 'alice@example.com']);

    expect($doc)->toHaveKeys(['source_type', 'source_id', 'data', 'synced_at'])
        ->and($doc['source_type'])->toBe('user')
        ->and($doc['source_id'])->toBe(5)
        ->and($doc['data']['name'])->toBe('Alice');
});

it('documentKey() returns source_id', function () {
    expect((new DefaultTransformer('App\Models\User'))->documentKey())->toBe('source_id');
});

it('sourceKey() returns id', function () {
    expect((new DefaultTransformer('App\Models\User'))->sourceKey())->toBe('id');
});
