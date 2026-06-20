import 'package:dio/dio.dart';
import 'package:logger/logger.dart';
import 'package:triosense/core/api/api_client.dart';
import 'package:triosense/features/locations/domain/entities/location_summary.dart';

class LocationsRepository {
  LocationsRepository(this._apiClient);

  final ApiClient _apiClient;
  final Logger _log = Logger();

  Future<List<LocationSummary>> fetchLocations() async {
    try {
      final response = await _apiClient.dio.get<Map<String, dynamic>>(
        '/api/v1/locations',
      );

      final body = response.data;
      if (body == null || body['success'] != true) {
        throw Exception('Failed to load locations');
      }

      final data = body['data'] as Map<String, dynamic>;
      final items = data['locations'] as List<dynamic>;

      final locations = items
          .map((item) => LocationSummary.fromJson(item as Map<String, dynamic>))
          .toList();

      _log.i('LocationsRepository.fetchLocations count=${locations.length}');
      return locations;
    } on DioException catch (e) {
      _log.e('LocationsRepository.fetchLocations failed', error: e);
      rethrow;
    }
  }
}
