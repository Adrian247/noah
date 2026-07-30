import 'package:dio/dio.dart';

Future<void> main() async {
  final urls = [
    'http://127.0.0.1:8888/api/v1',
    'http://localhost:8888/api/v1',
    'http://10.0.2.2:8888/api/v1',
  ];

  for (final base in urls) {
    final dio = Dio(
      BaseOptions(
        baseUrl: base,
        connectTimeout: const Duration(seconds: 5),
        receiveTimeout: const Duration(seconds: 5),
      ),
    );
    try {
      final response = await dio.get<dynamic>('/health');
      print('OK $base -> ${response.statusCode} ${response.data}');
    } catch (e) {
      print('FAIL $base -> $e');
    }
  }
}
