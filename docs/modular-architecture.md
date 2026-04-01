# Modular Architecture

OpenSolid Core provides conventions and base classes for organizing Symfony applications into self-contained modules, each with its own domain, application, and infrastructure layers.

## Module Directory Structure

Each module follows a consistent directory layout:

```
src/
└── Product/                                   # Module root
    ├── Application/
    │   └── Product/                           # Aggregate
    │       ├── Create/                        # Use case (verb)
    │       │   ├── CreateProduct.php          # Command
    │       │   └── CreateProductHandler.php   # Handler
    │       └── Find/
    │           ├── FindProduct.php            # Query
    │           └── FindProductHandler.php     # Handler
    ├── Domain/
    │   ├── Error/                             # Domain-specific errors
    │   ├── Event/                             # Domain events
    │   └── Model/                             # Aggregate, entities and value objects
    └── Infrastructure/         
        ├── Resources/         
        │   └── config/         
        │       ├── doctrine/         
        │       │   └── mapping/               # Doctrine ORM mappings
        │       ├── packages/                  # Package-specific config overrides
        │       └── services.yaml              # Service definitions
        └── ProductExtension.php               # Module extension
```

## ModuleExtension

Each module registers itself as a Symfony bundle extension by extending `ModuleExtension`:

```php
namespace App\Product\Infrastructure;

use OpenSolid\Core\Infrastructure\Symfony\Module\ModuleExtension;

class ProductExtension extends ModuleExtension
{
}
```

That's all you need. `ModuleExtension` automatically:

- **Detects the module path** from the extension class location (two directories up)
- **Derives the module namespace** by stripping the `\Infrastructure\Symfony` suffix
- **Registers service definitions** from `Infrastructure/Resources/config/services.yaml`
- **Configures Doctrine ORM mappings** for entities in `Domain/Model/` (if the `doctrine` extension is available)
- **Imports package configuration** from `Infrastructure/Resources/config/packages/*.yaml`

### Extension Alias

The extension alias is automatically prefixed with `app_`. For example, `ProductExtension` gets the alias `app_product`.

### Customizing Extension Behavior

Override `loadExtension()` or `prependExtension()` to add custom configuration:

```php
class ProductExtension extends ModuleExtension
{
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        parent::loadExtension($config, $container, $builder);

        // Additional service configuration ...
    }
}
```

The `$this->path` and `$this->namespace` properties are available for referencing module paths and namespaces.

## Kernel

OpenSolid Core provides a `Kernel` base class that extends Symfony's kernel with support for module extension configuration merging:

```php
namespace App;

use OpenSolid\Core\Infrastructure\Symfony\HttpKernel\Kernel as BaseKernel;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;

class Kernel extends BaseKernel
{
    use MicroKernelTrait;
}
```

This kernel ensures that module extensions can properly prepend and merge their configuration with other bundles (like Doctrine and API Platform) through a custom `MergeExtensionConfigurationPass`.

## Auto-Configured Mappings

### Doctrine ORM

When a module has a `Domain/Model/` directory and the Doctrine bundle is installed, the extension automatically registers ORM mappings:

- **Mapping type:** Configurable (default: `xml`). See [Configuration](configuration.md).
- **Mapping directory:** `Infrastructure/Resources/config/doctrine/mapping/` (configurable)
- **Entity prefix:** `{ModuleNamespace}\Domain\Model`

The mapping directory is created automatically if it doesn't exist.

## Full Example

```
src/
├── Catalog/
│   ├── Application/
│   │   └── Product/
│   │       ├── Create/
│   │       │   ├── CreateProduct.php
│   │       │   └── CreateProductHandler.php
│   │       └── Get/
│   │           ├── GetProduct.php
│   │           └── GetProductHandler.php
│   ├── Domain/
│   │   ├── Event/
│   │   │   └── ProductCreated.php
│   │   └── Model/
│   │       └── Product.php
│   └── Infrastructure/
│       ├── Resources/
│       │   └── config/
│       │       ├── doctrine/
│       │       │   └── mapping/
│       │       │       └── Product.orm.xml
│       │       └── services.yaml
│       └── CatalogExtension.php
└── Kernel.php
```

```php
// src/Catalog/Infrastructure/CatalogExtension.php
namespace App\Catalog\Infrastructure;

use OpenSolid\Core\Infrastructure\Symfony\Module\ModuleExtension;

class CatalogExtension extends ModuleExtension
{
}
```
