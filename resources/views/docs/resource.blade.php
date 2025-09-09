{{-- Resource Documentation Template --}}
## {{ $name }}

{{ $description }}

@if (!empty($metadata['uri']))
**URI:** `{{ $metadata['uri'] }}`
@endif

@if (!empty($metadata['mime_type']))
**MIME Type:** `{{ $metadata['mime_type'] }}`
@endif

@if (!empty($metadata['class']))
**Class:** `{{ $metadata['class'] }}`
@endif

@if (!empty($metadata['parameters']))
### Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
@foreach ($metadata['parameters'] as $param => $info)
| **{{ $param }}** | `{{ $info['type'] ?? 'mixed' }}` | {{ $info['required'] ?? false ? 'Yes' : 'No' }} | {{ $info['description'] ?? '' }} |
@endforeach
@endif

@if (!empty($metadata['schema']))
### Resource Schema

```json
{!! json_encode($metadata['schema'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) !!}
```

@if (isset($schemaDocumentation))
{!! $schemaDocumentation !!}
@endif
@endif

@if (!empty($metadata['annotations']))
### Annotations

@foreach ($metadata['annotations'] as $annotation)
- {{ $annotation }}
@endforeach
@endif

@if (!empty($metadata['templates']))
### Resource Templates

@foreach ($metadata['templates'] as $template)
#### {{ $template['name'] }}

{{ $template['description'] ?? '' }}

**URI Pattern:** `{{ $template['uri_pattern'] }}`

@if (!empty($template['parameters']))
**Parameters:**
@foreach ($template['parameters'] as $param => $desc)
- **{{ $param }}**: {{ $desc }}
@endforeach
@endif
@endforeach
@endif

@if (!empty($metadata['examples']))
### Examples

@foreach ($metadata['examples'] as $example)
**{{ $example['title'] ?? 'Example' }}**

Request:
```json
{!! json_encode($example['request'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) !!}
```

Response:
```json
{!! json_encode($example['response'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) !!}
```
@endforeach
@endif

@if (!empty($metadata['notes']))
### Notes

{{ $metadata['notes'] }}
@endif

---