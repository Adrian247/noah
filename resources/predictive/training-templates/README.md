# Plantillas de entrenamiento predictivo

Archivos de ejemplo para subir en **Plataforma → Algoritmos predictivos**.

Contrato JSON: `phoenix.predictive.training/v1`.

| Archivo | Algoritmo | Uso |
|---------|-----------|-----|
| `maintenance_hazard_v2.json` / `.csv` | Mantenimiento | Ventanas etiquetadas: ¿el activo falló / requirió servicio en el horizonte? |
| `manufacturing_demand_v1.json` / `.csv` | Manufactura | Eventos históricos de servicio de fabricación por cliente |
| `inventory_demand_v1.json` / `.csv` | Inventario | Solicitudes históricas de compra de artículos por cliente |

Sustituye los códigos de ejemplo (`asset_tag`, `client_code`, `catalog_item_code`) por valores reales de tu tenant.

La **regresión** no usa estos archivos: es un backtest automático sobre el historial de empresas con opt-in.
