<?php

namespace App\Console\Commands\Foundation;

use App\Console\Commands\Command;
use Illuminate\Queue\QueueManager;

class ListenOfficeEventsBroadcastingCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'queue:listen-office-events';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Listen to rabbitmg queue of office events for services';

    /**
     * The schedule instance.
     *
     * @var \Illuminate\Support\Collection;
     */
    protected $manager;

    /**
     * Create a new command instance.
     *
     * @param QueueManager $manager
     */
    public function __construct(QueueManager $manager)
    {
        $this->manager = $manager;

        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->info('[*] Waiting for events messages. To exit press CTRL+C');

        while (true) {
            $msg = app('queue')->connection('office_events_reading')->pop();
            if (null !== $msg) {
                $this->line(" [x] {$msg->getRawBody()}");
                $msg->delete();
            }
        }
    }
}
