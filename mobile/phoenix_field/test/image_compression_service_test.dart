import 'dart:io';

import 'package:flutter_test/flutter_test.dart';
import 'package:image/image.dart' as img;
import 'package:phoenix_field/shared/media/image_compression_service.dart';

void main() {
  test('compressToFile reduces large jpeg payload', () async {
    final tempDir = await Directory.systemTemp.createTemp('phoenix_compress_');
    try {
      final source = File('${tempDir.path}/source.png');
      final image = img.Image(width: 3200, height: 2400);
      img.fill(image, color: img.ColorRgb8(120, 80, 40));
      await source.writeAsBytes(img.encodePng(image));

      final target = File('${tempDir.path}/target.jpg');
      final storedPath = await ImageCompressionService.compressToFile(
        sourcePath: source.path,
        targetPath: target.path,
      );

      final stored = File(storedPath);
      expect(stored.existsSync(), isTrue);
      expect(stored.lengthSync(), lessThanOrEqualTo(ImageCompressionService.defaultMaxBytes));

      final decoded = img.decodeImage(await stored.readAsBytes());
      expect(decoded, isNotNull);
      expect(
        decoded!.width <= ImageCompressionService.defaultMaxDimension ||
            decoded.height <= ImageCompressionService.defaultMaxDimension,
        isTrue,
      );
    } finally {
      await tempDir.delete(recursive: true);
    }
  });
}
