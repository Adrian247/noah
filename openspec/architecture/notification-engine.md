# Notification Engine — Phoenix

Dominio: [notifications.md](../domain/notifications.md).

## Arquitectura

```mermaid
flowchart LR
  E[Domain Event] --> L[Listener]
  L --> Q[Queue]
  Q --> N[NotificationService]
  N --> C{Channel}
  C --> Email[Email]
  C --> Push[Push - futuro]
```

## Canales

| Canal | Fase |
|-------|------|
| Email | v1 |
| Push móvil | v2 |
| WhatsApp / SMS | futuro |
| Slack / Teams | integraciones |

## Interfaz

```php
// Conceptual
interface NotificationChannel {
    public function send(NotificationMessage $message): void;
}
```

## Plantillas

- Variables sustituidas en cola, no en request HTTP.
- Versionado de plantillas opcional (v1: una plantilla por tipo de evento).

## Reintentos

- 3 reintentos exponenciales; dead-letter para revisión admin.
