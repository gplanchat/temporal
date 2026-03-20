<?php

declare(strict_types=1);

namespace Kiboko\Temporal\Testing;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;
use Symfony\Component\Messenger\Event\WorkerRunningEvent;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Transport\Receiver\ReceiverInterface;
use Symfony\Component\Messenger\Worker;

/**
 * Exécute **une passe** du {@see Worker} Symfony : parcours des receivers (ordre du tableau) puis
 * traitement d’**au plus un** message (comportement identique au `while` interne : dès qu’un message
 * est pris sur un receiver, les suivants ne sont pas interrogés dans la même itération).
 *
 * Si aucun message n’est disponible après un cycle complet (polls « vides »), la passe se termine.
 *
 * **Non supporté officiellement par Symfony** : s’appuie sur {@see Worker::run()}, {@see Worker::stop()}
 * et les événements Messenger. À vérifier à chaque montée de version majeure.
 *
 * @experimental
 */
final class MessengerWorkerTicker
{
    /**
     * @param array<string, ReceiverInterface> $receivers
     */
    public function __construct(
        private readonly array $receivers,
        private readonly MessageBusInterface $bus,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    /**
     * @param array{sleep?: int, queues?: string[]|null} $runOptions mêmes clés que {@see Worker::run()}
     *
     * @return bool true si au moins un message a été **traité avec succès** ({@see WorkerMessageHandledEvent})
     */
    public function tick(array $runOptions = []): bool
    {
        $runOptions += ['sleep' => 0];

        $worker = new Worker($this->receivers, $this->bus, $this->eventDispatcher);

        $handledSuccessfully = false;

        // Handled/Failed n'exposent pas getWorker() (seulement envelope + receiverName) :
        // pendant tick() un seul Worker::run() est actif sur ce dispatcher → pas de filtre par instance.
        $onHandled = function (WorkerMessageHandledEvent $_e) use ($worker, &$handledSuccessfully): void {
            $handledSuccessfully = true;
            $worker->stop();
        };

        $onFailed = function (WorkerMessageFailedEvent $_e) use ($worker): void {
            $worker->stop();
        };

        $onIdle = function (WorkerRunningEvent $event) use ($worker, &$handledSuccessfully): void {
            if ($event->getWorker() !== $worker || !$event->isWorkerIdle() || $handledSuccessfully) {
                return;
            }
            $worker->stop();
        };

        $this->eventDispatcher->addListener(WorkerMessageHandledEvent::class, $onHandled);
        $this->eventDispatcher->addListener(WorkerMessageFailedEvent::class, $onFailed);
        $this->eventDispatcher->addListener(WorkerRunningEvent::class, $onIdle);

        try {
            $worker->run($runOptions);
        } finally {
            $this->eventDispatcher->removeListener(WorkerMessageHandledEvent::class, $onHandled);
            $this->eventDispatcher->removeListener(WorkerMessageFailedEvent::class, $onFailed);
            $this->eventDispatcher->removeListener(WorkerRunningEvent::class, $onIdle);
        }

        return $handledSuccessfully;
    }
}
