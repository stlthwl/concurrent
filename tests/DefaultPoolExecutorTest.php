<?php

namespace Tests;

use PHPUnit\Framework\TestCase;
use Concurrent\Executor\DefaultPoolExecutor;
use Concurrent\Queue\ArrayBlockingQueue;
use Concurrent\TimeUnit;

class DefaultPoolExecutorTest extends TestCase
{
    protected function setUp(): void
    {
    }

    public function testBlockingQueue(): void
    {
        $queue = new ArrayBlockingQueue(3);
        $queue->add(1);
        $queue->add(2);
        $queue->add(3);
        $this->assertEquals(3, $queue->size());
        $it = $queue->iterator();
        $this->assertEquals(1, $it->current());
        $this->assertEquals(1, $it->current());
        $this->assertTrue($it->valid());
        while ($it->valid()) {
            $it->next();
        }
        $this->assertEquals(3, $it->current());
        $this->assertFalse($it->valid());

        $queue->clear();
        $this->assertEquals(0, $queue->size());
        $queue->add(1);
        $this->assertEquals(1, $queue->size());
        $queue->remove(2);
        $this->assertEquals(1, $queue->size());
        $queue->remove(1);
        $this->assertEquals(0, $queue->size());
        $queue->add(2);
        $queue->add(3);
        $ar = $queue->toArray();
        $this->assertCount(2, $ar);
    }

    public function testTaskExecution(): void
    {
        $pool = new DefaultPoolExecutor(4, 6);
        $task1 = new TestTask("task 1");
        $task2 = new TestTask("task 2");
        $task3 = new TestTask("task 3");
        $task4 = new TestTask("task 4");
        $task5 = new TestTask("task 5");
        $task6 = new TestTask("task 6");
        $task7 = new TestTask("task 7");
        $task8 = new TestTask("task 8");
        $task9 = new TestTask("task 9");
        $pool->execute($task1);
        $pool->execute($task2);
        $pool->execute($task3);
        $pool->execute($task4);
        $pool->execute($task5);
        sleep(5);
        $pool->shutdown();
        $pool->execute($task6);
        $pool->execute($task7);
        $pool->execute($task8);
        $pool->execute($task9);

        $pool->shutdown();
        $this->assertTrue($pool->isShutdown());
    }

    public function testAbruptWorkerFailureStopsPoolWithoutReplacement(): void
    {
        $queueCount = $this->getSystemQueueCount();
        $pool = new DefaultPoolExecutor(1, 1);
        $pool->execute(new FailingTask());

        $deadline = microtime(true) + 2;
        while (!$pool->isFailed() && microtime(true) < $deadline) {
            usleep(10000);
        }

        $this->assertTrue($pool->isFailed());
        $this->assertSame(1, $pool->getFailedWorkerCount());
        $this->assertSame(0, $pool->getPoolSize());
        $this->assertSame($queueCount, $this->getSystemQueueCount());
        $this->assertThrowsRuntimeException(function () use ($pool): void {
            $pool->execute(new TestTask('must be rejected'));
        });
    }

    public function testPhpErrorAlsoStopsPoolWithoutReplacement(): void
    {
        $queueCount = $this->getSystemQueueCount();
        $pool = new DefaultPoolExecutor(1, 1);
        $pool->execute(new FailingTask(true));

        $deadline = microtime(true) + 2;
        while (!$pool->isFailed() && microtime(true) < $deadline) {
            usleep(10000);
        }

        $this->assertTrue($pool->isFailed());
        $this->assertSame(1, $pool->getFailedWorkerCount());
        $this->assertSame(0, $pool->getPoolSize());
        $this->assertSame($queueCount, $this->getSystemQueueCount());
    }

    private function assertThrowsRuntimeException(callable $callback): void
    {
        try {
            $callback();
            $this->fail('Expected RuntimeException was not thrown');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Cannot execute tasks: worker pool has failed', $exception->getMessage());
        }
    }

    private function getSystemQueueCount(): int
    {
        if (!is_readable('/proc/sysvipc/msg')) {
            $this->markTestSkipped('SysV IPC queue information is not available');
        }

        return count(file('/proc/sysvipc/msg'));
    }
}
