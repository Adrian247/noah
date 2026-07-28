# Ficha Técnica y Cotización de Refacciones: Mitsubishi L200 2018 (México)

> Copia de referencia para change **037** (origen: ficha compartida por producto).  
> Uso en Noah: `CatalogItem.specifications` (JSON), insumos demo (`supply_items`) y texto de ayuda en catálogo.

## 1. Especificaciones Técnicas

La Mitsubishi L200 2018 comercializada en México es una pickup mediana sumamente resistente, construida sobre el chasis "Rise Body". Se ofreció principalmente con dos opciones de motorización orientadas al trabajo pesado y uso mixto.

### Motorización y Desempeño

| Característica | Versión Gasolina (2.4L) | Versión Diésel (2.5L DI-D) |
| :--- | :--- | :--- |
| **Motor** | 2.4 Litros, 4 cilindros en línea | 2.5 Litros, 4 cilindros Turbo Diésel (Intercooler) |
| **Potencia Máxima** | 126 HP @ 5,250 rpm | 134 HP @ 4,000 rpm |
| **Torque Máximo** | 143 lb-pie @ 4,000 rpm | 232 lb-pie @ 2,000 rpm |
| **Transmisión** | Manual de 5 velocidades | Manual de 5 velocidades (con caja reductora) |
| **Tracción** | 4x2 (Trasera) | 4x4 (Easy Select 4WD) |
| **Alimentación** | Inyección Multipunto | Inyección Directa Common Rail de Alta Presión |

### Suspensión y Frenos

* **Suspensión Delantera:** Independiente de doble horquilla con resortes helicoidales y barra estabilizadora.
* **Suspensión Trasera:** Eje rígido con muelles elípticos (ballestas) reforzadas para carga.
* **Frenos Delanteros:** Discos ventilados.
* **Frenos Traseros:** Tambor.
* **Asistencias:** Sistema Antibloqueo (ABS) y Distribución Electrónica de Frenado (EBD).
* **Dirección:** Hidráulica asistida de piñón y cremallera.

### Dimensiones, Peso y Capacidades

* **Longitud Total:** 5,205 mm
* **Anchura Total:** 1,785 mm
* **Altura Total:** 1,775 mm
* **Distancia entre Ejes (Batalla):** 3,000 mm
* **Capacidad de Carga:** 1,040 kg a 1,060 kg (dependiendo de la versión).
* **Capacidad del Tanque de Combustible:** 75 Litros.
* **Rines:** Aluminio de 16 pulgadas.

---

## 2. Lista de Refacciones y Precios en México

Estimación de precios para repuestos comunes de mantenimiento en el mercado mexicano (MXN). En Noah: `supply_items.standard_cost` (valor medio o mínimo del rango).

| Refacción | Marca / Modelo Recomendado | Precio Estimado (MXN) | SKU sugerido (demo) |
| :--- | :--- | :--- | :--- |
| **Balatas Delanteras (Juego)** | Brembo Cerámicas (P54038) | $1,130.00 - $1,406.00 | `FRE-P54038-BRM` |
| **Filtro de Aceite** | Mitsubishi Original (OEM 1230a153) | $500.00 - $600.00 | `FIL-1230A153-OEM` |
| **Amortiguadores Delanteros (Par)** | GROB (Gas) | $1,698.00 | `SUS-AMORT-GROB-PAR` |
| **Filtro de Aire** | Sakura (2030515) | $237.00 - $445.00 | `FIL-AIR-2030515-SAK` |

*Nota: Los precios mencionados son referencias de mercado a través de distribuidores de autopartes en México.*

---

## 3. Mapeo a entidades Noah (037)

### CatalogItem (equipo plantilla)

| Campo | Valor demo |
|-------|------------|
| `code` | `VEH-L200-2018` |
| `name` | Mitsubishi L200 2018 |
| `manufacturer` | Mitsubishi |
| `equipment_type` | Vehículo (`vehiculo`) |

### `specifications` (JSON sugerido)

```json
{
  "modelo": "L200",
  "anio": 2018,
  "mercado": "MX",
  "chasis": "Rise Body",
  "variante_demo": "2.5L DI-D 4x4",
  "motor": "2.5L Turbo Diésel Common Rail",
  "potencia_hp": 134,
  "torque_lb_pie": 232,
  "transmision": "Manual 5 vel. + reductora",
  "traccion": "4x4 Easy Select",
  "frenos_delanteros": "Discos ventilados",
  "frenos_traseros": "Tambor",
  "tanque_litros": 75,
  "capacidad_carga_kg": 1050,
  "dimensiones_mm": { "largo": 5205, "ancho": 1785, "alto": 1775, "batalla": 3000 }
}
```

### Asset (instancia)

| Campo | Valor demo |
|-------|------------|
| `tag` | `L200-2018-DEMO` |
| `location_label` | Bahía 3 — recepción |
| Vincular | `catalog_item_id` → L200 2018 |

### SupplyItem (insumos)

Usar SKUs de la tabla §2; `supply_type`: Frenos / Filtros y lubricantes / Suspensión según taxonomía 037.

| SKU | `standard_cost` (MXN, referencia) |
|-----|-----------------------------------|
| `FRE-P54038-BRM` | 1268.00 |
| `FIL-1230A153-OEM` | 550.00 |
| `SUS-AMORT-GROB-PAR` | 1698.00 |
| `FIL-AIR-2030515-SAK` | 341.00 |

El formulario de rutina **no cambia** por esta ficha: sigue el de revisión mayor vehículo; la ficha alimenta catálogo, activo e insumos.
