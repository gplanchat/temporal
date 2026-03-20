# kiboko/temporal

Package **core** : transports et contrats **sans Symfony** (namespace `Kiboko\Temporal\`).

## Contenu

- `Kiboko\Temporal\Transport\TransportInterface` — contrat commun HTTP / gRPC
- `Kiboko\Temporal\Transport\Psr18HttpTransport` — appels HTTP vers l’API Temporal (port 7243) via **PSR-18** + **PSR-17**
- `Kiboko\Temporal\Serialization\TemporalPayloadCodecInterface` — codec payloads style Temporal

Les implémentations gRPC, Symfony Messenger et le bundle vivent dans le monorepo (`temporal/`, `kiboko/temporal-bundle`).

## Dépendances PSR-17

Fournir une implémentation concrète (`nyholm/psr7`, `guzzlehttp/psr7`, etc.) pour `RequestFactoryInterface` et `StreamFactoryInterface`.

## Long polling

PSR-18 ne standardise pas le timeout par requête. Pour `PollActivityTaskQueue`, configurer le client sous-jacent (ex. timeout élevé sur le client HTTP).
