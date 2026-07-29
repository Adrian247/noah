# Pruebas manuales — Phoenix

Guía para validar el producto en **http://localhost:8888** (Docker: `docker compose up -d`).

## Preparación

1. Contenedores: `app`, `web`, `postgres`, `redis`, `queue`, `mailpit` (MinIO opcional para evidencias futuras en S3).
2. Al arrancar `app` se ejecutan `migrate`, `storage:link` y `phoenix:ensure-demo` si no hay usuarios.
3. Si el login falla o tras actualizar el seed demo:
   ```bash
   docker compose exec app php artisan phoenix:refresh-demo
   ```
   Ver credenciales en [DEMO_ENV.md](DEMO_ENV.md).
4. Mailpit: **http://localhost:8025**

### Credenciales demo

| Email | Rol | Contraseña |
|-------|-----|------------|
| admin@pyro-systems.com | Administrador | phoenix_application |
| claudio.rodriguez@mein-company.com | Supervisor | phoenix_application |
| misael.palos@mein-company.com | Técnico | phoenix_application |
| elena.sanchez@mein-company.com | Facturación | phoenix_application |

---

## A. Autenticación y empresa

| # | Pasos | Resultado esperado |
|---|--------|-------------------|
| A1 | Login con admin | Entra al dashboard |
| A2 | Cambiar empresa en el selector (si hay más de una) | Recarga contexto |
| A3 | Salir y volver a entrar | Sesión limpia |
| A4 | Clic en chevron del menú lateral | Menú se colapsa; **solo iconos** visibles con tooltip; preferencia persiste al recargar |
| A5 | Clic en nombre/avatar arriba → **Elegir imagen** | Foto visible en cabecera y en `GET /auth/me` (`avatar_url`) |
| A6 | Revisar páginas internas (dashboard, rutinas) | Texto oscuro sobre fondo claro; sin letras grises sobre fondos oscuros en el área principal |
| A7 | Pantalla de login | Fondo animado (red de partículas) y tarjeta con entrada suave, como en AI Assistant |

---

## B. Dashboard y rutinas (Fase 1–2)

| # | Usuario | Pasos | Resultado esperado |
|---|---------|--------|-------------------|
| B1 | admin | Dashboard | Tarjetas: pendientes validación, asignadas, validadas, borradores factura |
| B2 | admin | Rutinas → filtro `pending_validation` | Solo rutinas en ese estado |
| B3 | admin/supervisor | Rutinas → **Nueva rutina** (sitio, activo, tipo, técnico) | Rutina `assigned` |
| B4 | tecnico | Abrir rutina asignada → llenar formulario → **Enviar ejecución** | Estado `pending_validation` |
| B5 | — | Mailpit | Correo a supervisor/admin |
| B6 | supervisor | Rutina pendiente → **Validar** | `validated`, mensaje PDF/factura |
| B7 | supervisor | Esperar o recargar → **Descargar PDF** | PDF válido |
| B8 | tecnico | Intentar Validar | Sin botones; mensaje si API 403 |
| B9 | supervisor | **Rechazar** con motivo | Vuelve a `assigned` |

---

## C. Catálogos y sitios

| # | Usuario | Pasos | Resultado esperado |
|---|---------|--------|-------------------|
| C1 | admin | Sitios → crear/editar | CRUD OK |
| C2 | admin | Proveedores → alta | Listado actualizado |
| C3 | admin | Equipos / Insumos / Activos | CRUD existente |
| C4 | admin | Usuarios | Matriz **Lectura / Escritura** por módulo; si ambos off, el usuario no ve el ítem en menú (recargar sesión del usuario afectado) |
| C5 | admin | **Catálogos → Clientes** | CRUD; técnico sin acceso de edición |

---

## D. Diseño (metadatos)

Orden recomendado: **D0** (caso demo ya enlazado) o **D1 → D2 → D3 → D4** si creas plantillas nuevas.

### D0 — Caso demo: revisión mayor SUV premium

Tras `PhoenixDemoSeeder`:

| Pieza | Nombre |
|--------|--------|
| Formulario | *Revisión mayor vehículo — agencia premium* |
| Reporte | *Informe revisión mayor vehículo* (campos alineados al formulario) |
| Tipo de rutina | *Revisión mayor vehículo (premium)* |
| Rutina | Activo `L200-2018-DEMO` → técnico `misael.palos@mein-company.com` |

Secciones del formulario: **kilometraje**, **frenos**, **filtros**, **aceite**, **batería**, **luces**, **fusibles**, fotos recomendadas y bloque **Revisiones Plus** (opcional). Flujo: **B4** → **B6–B7** (PDF).

