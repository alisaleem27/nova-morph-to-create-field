# Nova Morph-To Create Field

This [Laravel Nova](https://nova.laravel.com/) package a MorphTo field that does not allow selecting previously created
resources.

## Installation

Install the package in to a Laravel app that uses [Nova](https://nova.laravel.com) via composer:

```bash
composer require alisaleem27/nova-morph-to-create-field
```

## Usage

```php
use AliSaleem\MorphToCreateField\MorphToCreate;

class MyResource extends Resource
{
    //...
    
    public function fields(NovaRequest $request)
    {
        return [
            MorphToCreate::make('...')
                ->types(['...']),
        ];
    }
}
```

## License

Nova Dependable Panel is open-sourced software licensed under the [MIT license](LICENSE.md).

