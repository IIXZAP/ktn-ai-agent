<?php
namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PythonAgentClient
{
    protected string $baseUrl;
    protected string $secret;

    public function __construct()
    {
        $this->baseUrl = rtrim((string) config('services.python.agent.url'), '/');
        $this->secret = (string) config('services.python_agent.secret');
        
    }

    protected function client()
    {
        return Http::withToken($this->secret)
            ->acceptJson()
            ->timeout(30)
            ->connectTimeout(5);
    }
}

?>