import 'dart:io' show Platform;

import 'package:file_picker/file_picker.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';

class PhotoPickerService {
  const PhotoPickerService._();

  static final _picker = ImagePicker();

  static bool get supportsCamera =>
      !kIsWeb && (Platform.isAndroid || Platform.isIOS);

  static Future<List<String>> pickPaths({
    required BuildContext context,
    required bool allowMultiple,
    ImageSource? forcedSource,
  }) async {
    if (supportsCamera) {
      final source = forcedSource ?? await _askSource(context);
      if (source == null) {
        return [];
      }

      if (allowMultiple && source == ImageSource.gallery) {
        final files = await _picker.pickMultiImage(imageQuality: 85);
        return files.map((f) => f.path).whereType<String>().toList();
      }

      final file = await _picker.pickImage(
        source: source,
        imageQuality: 85,
      );
      if (file == null) {
        return [];
      }
      return [file.path];
    }

    final result = await FilePicker.platform.pickFiles(
      type: FileType.image,
      allowMultiple: allowMultiple,
    );
    if (result == null) {
      return [];
    }
    return result.files.map((f) => f.path).whereType<String>().toList();
  }

  static Future<ImageSource?> _askSource(BuildContext context) async {
    return showModalBottomSheet<ImageSource>(
      context: context,
      builder: (context) => SafeArea(
        child: Wrap(
          children: [
            ListTile(
              leading: const Icon(Icons.photo_camera_outlined),
              title: const Text('Tomar foto'),
              onTap: () => Navigator.pop(context, ImageSource.camera),
            ),
            ListTile(
              leading: const Icon(Icons.photo_library_outlined),
              title: const Text('Elegir de galería'),
              onTap: () => Navigator.pop(context, ImageSource.gallery),
            ),
          ],
        ),
      ),
    );
  }
}