| # | Dónde | Pasos | Resultado esperado |
|---|--------|--------|-------------------|
| D1 | Diseño → **Formularios** | Editar → Guardar borrador → **Publicar versión** | Lista: “vN publicada” + borrador vN+1 |
| D2 | Diseño → **Reportes** → pestaña **Plantillas** | Abrir *Informe revisión mayor vehículo* → revisar componentes alineados al formulario → **Publicar** si editaste | Igual que D1: versión publicada + borrador nuevo |
| D3 | Diseño → **Workflows** | Crear workflow, duplicar uno existente, abrir diseñador, mover nodos, toggle PDF/factura al validar, guardar | Listado muestra uso en tipos de rutina; cambios persisten |
| D4 | Diseño → **Tipos de rutina** | En la fila *Revisión mayor vehículo (premium)*, elegir en los desplegables la versión **publicada** de formulario y reporte; workflow si aplica | Al cambiar, mensaje “Formulario/Reporte enlazado…”; columnas muestran la v elegida |

**Cómo validar D2 en concreto**

1. Entra a **Reportes** → pestaña **Plantillas** → *Informe revisión mayor vehículo*.
2. Añade o cambia un componente (título, párrafo con campo `corrected_comments`, etc.) → **Publicar**.
3. No hace falta “enlazar” en esta pantalla: el enlace es en **Tipos de rutina (D4)**.
4. Comprueba el PDF tras validar una rutina (flujo B6–B7): debe reflejar la plantilla publicada enlazada al tipo.

Si el desplegable de reporte en D4 está vacío, aún no hay ninguna versión **publicada** (solo borrador).

---

## E. Facturación y auditoría

| # | Usuario | Pasos | Resultado esperado |
|---|---------|--------|-------------------|
| E1 | facturacion | Validar rutina (B6) → **Facturación** → abrir borrador | Prefactura con líneas sugeridas (insumos / MO si tarifa > 0) |
| E2 | facturacion | En detalle borrador: editar precios, agregar MO, elegir **cliente**, **Guardar prefactura** | Totales recalculados; ver `docs/BILLING.md` |
| E3 | facturacion | **Emitir factura** (con cliente asignado) | Folio y estado emitido; sin cliente → error |
| E4 | facturacion | **Configuración** tarifa sugerida MO / IVA | Solo afecta nuevos borradores sugeridos |
| E5 | admin | Auditoría | Eventos login, rutina, clientes, prefactura, etc. |

---

## F. API móvil (sin app Flutter)

Simula la app de campo con `curl` o Postman.

**Login** (guarda `token` y `company_id`):

```bash
curl -s -X POST http://localhost:8888/api/v1/auth/login \
  -H 'Content-Type: application/json' \
  -d '{"email":"misael.palos@mein-company.com","password":"phoenix_application","device_name":"curl"}'
```

**Pull** (rutinas asignadas):

```bash
curl -s -X POST http://localhost:8888/api/v1/sync \
  -H "Authorization: Bearer TOKEN" \
  -H "X-Company-Id: COMPANY_ID" \
  -H 'Content-Type: application/json' \
  -d '{"device_id":"dev-1","events":[],"pull":true}'
```

**Push** ejecución (misma `event_id` dos veces = idempotente):

```bash
curl -s -X POST http://localhost:8888/api/v1/sync \
  -H "Authorization: Bearer TOKEN" \
  -H "X-Company-Id: COMPANY_ID" \
  -H 'Content-Type: application/json' \
  -d '{
    "device_id":"dev-1",
    "pull":false,
    "events":[{
      "event_id":"evt-manual-1",
      "event_type":"execution.submitted",
      "payload":{
        "routine_id": ROUTINE_ID,
        "technician_comments":"Prueba sync",
        "duration_minutes":30,
        "responses":{"horometro":150},
        "consumptions":[]
      }
    }]
  }'
```

---

## G. Evidencias fotográficas

| # | Pasos | Resultado esperado |
|---|--------|-------------------|
| G1 | Tras enviar ejecución, subir foto (API `POST /api/v1/routines/{id}/evidences` multipart `file`) | 201 + registro |
| G2 | `GET /api/v1/evidences/{id}/download` | Imagen descargada |

*(UI web de subida: usar API o ampliar en siguiente iteración; almacenamiento local `storage/app/private/evidence`.)*

---

## H. Checklist de regresión rápida

- [ ] `docker compose exec app php artisan test` — 38 tests verdes
- [ ] Cola `queue` activa — PDF async
- [ ] Credenciales demo tras BD vacía — auto-seed al levantar `app`

---

## Fuera de alcance en esta entrega

- App **Flutter** (Fase 3 UI): contrato sync listo; ver `mobile/README.md`
- Facturación fiscal / PAC
- SSO, invitaciones, rule engine, IA visión (Fase 4)
