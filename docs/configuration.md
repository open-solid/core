# Configuration

The bundle is configured under the `opensolid` key in your Symfony configuration.

## Full Configuration Reference

```yaml
# config/packages/opensolid.yaml
opensolid:
    bus:
        # The bus strategy to use for dispatching messages.
        # - "symfony": Uses Symfony Messenger (requires symfony/messenger)
        # - "native": Uses the open-solid/bus native implementation
        # - "custom": No auto-configuration — provide your own bus implementations
        strategy: symfony  # "symfony" (default when symfony/messenger is installed), "native", or "custom"

    doctrine:
        orm:
            mapping:
                # The mapping format for Doctrine entities.
                type: xml  # default: "xml"

                # Relative path (from the module root) to the Doctrine mapping files.
                relative_path: /Infrastructure/Resources/config/doctrine/mapping/  # default
```

## Bus Strategy

### `symfony` (default)

Uses Symfony Messenger as the underlying bus implementation. This is the default when `symfony/messenger` is installed.

Configures three separate message buses:
- `command.bus` — for commands
- `query.bus` — for queries
- `event.bus` — for domain events

Handlers are tagged with `messenger.message_handler` and routed to the appropriate bus automatically.

### `native`

Uses the `open-solid/bus` native implementation. This is the default when `symfony/messenger` is **not** installed.

Handlers are registered directly through the native bus middleware pipeline.

### `custom`

Disables all bus auto-configuration. You are responsible for providing your own implementations of:
- `OpenSolid\Core\Application\Command\Bus\CommandBus`
- `OpenSolid\Core\Application\Query\Bus\QueryBus`
- `OpenSolid\Core\Domain\Event\Bus\EventBus`

## Doctrine ORM Mapping

The `doctrine.orm.mapping` section controls how module entities are mapped.

### `type`

The mapping format. Default: `xml`. Common values: `xml`, `attribute`, `yaml`.

### `relative_path`

The directory (relative to the module root) where mapping files are located. Default: `/Infrastructure/Resources/config/doctrine/mapping/`.

This path is used by `ModuleExtension` to register Doctrine mappings automatically. The directory is created if it doesn't exist.

## Example: Minimal Configuration

If the defaults work for you, no configuration is needed at all. The bundle auto-detects Symfony Messenger and configures everything accordingly.

For a project using native buses with attribute-based Doctrine mappings:

```yaml
# config/packages/opensolid.yaml
opensolid:
    bus:
        strategy: native
    doctrine:
        orm:
            mapping:
                type: attribute
```
