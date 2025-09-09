{{-- Prompt Documentation Template --}}
## {{ $name }}

{{ $description }}

@if (!empty($metadata['class']))
**Class:** `{{ $metadata['class'] }}`
@endif

@if (!empty($metadata['arguments']))
### Arguments

| Argument | Type | Required | Description |
|----------|------|----------|-------------|
@foreach ($metadata['arguments'] as $arg)
| **{{ $arg['name'] ?? 'unknown' }}** | `{{ $arg['type'] ?? 'string' }}` | {{ $arg['required'] ?? false ? 'Yes' : 'No' }} | {{ $arg['description'] ?? '' }} |
@endforeach
@endif

@if (!empty($metadata['schema']))
### Argument Schema

```json
{!! json_encode($metadata['schema'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) !!}
```

@if (isset($schemaDocumentation))
{!! $schemaDocumentation !!}
@endif
@endif

@if (!empty($metadata['template']))
### Template

```
{{ $metadata['template'] }}
```
@endif

@if (!empty($metadata['variables']))
### Template Variables

@foreach ($metadata['variables'] as $variable => $info)
- **{{ $variable }}**: {{ $info['description'] ?? '' }} (`{{ $info['type'] ?? 'string' }}`)
@endforeach
@endif

@if (!empty($metadata['examples']))
### Examples

@foreach ($metadata['examples'] as $example)
**{{ $example['title'] ?? 'Example' }}**

Arguments:
```json
{!! json_encode($example['arguments'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) !!}
```

Generated Prompt:
```
{{ $example['output'] }}
```
@endforeach
@endif

@if (!empty($metadata['use_cases']))
### Use Cases

@foreach ($metadata['use_cases'] as $useCase)
- {{ $useCase }}
@endforeach
@endif

@if (!empty($metadata['notes']))
### Notes

{{ $metadata['notes'] }}
@endif

---