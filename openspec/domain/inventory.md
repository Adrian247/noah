# Dominio — Inventory (Phoenix)

## Responsabilidad

Insumos, refacciones, costos unitarios y movimientos asociados a mantenimiento.

## Agregados

### SupplyItem

- SKU o código, descripción, unidad de medida, costo estándar, proveedor opcional.
- Stock opcional en v1 (registro de consumo sin inventario estricto).

### ConsumptionLine

- Ligada a **Execution** de rutina: `supply_item_id`, cantidad, costo unitario capturado o calculado.
- Contribuye al **costo de mantenimiento** agregado.

### StockMovement (fase opcional)

- Entrada/salida; ajuste manual por administrador.

## Cálculo de costo en rutina

```
costo_mantenimiento = Σ(consumos) + costo_mano_obra(tiempos, tarifas configurables)
```

Tarifas de mano de obra: configuración por empresa o tipo de rutina (metadatos).

## Invariantes

- Consumos solo en ejecuciones no facturadas o según política de bloqueo post-validación.
- Cantidades > 0; unidades coherentes con SupplyItem.

## Eventos

- `SupplyItemCreated`, `ConsumptionRecorded`, `StockAdjusted`
