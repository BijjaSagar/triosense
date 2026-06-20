import 'package:triosense/features/locations/domain/entities/location_live_state.dart';

sealed class LocationStateScreenState {}

final class LocationStateInitial extends LocationStateScreenState {}

final class LocationStateLoading extends LocationStateScreenState {}

final class LocationStateLoaded extends LocationStateScreenState {
  LocationStateLoaded(this.data, {this.isStale = false});

  final LocationLiveState data;
  final bool isStale;
}

final class LocationStateError extends LocationStateScreenState {
  LocationStateError(this.message);

  final String message;
}

final class OverrideApplied extends LocationStateScreenState {
  OverrideApplied(this.data);

  final LocationLiveState data;
}
