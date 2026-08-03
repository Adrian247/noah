#!/usr/bin/env python3
"""Recorta un dataset de ingesta a un fixture chico para las pruebas de regresión de PHPUnit.

Se conservan solo los equipos que tienen bitácora (en el libro de planta son 18 de 780 registros de
activo) y se descartan los eventos de máquina, que son la mayor parte del peso y no hacen falta para
verificar el contrato de ingesta ni el backtest.

    python scripts/make_test_fixture.py data/plant-4400.json \
        ../../tests/Fixtures/Predictive/plant-logbook.json
"""

from __future__ import annotations

import argparse
import json
from pathlib import Path


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("source", type=Path)
    parser.add_argument("output", type=Path)
    parser.add_argument("--max-events", type=int, default=400)
    args = parser.parse_args()

    dataset = json.loads(args.source.read_text(encoding="utf-8"))

    logged_tags = {row["asset_tag"] for row in dataset.get("shift_logs", [])}
    dataset["assets"] = [a for a in dataset.get("assets", []) if a["tag"] in logged_tags]

    for key in ("events", "failures", "work_orders", "component_replacements", "measurements"):
        dataset[key] = [row for row in dataset.get(key, []) if row.get("asset_tag", "") in logged_tags or "asset_tag" not in row]

    # De los eventos se conservan los más recientes de cada equipo, suficientes para que el motor
    # tenga alarmas con las que trabajar sin inflar el fixture.
    events = sorted(dataset["events"], key=lambda row: row.get("occurred_at", ""), reverse=True)
    dataset["events"] = events[: args.max_events]

    dataset["meta"]["fixture_of"] = dataset["meta"].get("source_file")
    dataset["meta"]["counts"] = {
        key: len(value) for key, value in dataset.items() if isinstance(value, list)
    }

    args.output.parent.mkdir(parents=True, exist_ok=True)
    # Sin indentar: es un fixture que se versiona, no un archivo para leer a mano.
    args.output.write_text(
        json.dumps(dataset, ensure_ascii=False, separators=(",", ":")), encoding="utf-8"
    )

    print(f"{args.output} ({args.output.stat().st_size // 1024} KB)")
    for key, total in dataset["meta"]["counts"].items():
        print(f"  {key}: {total}")

    return 0


if __name__ == "__main__":
    raise SystemExit(main())
