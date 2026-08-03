#!/usr/bin/env python3
"""Entrena el modelo de riesgo con el dataset exportado desde Laravel.

    docker compose exec app php artisan phoenix:predictive:dataset --company=1
    python scripts/train.py data/training.json
"""

from __future__ import annotations

import argparse
import json
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent.parent))

from app.model import MODEL_PATH, load_dataset, train  # noqa: E402


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("dataset", help="JSON generado por phoenix:predictive:dataset")
    parser.add_argument(
        "--holdout",
        type=float,
        default=0.25,
        help="Fracción reciente reservada para medir (corte temporal). Default 0.25.",
    )
    parser.add_argument("--json", action="store_true", help="Imprime el reporte como JSON")
    args = parser.parse_args()

    dataset = load_dataset(args.dataset)

    try:
        report = train(dataset, holdout_fraction=args.holdout)
    except ValueError as error:
        print(f"No se pudo entrenar: {error}", file=sys.stderr)
        return 1

    if args.json:
        print(json.dumps(report.to_dict(), ensure_ascii=False, indent=2))
        return 0

    print(f"Modelo {report.version} guardado en {MODEL_PATH}")
    print(f"  filas: {report.rows} ({report.positives} positivas) · características: {report.features}")
    print(f"  holdout temporal: {report.holdout_rows} filas")
    for name, value in report.metrics.items():
        print(f"  {name}: {value}")
    if report.top_features:
        print("  señales con más peso:")
        for name, importance in report.top_features:
            print(f"    - {name}: {importance}")

    return 0


if __name__ == "__main__":
    raise SystemExit(main())
