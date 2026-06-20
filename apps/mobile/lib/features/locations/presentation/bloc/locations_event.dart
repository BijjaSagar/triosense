import 'package:equatable/equatable.dart';

sealed class LocationsEvent extends Equatable {
  const LocationsEvent();

  @override
  List<Object?> get props => [];
}

final class LoadLocationsRequested extends LocationsEvent {
  const LoadLocationsRequested();
}
