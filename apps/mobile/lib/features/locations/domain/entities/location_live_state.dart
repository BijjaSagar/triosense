class LocationLiveState {
  const LocationLiveState({
    required this.locationId,
    required this.locationName,
    required this.shortCode,
    required this.mode,
    required this.asOf,
    required this.tokensRemaining,
    required this.queueHead,
    required this.queueTail,
    required this.cutoffPosition,
    required this.status,
    required this.quota,
  });

  final int locationId;
  final String locationName;
  final String shortCode;
  final String mode;
  final DateTime asOf;
  final int tokensRemaining;
  final int queueHead;
  final int queueTail;
  final int? cutoffPosition;
  final String status;
  final int quota;

  factory LocationLiveState.fromJson(Map<String, dynamic> json) {
    return LocationLiveState(
      locationId: json['location_id'] as int,
      locationName: json['location_name'] as String,
      shortCode: json['short_code'] as String,
      mode: json['mode'] as String? ?? 'shadow',
      asOf: DateTime.parse(json['as_of'] as String),
      tokensRemaining: json['tokens_remaining'] as int,
      queueHead: json['queue_head'] as int,
      queueTail: json['queue_tail'] as int,
      cutoffPosition: json['cutoff_position'] as int?,
      status: json['status'] as String,
      quota: json['quota'] as int,
    );
  }

  Map<String, dynamic> toJson() => {
        'location_id': locationId,
        'location_name': locationName,
        'short_code': shortCode,
        'mode': mode,
        'as_of': asOf.toIso8601String(),
        'tokens_remaining': tokensRemaining,
        'queue_head': queueHead,
        'queue_tail': queueTail,
        'cutoff_position': cutoffPosition,
        'status': status,
        'quota': quota,
      };

  bool isStale({int thresholdSeconds = 60}) {
    return DateTime.now().difference(asOf).inSeconds > thresholdSeconds;
  }
}
