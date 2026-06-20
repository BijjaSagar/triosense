# TrioSense Mobile

Flutter 3.24 supervisor app for TTD's Executive Officer and shift staff.

## Quick start

```bash
flutter pub get
flutter pub run build_runner build --delete-conflicting-outputs   # generate injectable + hive
flutter run                                  # to attached device or emulator
flutter test
flutter analyze
```

## What it does

- Live view of all three TTD counter locations
- Push notifications via FCM when:
  - A counter reaches APPROACHING_CUTOFF or CUTOFF_DECLARED
  - An edge device is offline > 2 minutes
  - Another supervisor applies a manual override
- Override controls (gated by `location_supervisor` role)
- Offline-first reads via Hive cache, with stale-data badge

## Architecture

Feature-first BLoC. See [`.cursor/rules/03-mobile-flutter.mdc`](../../.cursor/rules/03-mobile-flutter.mdc).

```
lib/
├── main.dart
├── app.dart
├── core/
└── features/
    ├── auth/
    ├── locations/
    └── overrides/
```

## Read before editing

- [`../../CLAUDE.md`](../../CLAUDE.md)
- [`../../API_CONTRACTS.md`](../../API_CONTRACTS.md) for REST + WebSocket contracts
