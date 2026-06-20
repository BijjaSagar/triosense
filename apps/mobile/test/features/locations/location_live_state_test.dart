import 'package:flutter_test/flutter_test.dart';
import 'package:triosense/features/locations/domain/entities/location_live_state.dart';

void main() {
  test('LocationLiveState.isStale returns true after threshold', () {
    final state = LocationLiveState(
      locationId: 3,
      locationName: 'Bhudevi Complex',
      shortCode: 'BDV',
      mode: 'shadow',
      asOf: DateTime.now().subtract(const Duration(seconds: 90)),
      tokensRemaining: 100,
      queueHead: 1,
      queueTail: 50,
      cutoffPosition: null,
      status: 'open',
      quota: 5000,
    );

    expect(state.isStale(thresholdSeconds: 60), isTrue);
  });

  test('LocationLiveState.fromJson parses api payload', () {
    final state = LocationLiveState.fromJson({
      'location_id': 3,
      'location_name': 'Bhudevi Complex',
      'short_code': 'BDV',
      'mode': 'shadow',
      'as_of': '2026-06-20T06:00:00Z',
      'tokens_remaining': 1160,
      'queue_head': 3841,
      'queue_tail': 5210,
      'cutoff_position': 5000,
      'status': 'cutoff_declared',
      'quota': 5000,
    });

    expect(state.locationId, 3);
    expect(state.status, 'cutoff_declared');
    expect(state.cutoffPosition, 5000);
  });
}
