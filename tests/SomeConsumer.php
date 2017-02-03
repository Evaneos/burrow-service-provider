<?php

namespace Tests\Evaneos\Burrow;

use Burrow\QueueConsumer;

class SomeConsumer implements QueueConsumer
{
    public function consume($message, array $headers = [])
    {
        echo $message;
    }
}