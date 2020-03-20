<?php

namespace TheRezor\TransactionalJobs;

use Closure;
use Illuminate\Bus\Dispatcher;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Events\Dispatcher as DispatcherContract;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Events\TransactionCommitted;
use Illuminate\Database\Events\TransactionRolledBack;
use Illuminate\Queue\NullQueue;
use Illuminate\Queue\SyncQueue;
use TheRezor\TransactionalJobs\Contracts\TransactionalJob;

class TransactionalDispatcher extends Dispatcher
{
    /**
     * @var array
     */
    protected $pendingCommands = [];

    /**
     * @var DispatcherContract
     */
    protected $eventDispatcher;

    /**
     * @var DatabaseManager
     */
    protected $db;

    public function __construct(Container $container, Closure $queueResolver = null)
    {
        parent::__construct($container, $queueResolver);

        $this->eventDispatcher = $container->make(DispatcherContract::class);
        $this->db = $container->make('db');
        $this->setUpTransactionListeners();
    }

    public function dispatchToQueue($command)
    {
        // Dispatch immediately if no transactions was opened during job
        if (!($command instanceof TransactionalJob) || 0 === $this->db->transactionLevel()) {
            return parent::dispatchToQueue($command);
        }

        $connection = $command->connection ?? null;

        $queue = call_user_func($this->queueResolver, $connection);

        if ($queue instanceof NullQueue || $queue instanceof SyncQueue) {
            return parent::dispatchToQueue($command);
        }

        // Add command to pending list
        $this->pendingCommands[] = $command;

        return null;
    }

    public function commitTransaction()
    {
        if (empty($this->pendingCommands) || $this->db->transactionLevel() > 0) {
            return;
        }

        $this->dispatchPendingCommands();
    }

    public function rollbackTransaction()
    {
        $this->pendingCommands = [];
    }

    protected function dispatchPendingCommands()
    {
        foreach ($this->pendingCommands as $command) {
            parent::dispatchToQueue($command);
        }

        $this->pendingCommands = [];
    }

    protected function setUpTransactionListeners()
    {
        $this->eventDispatcher->listen(TransactionCommitted::class, function () {
            $this->commitTransaction();
        });
        $this->eventDispatcher->listen(TransactionRolledBack::class, function () {
            $this->rollbackTransaction();
        });
    }
}
