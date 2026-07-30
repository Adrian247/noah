#!/usr/bin/env bash
# Arranque local de Phoenix Campo (Flutter).
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

if [[ -x "$HOME/flutter/bin/flutter" ]]; then
  export PATH="$HOME/flutter/bin:$PATH"
fi

if ! command -v flutter >/dev/null 2>&1; then
  echo "Flutter no está en PATH."
  echo ""
  echo "Opción A — instalar SDK en ~/flutter:"
  echo "  git clone https://github.com/flutter/flutter.git -b stable --depth 1 ~/flutter"
  echo "  echo 'export PATH=\"\$HOME/flutter/bin:\$PATH\"' >> ~/.zshrc"
  echo "  source ~/.zshrc"
  echo ""
  echo "Opción B — si ya lo tienes en otro sitio, exporta PATH antes de correr este script."
  exit 1
fi

case "${1:-run}" in
  deps)
    flutter pub get
    dart run build_runner build
    ;;
  run)
    flutter pub get
    dart run build_runner build
    flutter run -d linux "${@:2}"
    ;;
  doctor)
    flutter doctor -v
    ;;
  *)
    echo "Uso: ./tool/dev.sh [deps|run|doctor] [args para flutter run]"
    exit 1
    ;;
esac
