<?php

namespace Mostafax\DualLayer\Support\Facades;

use Illuminate\Support\Facades\Facade;
use Mostafax\DualLayer\Contracts\SyncHooksInterface;
use Mostafax\DualLayer\Contracts\TransformerInterface;
use Mostafax\DualLayer\DualLayerManager;

/**
 * @method static DualLayerManager observe(string $modelClass)
 * @method static DualLayerManager transformer(TransformerInterface $transformer)
 * @method static DualLayerManager hooks(SyncHooksInterface $hooks)
 * @method static DualLayerManager register(string $modelClass, ?TransformerInterface $transformer = null)
 * @method static array observedModels()
 *
 * @see DualLayerManager
 */
class DualReport extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'dual-layer.manager';
    }
}
