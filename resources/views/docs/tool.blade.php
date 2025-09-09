{{-- Tool Documentation Template --}}
## {{ $name }}

{{ $description }}

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

@if (!empty($metadata['input_schema']))
### Input Schema

```json
{!! json_encode($metadata['input_schema'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) !!}
```

@if (isset($schemaDocumentation))
{!! $schemaDocumentation !!}
@endif
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

@if (!empty($metadata['errors']))
### Possible Errors

@foreach ($metadata['errors'] as $error)
- **{{ $error['code'] }}**: {{ $error['message'] }}
@endforeach
@endif

@if (!empty($metadata['notes']))
### Notes

{{ $metadata['notes'] }}
@endif

---