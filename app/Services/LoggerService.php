<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class LoggerService
{
    private $channel;
    
    public function __construct($channel = 'default')
    {
        $this->channel = $channel;
    }
    
    public function info($message, array $context = [])
    {
        Log::channel('single')->info("[{$this->channel}] $message", $context);
    }
    
    public function error($message, array $context = [])
    {
        Log::channel('single')->error("[{$this->channel}] $message", $context);
    }
    
    public function warning($message, array $context = [])
    {
        Log::channel('single')->warning("[{$this->channel}] $message", $context);
    }
    
    public function debug($message, array $context = [])
    {
        Log::channel('single')->debug("[{$this->channel}] $message", $context);
    }
}