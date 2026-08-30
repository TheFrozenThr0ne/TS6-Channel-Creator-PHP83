<?php
declare(strict_types=1);

final class AbuseGuard
{
    public function __construct(
        private readonly string $file,
        private readonly int $cooldown
    ) {
    }

    public function blocked(string $ip): bool
    {
        if ($this->cooldown <= 0) {
            return false;
        }

        $data = $this->read();
        return isset($data[$ip]) && (time() - (int)$data[$ip]) < $this->cooldown;
    }

    public function touch(string $ip): void
    {
        if ($this->cooldown <= 0) {
            return;
        }

        $data = $this->read();
        $data[$ip] = time();

        // Keep the file small.
        $cutoff = time() - ($this->cooldown * 2);
        foreach ($data as $key => $timestamp) {
            if ((int)$timestamp < $cutoff) {
                unset($data[$key]);
            }
        }

        @file_put_contents(
            $this->file,
            json_encode($data, JSON_THROW_ON_ERROR),
            LOCK_EX
        );
    }

    private function read(): array
    {
        if (!is_file($this->file)) {
            return [];
        }

        $raw = @file_get_contents($this->file);
        if ($raw === false || $raw === '') {
            return [];
        }

        try {
            $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
            return is_array($data) ? $data : [];
        } catch (Throwable) {
            return [];
        }
    }
}
