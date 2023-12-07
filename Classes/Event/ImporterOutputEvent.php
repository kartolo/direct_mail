<?php

declare(strict_types=1);

namespace DirectMailTeam\DirectMail\Event;

final class ImporterOutputEvent
{
    private array $output = [];

    public function __construct(array $output)
    {
        $this->output = $output;
    }

    public function getOutput(): array
    {
        return $this->output;
    }

    public function setOutput(array $output): void
    {
        $this->output = $output;
    }
}