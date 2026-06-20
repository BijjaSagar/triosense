sealed class LocationStateEvent {}

final class LoadLocationStateRequested extends LocationStateEvent {
  LoadLocationStateRequested(this.locationId);

  final int locationId;
}

final class ApplyOverrideRequested extends LocationStateEvent {
  ApplyOverrideRequested({
    required this.action,
    required this.reason,
    this.cutoffPosition,
  });

  final String action;
  final String reason;
  final int? cutoffPosition;
}
