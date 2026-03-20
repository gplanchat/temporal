<?php

declare(strict_types=1);

namespace Kiboko\Temporal\Grpc;

use Google\Protobuf\Internal\GPBType;
use Google\Protobuf\Internal\MapField;
use Kiboko\Temporal\Serialization\TemporalPayloadCodecInterface;
use Temporal\Api\Common\V1\Payload;
use Temporal\Api\Common\V1\Payloads;

/**
 * Conversion Payloads protobuf Temporal ↔ valeurs PHP (JSON / json/plain).
 */
final class TemporalPayloadMapper
{
    private const JSON_PLAIN = 'json/plain';

    public function __construct(
        private readonly TemporalPayloadCodecInterface $codec,
    ) {
    }

    /**
     * @param list<mixed> $positionalArgs Arguments workflow / activité (sérialisés chacun en un Payload JSON).
     */
    public function payloadsFromArgs(array $positionalArgs): Payloads
    {
        $list = [];
        foreach ($positionalArgs as $arg) {
            $list[] = $this->singleJsonPayload($arg);
        }

        $payloads = new Payloads();
        $payloads->setPayloads($list);

        return $payloads;
    }

    /**
     * @param array<string, mixed> $namedInput ex. ['input' => 'hello'] — un seul Payload JSON objet
     */
    public function payloadsFromAssociativeInput(array $namedInput): Payloads
    {
        return $this->payloadsFromArgs([$namedInput]);
    }

    public function payloadsFromScalar(mixed $value): Payloads
    {
        return $this->payloadsFromArgs([$value]);
    }

    /**
     * Premier payload décodé en tableau associatif (activité / résultat simple).
     *
     * @return array<string, mixed>
     */
    public function payloadsToInputArray(?Payloads $payloads): array
    {
        if ($payloads === null) {
            return [];
        }
        $first = $payloads->getPayloads()->offsetGet(0);
        if (!$first instanceof Payload) {
            return [];
        }

        return $this->decodePayloadToArray($first);
    }

    /**
     * @return array<string, mixed>
     */
    public function decodePayloadToArray(Payload $payload): array
    {
        $data = $payload->getData();
        if ($data === '') {
            return [];
        }

        $decoded = json_decode($data, true, 512, \JSON_THROW_ON_ERROR);
        if (!\is_array($decoded)) {
            return ['value' => $decoded];
        }

        return $decoded;
    }

    private function singleJsonPayload(mixed $value): Payload
    {
        $json = $this->codec->encodeJson($value);
        $meta = new MapField(GPBType::STRING, GPBType::BYTES);
        $meta->offsetSet('encoding', self::JSON_PLAIN);

        $p = new Payload();
        $p->setMetadata($meta);
        $p->setData($json);

        return $p;
    }
}
