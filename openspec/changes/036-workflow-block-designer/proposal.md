# 036 — Diseñador de workflow por bloques (etapas, roles, acciones)

## Problema

El diseñador actual mezcla **Vue Flow libre**, **«Configuración del flujo»** (checkboxes que regeneran plantilla) y **«Transiciones (vista rápida)»** (tabla técnica). No refleja el lenguaje del negocio (etapas, quién aprueba, notificaciones) y obliga a entender `from`/`to`/`trigger`.

La prefactura exige **cliente** manual aunque el **activo** de la rutina ya puede tener cliente asignado.

## Objetivo

Conservar **Vue Flow** como canvas, pero con un modelo de **bloques tipados** y reglas de enlace que compilen al `definition` existente del motor (sin reescribir el runtime de golpe).

Sustituir en UI:

- Sección **Configuración del flujo** (`configure` + checkboxes).
- Sección **Transiciones (vista rápida)**.

Alinear facturación: **cliente de factura** derivado del activo cuando exista asignación activa.

## Principios

1. **Rutina** es siempre la primera etapa (`field_execution`); nodo fijo, no arrastrable desde paleta, no eliminable.
2. El admin de diseño **compone** etapas posteriores, roles y acciones; el motor sigue consumiendo `steps` + `transitions`.
3. **Aprobar / Rechazar** son acciones con reglas de topología estrictas (ver abajo).
4. **Email** es acción configurable (asunto, cuerpo enriquecido, tokens de contexto).
5. Publicación y validación actuales se mantienen (`draft` / `publish`, `WorkflowDefinitionValidator`).

---

## Modelo conceptual (bloques)

### Paleta — tres familias

| Familia | Bloques (ejemplos) | En canvas |
|---------|-------------------|-----------|
| **Etapas** | Revisión, Facturación | Nodos de fase; Rutina es ancla fija |
| **Roles** | Supervisor, Administrador, Facturación, Jefe de taller… | Nodos `human_task` con `assigned_role` |
| **Acciones** | Aprobar, Rechazar, Notificación por correo | Aristas o nodos `action` que generan `transitions` / `service_task` |

**Rutina (etapa)**  
- Representa ejecución en campo (`initial_step` = `field_execution`).  
- Siempre presente; **no** aparece en paleta; **no** seleccionable como bloque nuevo.  
- Visual: swimlane o nodo ancla izquierda del canvas.

**Etapas opcionales**  
- **Revisión**: agrupa uno o más nodos de rol (supervisor, doble revisión).  
- **Facturación**: paso `billing_review` si se incluye en el flujo.  
- Orden sugerido en UI: Rutina → Revisión → Facturación → Fin (`complete`).

**Roles**  
- Mapeo a `MembershipRole` / `assigned_role` del motor (`supervisor`, `billing`, `technician` solo en rutina implícita).  
- El técnico no se arrastra: la rutina ya implica ejecución por técnico.

**Acciones**

| Acción | Regla de enlace | Efecto en motor |
|--------|-----------------|-----------------|
| **Aprobar** | Solo **desde nodo Rol → hacia etapa Rutina** (validación sobre la ejecución) o **Rol → siguiente etapa** según compilador acordado¹ | `trigger: approved` (+ `actions: routine_validated` si aplica) |
| **Rechazar** | Solo **desde nodo Rol → hacia etapa Rutina** | `trigger: rejected`, destino `field_execution` |
| **Notificación correo** | Se asocia a una **transición** o **entrada de etapa** (p. ej. al enviar ejecución o al entrar en revisión) | `service_task` / `send_email` + config JSON |

¹ **Aclaración de producto (decisión en implementación):**  
La redacción «de un rol a una rutina» se interpreta como: **las aristas Aprobar/Rechazar deben tener origen en un bloque Rol y destino en el nodo/etapa Rutina** (devolución o cierre de ciclo de validación), o origen Rol y destino la **siguiente etapa humana** solo para Aprobar. El validador UI rechazará cualquier otro patrón. Documentar en glosario al implementar.

### Configuración de email (acción)

Al asociar **Notificación por correo**, panel lateral:

| Campo | Tipo |
|-------|------|
| Asunto | texto |
| Cuerpo | **texto enriquecido** (mismo stack que reportes / TipTap) |
| Destinatarios | roles / lista (heredar de hoy: supervisores + administradores; extensible) |
| **Campos de contexto** (insertables en plantilla) | Rutina, Tipo de rutina, Activo, Cliente |

Almacenamiento en `definition.steps[stepId].email` + `template_tokens` / `body_html`.

Runtime: `WorkflowStepEmailNotifier` + sustitución de placeholders al disparar la transición.

---

## UX (Vue Flow)

### Layout por bloques / swimlanes

```
[ Rutina (fija) ]  →  [ Revisión: Supervisor ]  →  [ Facturación ]  →  [ Fin ]
        ↑                      |
        └──── Rechazar ────────┘
```

- **Paleta izquierda:** Etapas (sin Rutina), Roles, Acciones.  
- **Canvas central:** Vue Flow con `Background`, snap a lanes, nodos custom (`stage`, `role`, `end`).  
- **Inspector derecho:** propiedades del seleccionado (rol, email, etc.).  
- **Conexión:** arrastrar desde handle de Rol; al soltar en Rutina o etapa, elegir acción (Aprobar/Rechazar) si aplica; para email, wizard de config.

### Eliminar de `WorkflowDesignerPage`

- Panel «Configuración del flujo» + botón «Aplicar configuración».  
- Lista «Transiciones (vista rápida)».  
- Opcional deprecar `PUT …/configure` o dejarlo como API interna que invoque el **compilador** desde el mismo payload que guarda el canvas.

### Conservar

