Rutina #{{ $routineId }} — {{ $typeName }}
Activo: {{ $assetTag }}

{{ $bodyText ?? trim(html_entity_decode(strip_tags($body ?? $mailMessage ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8')) }}

Ver detalle: {{ $url }}
