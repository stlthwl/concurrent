<?php

namespace Tests;

use Concurrent\RunnableInterface;
use Concurrent\ThreadInterface;

class FailingTask implements RunnableInterface
{
    private $throwError;

    public function __construct(bool $throwError = false)
    {
        $this->throwError = $throwError;
    }

    public function __serialize(): array
    {
        return ['throwError' => $this->throwError];
    }

    public function __unserialize(array $data): void
    {
        $this->throwError = $data['throwError'];
    }

    public function run(ThreadInterface $process = null, ...$args): void
    {
        if ($this->throwError) {
            throw new \Error('worker failure');
        }

        throw new \RuntimeException('worker failure');
    }
}
