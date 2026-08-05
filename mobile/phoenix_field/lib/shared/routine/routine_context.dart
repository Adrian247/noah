/// Datos de identificación del servicio para lista y detalle (offline desde el pull).
class RoutineContext {
  const RoutineContext({
    required this.title,
    required this.typeName,
    this.serviceLineLabel,
    this.clientName,
    this.siteName,
    this.siteAddress,
    this.assetTag,
    this.assetSerial,
    this.locationLabel,
    this.catalogName,
    this.catalogCode,
  });

  final String title;
  final String typeName;
  final String? serviceLineLabel;
  final String? clientName;
  final String? siteName;
  final String? siteAddress;
  final String? assetTag;
  final String? assetSerial;
  final String? locationLabel;
  final String? catalogName;
  final String? catalogCode;

  /// Líneas compactas para la tarjeta de lista (sitio, ubicación, cliente…).
  List<String> get listSubtitles {
    final lines = <String>[];
    if (siteName != null) {
      final addressBit =
          siteAddress != null && siteAddress!.isNotEmpty ? ' · $siteAddress' : '';
      lines.add('Sitio: $siteName$addressBit');
    } else if (siteAddress != null) {
      lines.add(siteAddress!);
    }
    if (locationLabel != null) {
      lines.add('Ubicación: $locationLabel');
    }
    if (assetTag != null) {
      final serialBit =
          assetSerial != null && assetSerial!.isNotEmpty ? ' · S/N $assetSerial' : '';
      lines.add('Activo: $assetTag$serialBit');
    } else if (catalogName != null) {
      final codeBit =
          catalogCode != null && catalogCode!.isNotEmpty ? ' ($catalogCode)' : '';
      lines.add('Artículo: $catalogName$codeBit');
    }
    if (clientName != null && (assetTag != null || siteName != null)) {
      lines.add('Cliente: $clientName');
    }
    return lines;
  }

  /// Filas etiqueta → valor para la tarjeta de detalle.
  List<({String label, String value})> get detailRows {
    final rows = <({String label, String value})>[];
    if (serviceLineLabel != null) {
      rows.add((label: 'Línea', value: serviceLineLabel!));
    }
    if (clientName != null) {
      rows.add((label: 'Cliente', value: clientName!));
    }
    if (siteName != null) {
      rows.add((label: 'Sitio', value: siteName!));
    }
    if (siteAddress != null) {
      rows.add((label: 'Dirección', value: siteAddress!));
    }
    if (locationLabel != null) {
      rows.add((label: 'Ubicación', value: locationLabel!));
    }
    if (assetTag != null) {
      rows.add((label: 'Activo / tag', value: assetTag!));
    }
    if (assetSerial != null) {
      rows.add((label: 'Serie', value: assetSerial!));
    }
    if (catalogName != null) {
      final codeBit =
          catalogCode != null && catalogCode!.isNotEmpty ? ' ($catalogCode)' : '';
      rows.add((label: 'Artículo', value: '$catalogName$codeBit'));
    }
    if (rows.isEmpty) {
      rows.add((label: 'Sujeto', value: '—'));
    }
    return rows;
  }

  factory RoutineContext.fromPayload(Map<String, dynamic> map, {int? fallbackId}) {
    final type = _nestedString(map, ['routine_type', 'name']) ?? 'Servicio';
    final serviceLine = _serviceLineLabel(
      _nestedString(map, ['routine_type', 'service_line']) ??
          _nestedString(map, ['routine_type', 'service_category']),
    );

    final asset = map['asset'];
    final assetMap = asset is Map ? Map<String, dynamic>.from(asset) : null;
    final catalog = assetMap?['catalog_item'];
    final catalogMap = catalog is Map ? Map<String, dynamic>.from(catalog) : null;

    final site = map['site'];
    final siteMap = site is Map ? Map<String, dynamic>.from(site) : null;

    final client = map['client'];
    final clientMap = client is Map ? Map<String, dynamic>.from(client) : null;
    final siteClient = siteMap?['client'];
    final siteClientMap =
        siteClient is Map ? Map<String, dynamic>.from(siteClient) : null;

    final assetTag = _nonEmpty(assetMap?['tag']?.toString());
    final assetSerial = _nonEmpty(assetMap?['serial_number']?.toString());
    final locationLabel = _nonEmpty(assetMap?['location_label']?.toString());
    final catalogName = _nonEmpty(catalogMap?['name']?.toString());
    final catalogCode = _nonEmpty(catalogMap?['code']?.toString());
    final siteName = _nonEmpty(siteMap?['name']?.toString());
    final siteAddress = _nonEmpty(siteMap?['address']?.toString());
    final clientName = _nonEmpty(clientMap?['trade_name']?.toString()) ??
        _nonEmpty(clientMap?['legal_name']?.toString()) ??
        _nonEmpty(siteClientMap?['trade_name']?.toString()) ??
        _nonEmpty(siteClientMap?['legal_name']?.toString());

    final subject = assetTag ??
        catalogName ??
        siteName ??
        clientName ??
        (fallbackId != null ? '#$fallbackId' : null);
    final title = subject != null ? '$type — $subject' : type;

    return RoutineContext(
      title: title,
      typeName: type,
      serviceLineLabel: serviceLine,
      clientName: clientName,
      siteName: siteName,
      siteAddress: siteAddress,
      assetTag: assetTag,
      assetSerial: assetSerial,
      locationLabel: locationLabel,
      catalogName: catalogName,
      catalogCode: catalogCode,
    );
  }

  static String? _nestedString(Map<String, dynamic> map, List<String> path) {
    dynamic cur = map;
    for (final key in path) {
      if (cur is! Map) {
        return null;
      }
      cur = cur[key];
    }
    return _nonEmpty(cur?.toString());
  }

  static String? _nonEmpty(String? value) {
    if (value == null) {
      return null;
    }
    final trimmed = value.trim();
    return trimmed.isEmpty ? null : trimmed;
  }

  static String? _serviceLineLabel(String? raw) {
    return switch (raw) {
      'manufacturing' || 'fabrication' => 'Fabricación',
      'installation' || 'supply' => 'Instalación',
      'maintenance' => 'Mantenimiento',
      _ => null,
    };
  }
}
