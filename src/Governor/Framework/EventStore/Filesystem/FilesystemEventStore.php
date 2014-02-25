<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace Governor\Framework\EventStore\Filesystem;

use Governor\Framework\Repository\ConflictingModificationException;
use Governor\Framework\Domain\DomainEventStreamInterface;
use Governor\Framework\Domain\DomainEventMessageInterface;
use Governor\Framework\EventStore\EventStoreInterface;
use Governor\Framework\EventStore\SnapshotEventStoreInterface;
use Governor\Framework\Serializer\SerializerInterface;
use Governor\Framework\EventStore\EventStreamNotFoundException;

/**
 * Description of FilesystemEventStore
 *
 */
class FilesystemEventStore implements EventStoreInterface, SnapshotEventStoreInterface
{

    /**
     * @var SerializerInterface
     */
    private $serializer;

    /**
     *
     * @var EventFileResolverInterface 
     */
    private $fileResolver;

    /**
     * 
     * @param SerializerInterface $serializer
     */
    function __construct(EventFileResolverInterface $fileResolver,
        SerializerInterface $serializer)
    {
        $this->serializer = $serializer;
        $this->fileResolver = $fileResolver;
    }

    public function appendEvents($type, DomainEventStreamInterface $events)
    {
        if (!$events->hasNext()) {
            return;
        }

        $next = $events->peek();
        if (0 === $next->getScn() && $this->fileResolver->eventFileExists($type,
                $next->getAggregateIdentifier())) {
            throw new ConflictingModificationException(sprintf("Could not create event stream for aggregate, such stream "
                . "already exists, type=%s, id=%s", $type,
                $next->getAggregateIdentifier()));
        }

        $file = $this->fileResolver->openEventFileForWriting($type,
            $next->getAggregateIdentifier());
        $eventMessageWriter = new FilesystemEventMessageWriter($file,
            $this->serializer);

        while ($events->hasNext()) {
            $eventMessageWriter->writeEventMessage($events->next());
        }
    }

    public function appendSnapshotEvent($type,
        DomainEventMessageInterface $snapshotEvent)
    {
        $eventFile = $this->fileResolver->openEventFileForReading($type,
            $snapshotEvent->getAggregateIdentifier());
        $snapshotEventFile = $this->fileResolver->openSnapshotFileForWriting($type,
            $snapshotEvent->getAggregateIdentifier());
    }

    public function readEvents($type, $identifier)
    {        
        if (!$this->fileResolver->eventFileExists($type, $identifier)) {
            throw new EventStreamNotFoundException($type, $identifier);
        }

        $file = $this->fileResolver->openEventFileForReading($type, $identifier);
        $snapshotEvent = null;
        
        try {
            $snapshotEvent = $this->readSnapshotEvent($type, $identifier, $file);
        } catch (\Exception $ex) {
            // ignore 
        }

        if (null !== $snapshotEvent) {
            // $snapshotEventMessageWriter = new FilesystemEventMessageWriter($file, $serializer)
        }

        return new FilesystemDomainEventStream($file, $this->serializer);
    }

    private function readSnapshotEvent($type, $identifier, $eventFile)
    {
        $snapshotEvent = null;
        if ($this->eventFileResolver->snapshotFileExists($type, $identifier)) {
            $snapshotEventFile = $this->eventFileResolver->openSnapshotFileForReading($type,
                $identifier);
            $fileSystemSnapshotEventReader = new FileSystemSnapshotEventReader($eventFile,
                $snapshotEventFile, $this->eventSerializer);
            $snapshotEvent = $fileSystemSnapshotEventReader->readSnapshotEvent($type,
                $identifier);
        }
        return $snapshotEvent;
    }

}