#!/usr/bin/env bash
# Genera APK release firmado de Phoenix Campo.
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

if [[ -x "$HOME/flutter/bin/flutter" ]]; then
  export PATH="$HOME/flutter/bin:$PATH"
fi

KEY_PROPS="$ROOT/android/key.properties"
if [[ ! -f "$KEY_PROPS" ]]; then
  echo "Falta android/key.properties (copia desde android/key.properties.example)."
  echo "También necesitas el keystore en android/keystore/phoenix-field.jks"
  exit 1
fi

flutter pub get
dart run build_runner build --delete-conflicting-outputs
flutter build apk --release "$@"

echo ""
echo "APK: build/app/outputs/flutter-apk/app-release.apk"
