import 'package:dio/dio.dart';
import 'package:logger/logger.dart';
import 'package:triosense/core/api/api_client.dart';
import 'package:triosense/core/auth/token_store.dart';
import 'package:triosense/features/auth/domain/entities/auth_user.dart';

class AuthRepository {
  AuthRepository(this._apiClient, this._tokenStore);

  final ApiClient _apiClient;
  final TokenStore _tokenStore;
  final Logger _log = Logger();

  Future<AuthUser> login({required String email, required String password}) async {
    try {
      final response = await _apiClient.dio.post<Map<String, dynamic>>(
        '/api/v1/auth/login',
        data: {'email': email, 'password': password},
      );

      final body = response.data;
      if (body == null || body['success'] != true) {
        throw Exception('Login failed');
      }

      final data = body['data'] as Map<String, dynamic>;
      final token = data['token'] as String;
      await _tokenStore.write(token);

      final user = AuthUser.fromJson(data['user'] as Map<String, dynamic>);
      _log.i('AuthRepository.login success user=${user.userId}');
      return user;
    } on DioException catch (e) {
      _log.e('AuthRepository.login failed', error: e);
      rethrow;
    }
  }

  Future<void> logout() async {
    try {
      await _apiClient.dio.post<void>('/api/v1/auth/logout');
    } finally {
      await _tokenStore.clear();
    }
  }
}
