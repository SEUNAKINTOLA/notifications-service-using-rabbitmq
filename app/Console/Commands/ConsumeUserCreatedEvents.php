<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use PhpAmqpLib\Connection\AMQPStreamConnection;

class ConsumeUserCreatedEvents extends Command
{
    protected $signature = 'consume:user-created-events';
    protected $description = 'Consume messages from RabbitMQ';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        try {
            $this->consumeMessages();
        } catch (\Exception $e) {
            $this->error('An error occurred: ' . $e->getMessage());
        }
    }

    private function consumeMessages()
    {
        $connection = new AMQPStreamConnection(
            env('RABBITMQ_HOST', 'rabbitmq'),
            env('RABBITMQ_PORT', 5672),
            env('RABBITMQ_USER', 'guest'),
            env('RABBITMQ_PASSWORD', 'guest'),
            env('RABBITMQ_VHOST', '/')
        );

        $channel = $connection->channel();
        $channel->queue_declare(env('QUEUE_NAME', 'my_queue'), false, true, false, false);

        $callback = function ($msg) {
            $this->processMessage($msg);
        };

        $channel->basic_consume('my_queue', '', false, true, false, false, $callback);

        $this->info("Waiting for messages. To exit press CTRL+C\n");

        while ($channel->is_consuming()) {
            $channel->wait(null, false, 3); // Wait for 3 seconds for new messages
        }

        $channel->close();
        $connection->close();
    }

    private function processMessage($msg)
    {
        $data = json_decode($msg->body, true);
        $logMessage = sprintf("User Created: %s, %s %s\n", $data['email'], $data['firstName'], $data['lastName']);
        file_put_contents(storage_path('logs/user_created_events.log'), $logMessage, FILE_APPEND);
        $this->info("Logged user creation: " . $logMessage);
    }
}
