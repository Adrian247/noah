import 'package:flutter_test/flutter_test.dart';
import 'package:phoenix_field/data/repositories/media_repository.dart';

void main() {
  group('isLikelyRemoteMediaPath', () {
    test('rejects catalog option values', () {
      expect(isLikelyRemoteMediaPath('tres_cuartos'), isFalse);
      expect(isLikelyRemoteMediaPath('ok'), isFalse);
      expect(isLikelyRemoteMediaPath('local:abc'), isFalse);
    });

    test('accepts evidence paths and image urls', () {
      expect(
        isLikelyRemoteMediaPath(
          'executions/6/demo/foto_tablero-0-49e19563.jpg',
        ),
        isTrue,
      );
      expect(
        isLikelyRemoteMediaPath('executions/6/fields/abc.jpg'),
        isTrue,
      );
      expect(
        isLikelyRemoteMediaPath('https://cdn.example/photo.jpg'),
        isTrue,
      );
    });
  });
}
