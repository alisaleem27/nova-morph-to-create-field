<?php

declare(strict_types=1);

namespace AliSaleem\MorphToCreateField;

use Illuminate\Support\ServiceProvider;
use Laravel\Nova\Events\ServingNova;
use Laravel\Nova\Nova;

class FieldServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Nova::serving(function (ServingNova $event): void {
            Nova::script('morph-to-create-field', __DIR__.'/../dist/js/field.js');
        });
    }
}
