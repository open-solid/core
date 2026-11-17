<?php

declare(strict_types=1);

namespace OpenSolid\Core\Infrastructure\Bus\Event\Serializer;

use InvalidArgumentException;
use OpenSolid\Core\Domain\Event\Message\DomainEvent;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionProperty;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Serializer\Normalizer\DenormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

final class DomainEventNormalizer implements NormalizerInterface, DenormalizerInterface, NormalizerAwareInterface, DenormalizerAwareInterface
{
    use DenormalizerAwareTrait;
    use NormalizerAwareTrait;

    private const string DATETIME_FORMAT = 'Y-m-d\TH:i:s.uP';

    /**
     * @var array<class-string, list<array{property: ReflectionProperty, name: string, type: ?string}>>
     */
    private array $propertyPlanCache = [];

    /**
     * @return array<string, mixed>
     */
    public function normalize(mixed $data, ?string $format = null, array $context = []): array
    {
        \assert($data instanceof DomainEvent);

        $context += [DateTimeNormalizer::FORMAT_KEY => self::DATETIME_FORMAT];

        $normalized = [];
        foreach ($this->propertyPlan($data::class) as $plan) {
            $property = $plan['property'];

            if (!$property->isInitialized($data)) {
                continue;
            }

            $value = $property->getValue($data);
            $normalized[$plan['name']] = \is_object($value) ? $this->normalizer->normalize($value, $format, $context) : $value;
        }

        return $normalized;
    }

    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): DomainEvent
    {
        if (!is_a($type, DomainEvent::class, true)) {
            throw new InvalidArgumentException(\sprintf('Cannot denormalize "%s": it is not a subclass of "%s".', $type, DomainEvent::class));
        }

        $event = new ReflectionClass($type)->newInstanceWithoutConstructor();

        if (!\is_array($data)) {
            return $event;
        }

        foreach ($this->propertyPlan($type) as $plan) {
            if (!\array_key_exists($plan['name'], $data)) {
                continue;
            }

            $value = $data[$plan['name']];

            if (null !== $value && null !== $plan['type']) {
                $value = $this->denormalizer->denormalize($value, $plan['type'], $format, $context);
            }

            $plan['property']->setValue($event, $value);
        }

        return $event;
    }

    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $data instanceof DomainEvent;
    }

    public function supportsDenormalization(mixed $data, string $type, ?string $format = null, array $context = []): bool
    {
        return DomainEvent::class === $type || is_subclass_of($type, DomainEvent::class);
    }

    public function getSupportedTypes(?string $format): array
    {
        return [DomainEvent::class => true];
    }

    /**
     * @param class-string $class
     *
     * @return list<array{property: ReflectionProperty, name: string, type: ?string}>
     */
    private function propertyPlan(string $class): array
    {
        return $this->propertyPlanCache[$class] ??= array_values(array_map(
            static function (ReflectionProperty $property): array {
                $type = $property->getType();

                return [
                    'property' => $property,
                    'name' => $property->getName(),
                    'type' => $type instanceof ReflectionNamedType && !$type->isBuiltin() ? $type->getName() : null,
                ];
            },
            array_filter(
                new ReflectionClass($class)->getProperties(ReflectionProperty::IS_PUBLIC),
                static fn (ReflectionProperty $property): bool => !$property->isStatic(),
            ),
        ));
    }
}
