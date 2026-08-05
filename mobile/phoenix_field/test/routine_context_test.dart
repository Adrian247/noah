import 'package:flutter_test/flutter_test.dart';
import 'package:phoenix_field/shared/routine/routine_context.dart';

void main() {
  test('builds title and location context from sync payload', () {
    final ctx = RoutineContext.fromPayload({
      'id': 54,
      'routine_type': {
        'name': 'Mantenimiento preventivo',
        'service_line': 'maintenance',
      },
      'client': {
        'legal_name': 'Mina del Norte SA',
        'trade_name': 'Mina Norte',
      },
      'site': {
        'name': 'Planta superficie',
        'address': 'Km 12 carretera industrial',
      },
      'asset': {
        'tag': 'SS-305',
        'serial_number': 'SN-7788',
        'location_label': 'Bahía 3 — recepción',
        'catalog_item': {
          'code': 'SCOOP-12',
          'name': 'Scooptram 12t',
        },
      },
    });

    expect(ctx.title, 'Mantenimiento preventivo — SS-305');
    expect(ctx.serviceLineLabel, 'Mantenimiento');
    expect(ctx.listSubtitles, contains('Sitio: Planta superficie · Km 12 carretera industrial'));
    expect(ctx.listSubtitles, contains('Ubicación: Bahía 3 — recepción'));
    expect(ctx.listSubtitles, contains('Activo: SS-305 · S/N SN-7788'));
    expect(ctx.listSubtitles, contains('Cliente: Mina Norte'));
    expect(ctx.detailRows.map((r) => r.label).toList(), containsAll([
      'Cliente',
      'Sitio',
      'Dirección',
      'Ubicación',
      'Activo / tag',
      'Serie',
      'Artículo',
    ]));
  });

  test('falls back to client when there is no asset', () {
    final ctx = RoutineContext.fromPayload({
      'routine_type': {'name': 'Instalación'},
      'client': {'legal_name': 'Cliente Solo'},
      'site': {'name': 'Oficinas'},
    }, fallbackId: 9);

    expect(ctx.title, 'Instalación — Oficinas');
    expect(ctx.clientName, 'Cliente Solo');
    expect(ctx.assetTag, isNull);
  });
}