- Guardar diseño, publicar, estado borrador/publicado.  
- Tipos de rutina solo workflows publicados.

---

## Compilación: bloques → `definition`

Nuevo módulo front + back:

- `workflowBlockModel.ts` / `WorkflowBlockCompiler.php`  
- Entrada: grafo Vue Flow + `meta.block_editor_version`.  
- Salida: `initial_step`, `steps`, `transitions`, `layout`, `meta` (sin `options` sueltos si todo viene del grafo).

Reglas de validación (ampliar `WorkflowDefinitionValidator`):

- Rutina alcanzable; al menos un camino a `complete`.  
- Cada `human_task` de revisión con `approved` y, si se permite rechazo, `rejected` → `field_execution`.  
- Aprobar/Rechazar solo en aristas permitidas (matriz origen/destino/tipo acción).  
- Email: salida `service_complete` hacia siguiente paso.  
- Facturación: si existe etapa Facturación, `invoice_issued` hacia `complete` o siguiente.

---

## Facturación — cliente desde activo

### Problema

Hoy emitir/editar prefactura puede exigir `client_id` explícito aunque la rutina tenga **activo** con **asignación cliente** vigente.

### Dirección

1. Al crear/actualizar borrador (`InvoiceDraftService`), si `client_id` es null:  
   - Resolver `routine → asset → client_assignments` (activa, vigente).  
   - Si hay un cliente, asignar a `invoice.client_id` (auditoría `invoice.client_resolved_from_asset`).  
2. UI prefactura: mostrar cliente **derivado** (solo lectura) con opción «cambiar» solo si permiso `billing.draft.edit` y política lo permite.  
3. Emitir factura: si sigue sin cliente y sin derivación, 422 con mensaje claro.

**No sustituye** catálogo de clientes; **reduce** fricción cuando el vínculo activo–cliente ya existe.

---

## Fases de implementación

### Fase 1 — Especificación y compilador (backend + tipos)

- [ ] Esquema JSON `block_graph` en `definition.meta` o paralelo hasta migración.  
- [ ] `WorkflowBlockCompiler` + tests unitarios (plantillas 033 → bloques → definition idempotente).  
- [ ] Ampliar validador con reglas Aprobar/Rechazar/email.

### Fase 2 — Canvas por etapas (Vue Flow)

- [ ] Swimlanes / nodo Rutina fijo.  
- [ ] Paleta Etapas + Roles; restricción de drag/connect.  
- [ ] Inspector rol (etiqueta, `assigned_role`).  
- [ ] Sustituir tabla de transiciones; quitar checkboxes de configure en UI.

### Fase 3 — Acciones Aprobar / Rechazar

- [ ] Aristas tipadas; solo Rol → Rutina (y Aprobar → siguiente etapa si se valida en producto).  
- [ ] Rechazo visual (curva a Rutina).  
- [ ] `routine_validated` como toggle en arista Aprobar (sustituye checkbox PDF/borrador).

### Fase 4 — Acción Email

- [ ] Nodo/arista email + panel (asunto, HTML, tokens).  
- [ ] Runtime placeholders (rutina, tipo, activo, cliente).  
- [ ] Alinear con `WorkflowStepMail` / vista blade o cuerpo HTML almacenado.

### Fase 5 — API y migración

- [ ] `PUT …/definition` acepta `block_graph` o compila en front y envía `definition` (elegir una estrategia).  
- [ ] Deprecar `PUT …/configure` en docs; migrar workflows draft existentes con script o al abrir diseñador.  
- [ ] Actualizar `openspec/domain/workflows.md`.

### Fase 6 — Cliente en prefactura desde activo

- [ ] Resolver en `InvoiceDraftService` / emisión.  
- [ ] Tests Feature + ajuste UI `InvoiceDetailPage`.  
- [ ] Documentar en `docs/BILLING.md`.

---

## Fuera de alcance (esta iniciativa)

- Gateways condicionales por campo de formulario.  
- Múltiples rutinas paralelas en un mismo workflow.  
- Editor de roles en el mismo canvas (sigue RBAC plataforma 035).  
- Sustituir Vue Flow por otro diagramador.

---

## Criterios de aceptación

1. Diseñador sin secciones «Configuración del flujo» ni «Transiciones (vista rápida)»; flujo armado solo con bloques en canvas.  
2. Rutina no eliminable ni duplicable desde paleta.  
3. Intento de Aprobar/Rechazar fuera de Rol→Rutina (o regla acordada) → error en UI y 422 al guardar.  
4. Email con cuerpo enriquecido y al menos un token (p. ej. `{routine.code}`) en envío de prueba.  
5. Rutina con activo con cliente asignado genera borrador con `client_id` sin selección manual.  
6. Publicar workflow compilado pasa validador y rutina demo recorre el grafo.

---

## Riesgos y mitigación

| Riesgo | Mitigación |
|--------|------------|
| Ambigüedad Rol→Rutina vs Rol→siguiente etapa | Prototipo Figma + 1 sesión de validación; fijar en glosario antes de Fase 3 |
| Doble fuente verdad (bloques vs definition) | Compilador único; guardar `block_graph` como fuente en draft |
| Emails HTML inseguros | Sanitizar al guardar y al renderizar (misma política que reportes) |

---

## Dependencias

- 033 (plantillas, publish, runtime).  
- Vue Flow actual (`WorkflowFlowCanvas` / mapper).  
- Asignación activo–cliente (assets).  
- TipTap / rich text existente en reportes.

## Referencias

- `resources/js/lib/workflowFlowMapper.ts`  
- `app/Services/Workflow/WorkflowDefinitionFactory.php`  
- `openspec/changes/033-workflow-templates-custom/proposal.md`
