import 'package:dio/dio.dart';
import 'package:logger/logger.dart';
import 'package:triosense/core/auth/token_store.dart';

class ApiClient {
  ApiClient(this._tokenStore)
      : _dio = Dio(
          BaseOptions(
            baseUrl: const String.fromEnvironment(
              'API_BASE_URL',
              defaultValue: 'http://localhost:8000',
            ),
            connectTimeout: const Duration(seconds: 10),
            receiveTimeout: const Duration(seconds: 10),
            headers: {
              'Accept': 'application/json',
              'Content-Type': 'application/json',
            },
          ),
        ) {
    _dio.interceptors.add(
      InterceptorsWrapper(
        onRequest: (options, handler) {
          final token = _tokenStore.read();
          if (token != null && token.isNotEmpty) {
            options.headers['Authorization'] = 'Bearer $token';
          }
          _log.d('API ${options.method} ${options.path}');
          handler.next(options);
        },
        onError: (error, handler) {
          _log.e('API error ${error.response?.statusCode} ${error.message}');
          handler.next(error);
        },
      ),
    );
  }

  final TokenStore _tokenStore;
  final Dio _dio;
  final Logger _log = Logger();

  Dio get dio => _dio;
}
