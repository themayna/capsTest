<?php

declare(strict_types=1);

namespace App\src\EventSource;

use App\DTO\Event;
use App\src\Contract\EventSourceInterface;

final readonly class CsvEventSource implements EventSourceInterface
{
    public function __construct(
        private string $identifier,
        private string $filePath,
    )
    {
    }

    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    public function fetchAfter(int $lastEventId): array
    {
        if (!file_exists($this->filePath)) {
            return [];
        }

        $handle = fopen($this->filePath, 'r');
        if ($handle === false) {
            return [];
        }

        $events = [];
        $headers = fgetcsv($handle);

        while (($row = fgetcsv($handle)) !== false) {
            $data = array_combine($headers, $row);
            $id = (int) $data['id'];

            if ($id <= $lastEventId) {
                continue;
            }

            $payload = json_decode($data['payload'] ?? '{}', true) ?: [];
            $events[] = new Event($id, $data['type'], $payload);
        }

        fclose($handle);

        return $events;
    }
}
