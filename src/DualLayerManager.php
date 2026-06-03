<?php

namespace Mostafax\DualLayer;

use Illuminate\Contracts\Foundation\Application;
use Mostafax\DualLayer\Application\SyncEngine;
use Mostafax\DualLayer\Contracts\SyncHooksInterface;
use Mostafax\DualLayer\Contracts\TransformerInterface;
use Mostafax\DualLayer\Infrastructure\Observers\ModelSyncObserver;

/**
 * Public API surface. Facades proxy to this class.
 */
final class DualLayerManager
{
    /** @var array<string> observed model FQCNs */
    private array $observed = [];

    public function __construct(
        private readonly Application $app,
        private readonly SyncEngine  $engine,
    ) {}

    /**
     * Register a model for dual-layer sync.
     *
     * Usage: DualReport::observe(User::class);
     */
    public function observe(string $modelClass): self
    {
        if (in_array($modelClass, $this->observed, true)) {
            return $this;
        }

        $modelClass::observe(new ModelSyncObserver());
        $this->observed[] = $modelClass;

        return $this;
    }

    /**
     * Register a custom transformer for a model.
     *
     * Usage: DualReport::transformer(new UserTransformer());
     */
    public function transformer(TransformerInterface $transformer): self
    {
        $this->engine->registerTransformer($transformer);
        return $this;
    }

    /**
     * Register lifecycle hooks for a model.
     *
     * Usage: DualReport::hooks(new UserSyncHooks());
     */
    public function hooks(SyncHooksInterface $hooks): self
    {
        $this->engine->registerHooks($hooks);
        return $this;
    }

    /**
     * Fluent: observe + transformer in one call.
     */
    public function register(string $modelClass, ?TransformerInterface $transformer = null): self
    {
        $this->observe($modelClass);
        if ($transformer) {
            $this->transformer($transformer);
        }
        return $this;
    }

    public function observedModels(): array
    {
        return $this->observed;
    }
}
