# Sincronización — Phoenix (móvil)

Arquitectura servidor: [mobile-sync.md](../architecture/mobile-sync.md). Offline: [offline.md](offline.md).

## Protocolo (borrador)

**Request**

```json
{
  "device_id": "uuid",
  "app_version": "1.0.0",
  "last_sync_at": "2026-07-24T12:00:00Z",
  "events": [
    {
      "id": "client-uuid",
      "type": "RoutineFinished",
      "occurred_at": "...",
      "payload": { }
    }
  ]
}
```

**Response**

```json
{
  "server_time": "...",
  "acknowledged": ["client-uuid"],
  "server_changes": [
    {
      "type": "FormVersionPublished",
      "payload": { }
    }
  ],
  "conflicts": []
}
```

## Fotos

1. Evento referencia `local_media_id`.
2. Tras ACK, `POST /files/upload` multipart o URL firmada.
3. Servidor devuelve `stored_file_id`; cliente actualiza ejecución.

## Idempotencia

- `client event id` único; servidor ignora duplicados.

## Diagrama

Ver [sync-flow.md](../diagrams/sync-flow.md).
