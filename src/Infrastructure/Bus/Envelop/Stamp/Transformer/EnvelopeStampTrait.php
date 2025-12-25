<?php

declare(strict_types=1);

namespace OpenSolid\Core\Infrastructure\Bus\Envelop\Stamp\Transformer;

use OpenSolid\Bus\Envelope\Stamp\Stamp;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\StampInterface;

trait EnvelopeStampTrait
{
    private StampTransformer $stampTransformer;

    private function createEnvelope(object $message): Envelope
    {
        $reflection = new \ReflectionClass($message);

        if (!$attributes = $reflection->getAttributes(Stamp::class, \ReflectionAttribute::IS_INSTANCEOF)) {
            return Envelope::wrap($message);
        }

        /** @var list<StampInterface> $stamps */
        $stamps = [];
        foreach ($attributes as $attribute) {
            /** @var Stamp $stamp */
            $stamp = $attribute->newInstance();

            $stamps[] = $this->stampTransformer->transform($stamp);
        }

        return Envelope::wrap($message, $stamps);
    }
}
