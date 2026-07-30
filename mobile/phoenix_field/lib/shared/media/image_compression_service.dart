import 'dart:io';

import 'package:image/image.dart' as img;
import 'package:path/path.dart' as p;

class ImageCompressionService {
  const ImageCompressionService._();

  static const defaultMaxDimension = 1920;
  static const defaultMaxBytes = 2048 * 1024;

  /// Comprime [sourcePath] hacia [targetPath] (JPEG). Devuelve el path destino.
  static Future<String> compressToFile({
    required String sourcePath,
    required String targetPath,
    int maxDimension = defaultMaxDimension,
    int maxBytes = defaultMaxBytes,
  }) async {
    final source = File(sourcePath);
    if (!source.existsSync()) {
      throw StateError('Archivo no encontrado: $sourcePath');
    }

    final bytes = await source.readAsBytes();
    final decoded = img.decodeImage(bytes);
    if (decoded == null) {
      await source.copy(targetPath);
      return targetPath;
    }

    var working = decoded;
    final longestSide = working.width > working.height ? working.width : working.height;
    if (longestSide > maxDimension) {
      working = img.copyResize(
        working,
        width: working.width >= working.height ? maxDimension : null,
        height: working.height > working.width ? maxDimension : null,
      );
    }

    var quality = 85;
    List<int> encoded = img.encodeJpg(working, quality: quality);
    while (encoded.length > maxBytes && quality > 55) {
      quality -= 5;
      encoded = img.encodeJpg(working, quality: quality);
    }

    if (encoded.length > maxBytes && working.width > 960) {
      working = img.copyResize(
        working,
        width: (working.width * 0.75).round(),
        height: (working.height * 0.75).round(),
      );
      quality = 80;
      encoded = img.encodeJpg(working, quality: quality);
    }

    final target = File(_jpegTargetPath(targetPath));
    await target.writeAsBytes(encoded, flush: true);
    return target.path;
  }

  static String _jpegTargetPath(String targetPath) {
    final ext = p.extension(targetPath).toLowerCase();
    if (ext == '.jpg' || ext == '.jpeg') {
      return targetPath;
    }
    return p.setExtension(targetPath, '.jpg');
  }
}
