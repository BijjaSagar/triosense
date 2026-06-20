import 'package:logger/logger.dart';

/// Registers FCM token with backend when Firebase is configured.
class FcmService {
  final Logger _log = Logger();

  Future<void> initialize() async {
    _log.i('FcmService.initialize stub — register token after Firebase setup');
  }

  Future<void> registerToken(String token, Future<void> Function(String) register) async {
    _log.i('FcmService.registerToken');
    await register(token);
  }
}
