class LocationSummary {
  const LocationSummary({
    required this.locationId,
    required this.name,
    required this.shortCode,
    required this.mode,
    required this.status,
  });

  final int locationId;
  final String name;
  final String shortCode;
  final String mode;
  final String status;

  factory LocationSummary.fromJson(Map<String, dynamic> json) {
    return LocationSummary(
      locationId: json['location_id'] as int,
      name: json['name'] as String,
      shortCode: json['short_code'] as String,
      mode: json['mode'] as String,
      status: json['status'] as String,
    );
  }
}
