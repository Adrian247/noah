import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:phoenix_field/core/network/dio_provider.dart';

class SyncApi {
  SyncApi(this._dio);

  final Dio _dio;

  Future<Map<String, dynamic>> sync({
    required String deviceId,
    List<Map<String, dynamic>> events = const [],
    bool pull = true,
  }) async {
    final response = await _dio.post<Map<String, dynamic>>(
      '/sync',
      data: {
        'device_id': deviceId,
        'events': events,
        'pull': pull,
      },
    );

    final data = response.data?['data'];
    if (data is Map<String, dynamic>) {
      return data;
    }
    return {};
  }
}

final syncApiProvider = Provider<SyncApi>((ref) => SyncApi(ref.watch(dioProvider)));
