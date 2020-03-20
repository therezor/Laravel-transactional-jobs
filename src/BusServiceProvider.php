<?php

namespace TheRezor\TransactionalJobs;

use Illuminate\Contracts\Bus\Dispatcher as DispatcherContract;
use Illuminate\Contracts\Bus\QueueingDispatcher as QueueingDispatcherContract;
use Illuminate\Contracts\Queue\Factory as QueueFactoryContract;
use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\ServiceProvider;

class BusServiceProvider extends ServiceProvider implements DeferrableProvider
{
    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton(TransactionalDispatcher::class, function ($app) {
            return new TransactionalDispatcher($app, function ($connection = null) use ($app) {
                return $app[QueueFactoryContract::class]->connection($connection);
            });
        });

        $this->app->alias(
            TransactionalDispatcher::class, DispatcherContract::class
        );

        $this->app->alias(
            TransactionalDispatcher::class, QueueingDispatcherContract::class
        );
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return [
            TransactionalDispatcher::class,
            DispatcherContract::class,
            QueueingDispatcherContract::class,
        ];
    }
}
