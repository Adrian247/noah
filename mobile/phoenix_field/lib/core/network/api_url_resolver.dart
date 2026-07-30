class ApiUrlResolver {
  const ApiUrlResolver._();

  /// Convierte rutas relativas o URLs con `localhost` al host configurado en la API móvil.
  static String? resolveAssetUrl(String? raw, String apiBaseUrl) {
    if (raw == null || raw.trim().isEmpty) {
      return null;
    }

    final trimmed = raw.trim();
    final apiUri = Uri.parse(apiBaseUrl);
    final origin = Uri(
      scheme: apiUri.scheme,
      host: apiUri.host,
      port: apiUri.hasPort ? apiUri.port : null,
    ).toString();

    if (trimmed.startsWith('/')) {
      return '$origin$trimmed';
    }

    final assetUri = Uri.tryParse(trimmed);
    if (assetUri == null || !assetUri.hasScheme) {
      return trimmed;
    }

    final localHosts = {'localhost', '127.0.0.1', '10.0.2.2'};
    if (localHosts.contains(assetUri.host)) {
      return assetUri
          .replace(
            scheme: apiUri.scheme,
            host: apiUri.host,
            port: apiUri.hasPort ? apiUri.port : assetUri.port,
          )
          .toString();
    }

    return trimmed;
  }
}
