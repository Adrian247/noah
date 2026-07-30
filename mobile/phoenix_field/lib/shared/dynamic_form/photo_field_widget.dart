import 'dart:io';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:image_picker/image_picker.dart';
import 'package:phoenix_field/data/repositories/media_repository.dart';
import 'package:phoenix_field/shared/dynamic_form/photo_picker_service.dart';

class PhotoFieldWidget extends ConsumerStatefulWidget {
  const PhotoFieldWidget({
    super.key,
    required this.routineId,
    required this.fieldKey,
    required this.label,
    required this.required,
    required this.allowMultiple,
    required this.maxImages,
    required this.value,
    required this.onChanged,
  });

  final int routineId;
  final String fieldKey;
  final String label;
  final bool required;
  final bool allowMultiple;
  final int maxImages;
  final dynamic value;
  final void Function(dynamic value) onChanged;

  @override
  ConsumerState<PhotoFieldWidget> createState() => _PhotoFieldWidgetState();
}

class _PhotoFieldWidgetState extends ConsumerState<PhotoFieldWidget> {
  bool _picking = false;

  List<Map<String, dynamic>> get _items {
    final value = widget.value;
    if (value == null) {
      return [];
    }
    if (value is String && value.isNotEmpty) {
      return [{'path': value}];
    }
    if (value is Map && value['path'] != null) {
      return [Map<String, dynamic>.from(value)];
    }
    if (value is List) {
      return value
          .map((e) {
            if (e is String) {
              return {'path': e};
            }
            if (e is Map) {
              return Map<String, dynamic>.from(e);
            }
            return null;
          })
          .whereType<Map<String, dynamic>>()
          .toList();
    }
    return [];
  }

  Future<void> _addPaths(List<String> paths) async {
    if (paths.isEmpty) {
      return;
    }

    final media = ref.read(mediaRepositoryProvider);
    final updated = List<Map<String, dynamic>>.from(_items);

    for (final path in paths) {
      if (updated.length >= widget.maxImages) {
        break;
      }
      final localRef = await media.saveLocalPhoto(
        routineId: widget.routineId,
        fieldKey: widget.fieldKey,
        sourcePath: path,
      );
      updated.add({'path': localRef});
    }

    widget.onChanged(_normalizeOutput(updated));
  }

  Future<void> _pickFromGalleryOrFile() async {
    if (_picking || !_canAddMore) {
      return;
    }
    setState(() => _picking = true);
    try {
      final paths = await PhotoPickerService.pickPaths(
        context: context,
        allowMultiple: widget.allowMultiple,
      );
      await _addPaths(paths);
    } finally {
      if (mounted) {
        setState(() => _picking = false);
      }
    }
  }

  Future<void> _pickFromCamera() async {
    if (_picking || !_canAddMore || !PhotoPickerService.supportsCamera) {
      return;
    }
    setState(() => _picking = true);
    try {
      final paths = await PhotoPickerService.pickPaths(
        context: context,
        allowMultiple: false,
        forcedSource: ImageSource.camera,
      );
      await _addPaths(paths);
    } finally {
      if (mounted) {
        setState(() => _picking = false);
      }
    }
  }

  bool get _canAddMore {
    if (!widget.allowMultiple && _items.isNotEmpty) {
      return false;
    }
    return _items.length < widget.maxImages;
  }

  dynamic _normalizeOutput(List<Map<String, dynamic>> items) {
    if (items.isEmpty) {
      return null;
    }
    if (!widget.allowMultiple && items.length == 1) {
      return items.first;
    }
    return items;
  }

  Future<void> _removeAt(int index) async {
    final updated = List<Map<String, dynamic>>.from(_items)..removeAt(index);
    widget.onChanged(_normalizeOutput(updated));
  }

  @override
  Widget build(BuildContext context) {
    final items = _items;
    final supportsCamera = PhotoPickerService.supportsCamera;

    return Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Text(
            '${widget.label}${widget.required ? ' *' : ''}',
            style: Theme.of(context).textTheme.titleSmall,
          ),
          const SizedBox(height: 8),
          if (items.isNotEmpty)
            Wrap(
              spacing: 8,
              runSpacing: 8,
              children: [
                for (var i = 0; i < items.length; i++)
                  _PhotoThumb(
                    path: items[i]['path']?.toString() ?? '',
                    media: ref.read(mediaRepositoryProvider),
                    onRemove: () => _removeAt(i),
                  ),
              ],
            ),
          const SizedBox(height: 8),
          if (supportsCamera) ...[
            Row(
              children: [
                Expanded(
                  child: OutlinedButton.icon(
                    onPressed: _picking || !_canAddMore ? null : _pickFromCamera,
                    icon: const Icon(Icons.photo_camera_outlined),
                    label: const Text('Cámara'),
                  ),
                ),
                const SizedBox(width: 8),
                Expanded(
                  child: OutlinedButton.icon(
                    onPressed: _picking || !_canAddMore ? null : _pickFromGalleryOrFile,
                    icon: const Icon(Icons.photo_library_outlined),
                    label: const Text('Galería'),
                  ),
                ),
              ],
            ),
          ] else
            OutlinedButton.icon(
              onPressed: _picking || !_canAddMore ? null : _pickFromGalleryOrFile,
              icon: _picking
                  ? const SizedBox(
                      width: 16,
                      height: 16,
                      child: CircularProgressIndicator(strokeWidth: 2),
                    )
                  : const Icon(Icons.add_a_photo_outlined),
              label: Text(
                items.isEmpty
                    ? 'Agregar foto'
                    : 'Agregar otra (${items.length}/${widget.maxImages})',
              ),
            ),
        ],
      ),
    );
  }
}

class _PhotoThumb extends StatelessWidget {
  const _PhotoThumb({
    required this.path,
    required this.media,
    required this.onRemove,
  });

  final String path;
  final MediaRepository media;
  final VoidCallback onRemove;

  @override
  Widget build(BuildContext context) {
    return FutureBuilder<String?>(
      future: path.startsWith(localMediaPrefix)
          ? media.resolveLocalPath(path)
          : Future.value(path),
      builder: (context, snapshot) {
        final filePath = snapshot.data;
        return Stack(
          children: [
            ClipRRect(
              borderRadius: BorderRadius.circular(8),
              child: filePath != null && File(filePath).existsSync()
                  ? Image.file(
                      File(filePath),
                      width: 88,
                      height: 88,
                      fit: BoxFit.cover,
                    )
                  : Container(
                      width: 88,
                      height: 88,
                      color: Colors.white12,
                      child: const Icon(Icons.image_outlined),
                    ),
            ),
            Positioned(
              top: 0,
              right: 0,
              child: IconButton(
                visualDensity: VisualDensity.compact,
                icon: const Icon(Icons.close, size: 18),
                onPressed: onRemove,
              ),
            ),
          ],
        );
      },
    );
  }
}
