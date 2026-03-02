<?php

declare(strict_types=1);

namespace App\EventSource;

use App\DTO\Event;
use App\Contract\EventSourceException;
use App\Contract\EventSourceInterface;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;

final readonly class CsvEventSource implements EventSourceInterface
{
    public function __construct(
        private string     $identifier,
        private string     $filePath,
        private Filesystem $filesystem,
    )
    {
    }

    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    /**
     * @throws EventSourceException
     */
    public function fetchAfter(int $lastEventId): array
    {
        try {
            if (!$this->filesystem->exists($this->filePath)) {
                throw new EventSourceException("File not found: {$this->filePath}");
            }

            $content = file_get_contents($this->filePath);
            if ($content === false) {
                throw new EventSourceException("Cannot read file: {$this->filePath}");
            }

            return $this->parseCsv($content, $lastEventId);
        } catch (IOException $e) {
            throw new EventSourceException($e->getMessage(), $e);
        }
    }

    /**
     * @return Event[]
     */
    private function parseCsv(string $content, int $lastEventId): array
    {
        $lines = explode("\n", trim($content));
        if ($lines === []) {
            return [];
        }

        $headers = str_getcsv(array_shift($lines));
        $events = [];

        foreach ($lines as $line) {
            if (trim($line) === '') {
                continue;
            }

            $data = array_combine($headers, str_getcsv($line));
            $id = (int) $data['id'];

            if ($id <= $lastEventId) {
                continue;
            }

            $payload = json_decode($data['payload'] ?? '{}', true) ?: [];
            $events[] = new Event($id, $data['type'], $payload);
        }

        return $events;
    }
}
