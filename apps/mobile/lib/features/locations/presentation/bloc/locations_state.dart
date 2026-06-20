import 'package:equatable/equatable.dart';
import 'package:triosense/features/locations/domain/entities/location_summary.dart';

sealed class LocationsState extends Equatable {
  const LocationsState();

  @override
  List<Object?> get props => [];
}

final class LocationsInitial extends LocationsState {
  const LocationsInitial();
}

final class LocationsLoading extends LocationsState {
  const LocationsLoading();
}

final class LocationsLoaded extends LocationsState {
  const LocationsLoaded(this.locations);

  final List<LocationSummary> locations;

  @override
  List<Object?> get props => [locations];
}

final class LocationsError extends LocationsState {
  const LocationsError(this.message);

  final String message;

  @override
  List<Object?> get props => [message];
}
