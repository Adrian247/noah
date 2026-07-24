# Implementación — Noah (estado)

Referencia rápida del código respecto a [openspec/](openspec/README.md).

## Fase actual

MVP técnico en progreso: **identidad, multitenancy, catálogos, activos, rutinas y ejecución** con API REST y SPA de administración.

## Backend (`app/`)

| Área | Estado |
|------|--------|
| Sanctum + login/logout/me | Implementado |
| `X-Company-Id` + scope por empresa | Implementado |
| Companies, sites, memberships, roles | Modelo + seed |
| Catalog items, supply items, assets | CRUD API básico |
| Form/report definitions (JSON) | Tablas + seed |
| Routine types, routines, executions | API + validación supervisor |
| Grammar correction | Stub (`GrammarCorrectionService`) → AI Gateway futuro |
| Workflow engine visual | Pendiente |
| PDF report engine | Pendiente |
| Billing | Pendiente |

## Frontend (`resources/js/`)

| Pantalla | Estado |
|----------|--------|
| Login | Implementado |
| Dashboard | Resumen API + conteo rutinas |
| Rutinas | Listado |
| Diseñadores form/reporte | Pendiente |

## Demo local

Tras `php artisan migrate:fresh --seed`:

| Usuario | Contraseña | Rol |
|---------|------------|-----|
| admin@noah.local | password | Administrador |
| tecnico@noah.local | password | Técnico |

## Tests

```bash
php artisan test
```

## Próximos pasos sugeridos

1. AI Gateway real + prompt registry  
2. Generación PDF (Browsershot)  
3. Workflow configurable  
4. Facturación (borradores)  
5. Flutter + sync  
