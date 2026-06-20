import 'package:dio/dio.dart';
import 'package:hive/hive.dart';
import 'package:logger/logger.dart';
import 'package:triosense/core/api/api_client.dart';
import 'package:triosense/core/cache/hive_boxes.dart';
import 'package:triosense/features/locations/domain/entities/location_live_state.dart';

class LocationStateRepository {
  LocationStateRepository(this._apiClient);

  final ApiClient _apiClient;
  final Logger _log = Logger();

  Future<LocationLiveState> fetchState(int locationId) async {
    final cacheKey = 'state_$locationId';
    final box = HiveBoxes.locationsBox;

    try {
      final response = await _apiClient.dio.get<Map<String, dynamic>>(
        '/api/v1/locations/$locationId/state',
      );

      final body = response.data;
      if (body == null || body['success'] != true) {
        throw Exception('Failed to load location state');
      }

      final state = LocationLiveState.fromJson(
        body['data'] as Map<String, dynamic>,
      );

      await box.put(cacheKey, state.toJson());
      _log.i('LocationStateRepository.fetchState locationId=$locationId');
      return state;
    } on DioException catch (e) {
      _log.w('LocationStateRepository.network_failed using cache', error: e);
      final cached = box.get(cacheKey);
      if (cached is Map) {
        return LocationLiveState.fromJson(Map<String, dynamic>.from(cached));
      }
      rethrow;
    }
  }

  LocationLiveState? readCached(int locationId) {
    final cached = HiveBoxes.locationsBox.get('state_$locationId');
    if (cached is Map) {
      return LocationLiveState.fromJson(Map<String, dynamic>.from(cached));
    }
    return null;
  }

  Future<void> applyOverride({
    required int locationId,
    required String action,
    required String reason,
    int? cutoffPosition,
  }) async {
    await _apiClient.dio.post<Map<String, dynamic>>(
      '/api/v1/locations/$locationId/cutoff/override',
      data: {
        'action': action,
        'reason': reason,
        if (cutoffPosition != null) 'cutoff_position': cutoffPosition,
      },
    );
    _log.i('LocationStateRepository.applyOverride action=$action');
  }

  Future<void> registerFcmToken(String token) async {
    await _apiClient.dio.post<Map<String, dynamic>>(
      '/api/v1/users/me/fcm-token',
      data: {'fcm_token': token},
    );
    _log.i('LocationStateRepository.registerFcmToken');
  }
}
