import 'package:hive_flutter/hive_flutter.dart';
import 'package:triosense/core/cache/hive_boxes.dart';

class TokenStore {
  static const String tokenKey = 'auth_token';

  String? read() => HiveBoxes.auth.get(tokenKey);

  Future<void> write(String token) async {
    await HiveBoxes.auth.put(tokenKey, token);
  }

  Future<void> clear() async {
    await HiveBoxes.auth.delete(tokenKey);
  }

  bool get isAuthenticated {
    final token = read();
    return token != null && token.isNotEmpty;
  }
}
