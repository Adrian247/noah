import 'package:flutter_test/flutter_test.dart';
import 'package:phoenix_field/core/network/api_url_resolver.dart';

void main() {
  group('ApiUrlResolver.resolveAssetUrl', () {
    const api = 'http://192.168.1.42:8888/api/v1';

    test('rewrites localhost storage url to api host', () {
      final resolved = ApiUrlResolver.resolveAssetUrl(
        'http://localhost:8888/storage/avatars/1/photo.jpg',
        api,
      );
      expect(resolved, 'http://192.168.1.42:8888/storage/avatars/1/photo.jpg');
    });

    test('prefixes relative storage path', () {
      final resolved = ApiUrlResolver.resolveAssetUrl(
        '/storage/avatars/1/photo.jpg',
        api,
      );
      expect(resolved, 'http://192.168.1.42:8888/storage/avatars/1/photo.jpg');
    });
  });
}
