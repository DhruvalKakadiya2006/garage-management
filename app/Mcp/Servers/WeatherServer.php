<?php

namespace App\Mcp\Servers;

use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

#[Name('Garage Weather Server')]
#[Version('1.0.0')]
#[Instructions('Provides garage-relevant weather context for scheduling outdoor jobs and deliveries. Currently no tools are registered — add tools to $tools to expose them via MCP.')]
class WeatherServer extends Server
{
    protected array $tools = [
        //
    ];

    protected array $resources = [
        //
    ];

    protected array $prompts = [
        //
    ];
}
