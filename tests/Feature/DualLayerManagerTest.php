<?php

use Illuminate\Support\Facades\Queue;
use Mostafax\DualLayer\Support\Facades\DualReport;

beforeEach(function () {
    Queue::fake();
});

it('observe() registers the same model only once', function () {
    $manager = app(\Mostafax\DualLayer\DualLayerManager::class);

    $manager->observe(\Illuminate\Foundation\Auth\User::class);
    $manager->observe(\Illuminate\Foundation\Auth\User::class); // duplicate

    expect($manager->observedModels())->toHaveCount(1);
});

it('register() with transformer observes and registers transformer', function () {
    $manager = app(\Mostafax\DualLayer\DualLayerManager::class);

    $transformer = new class implements \Mostafax\DualLayer\Contracts\TransformerInterface {
        public function handles(): string    { return \Illuminate\Foundation\Auth\User::class; }
        public function collection(): string { return 'users'; }
        public function documentKey(): string { return 'source_id'; }
        public function sourceKey(): string  { return 'id'; }
        public function transform(array $attributes): array { return $attributes; }
    };

    $manager->register(\Illuminate\Foundation\Auth\User::class, $transformer);

    expect($manager->observedModels())->toContain(\Illuminate\Foundation\Auth\User::class);
});

it('facade DualReport proxies to DualLayerManager', function () {
    DualReport::observe(\Illuminate\Foundation\Auth\User::class);

    expect(DualReport::observedModels())->toContain(\Illuminate\Foundation\Auth\User::class);
});
