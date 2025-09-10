{{-- Overview Documentation Template --}}
# {{ $serverName }}

{{ $description }}

**Version:** {{ $version }}
**Generated:** {{ $generated }}

## Component Statistics

- **Tools:** {{ $stats['tools'] }}
- **Resources:** {{ $stats['resources'] }}
- **Prompts:** {{ $stats['prompts'] }}
- **Total Components:** {{ $stats['total'] }}

## Features

@foreach ($features as $feature)
- {{ $feature }}
@endforeach

## Quick Start

```bash
# Start the MCP server
php artisan mcp:serve

# List available components
php artisan mcp:list

# Register with AI client
php artisan mcp:register {{ $defaultClient }}
```

## Supported Transports

@foreach ($transports as $transport => $info)
### {{ ucfirst($transport) }} Transport

{{ $info['description'] }}

**Configuration:**
```json
{!! json_encode($info['config'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) !!}
```
@endforeach

## Integration Examples

@if (count($integrations) > 0)
@foreach ($integrations as $client => $config)
### {{ $client }}

```json
{!! json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) !!}
```
@endforeach
@else
_No client integrations configured yet._
@endif