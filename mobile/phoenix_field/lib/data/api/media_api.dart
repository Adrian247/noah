import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:phoenix_field/core/network/dio_provider.dart';

class MediaApi {
  MediaApi(this._dio);

  final Dio _dio;

  Future<Map<String, dynamic>> uploadFormField({
    required int routineId,
    required String fieldKey,
    required String filePath,
    String? fileName,
  }) async {
    final formData = FormData.fromMap({
      'field_key': fieldKey,
      'file': await MultipartFile.fromFile(
        filePath,
        filename: fileName ?? 'photo.jpg',
      ),
    });

    final response = await _dio.post<Map<String, dynamic>>(
      '/routines/$routineId/form-field-upload',
      data: formData,
    );

    final data = response.data?['data'];
    if (data is Map<String, dynamic>) {
      return data;
    }
    throw StateError('Respuesta de subida inválida');
  }

  /// Descarga bytes de una imagen de campo ya almacenada en el servidor.
  Future<List<int>> fetchFormFieldMedia({
    required int routineId,
    required String path,
  }) async {
    final response = await _dio.get<List<int>>(
      '/routines/$routineId/form-field-media',
      queryParameters: {'path': path},
      options: Options(responseType: ResponseType.bytes),
    );
    final data = response.data;
    if (data == null || data.isEmpty) {
      throw StateError('Imagen vacía');
    }
    return data;
  }
}

final mediaApiProvider = Provider<MediaApi>((ref) => MediaApi(ref.watch(dioProvider)));
