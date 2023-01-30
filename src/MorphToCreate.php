<?php

declare(strict_types=1);

namespace AliSaleem\MorphToCreateField;

use Laravel\Nova\Fields\MorphTo;

class MorphToCreate extends MorphTo
{
    public $component = 'morph-to-create-field';

    public function __construct($name, $attribute = null)
    {
        parent::__construct($name, $attribute);

        $this->showCreateRelationButton();
    }
}
