import 'package:flutter_test/flutter_test.dart';
import 'package:triosense/features/locations/domain/entities/location_summary.dart';

void main() {
  test('LocationSummary parses API payload', () {
    final location = LocationSummary.fromJson({
      'location_id': 1,
      'name': 'Vishnu Nivasam',
      'short_code': 'VSN',
      'mode': 'shadow',
      'status': 'active',
    });

    expect(location.locationId, 1);
    expect(location.shortCode, 'VSN');
    expect(location.name, 'Vishnu Nivasam');
  });
}
