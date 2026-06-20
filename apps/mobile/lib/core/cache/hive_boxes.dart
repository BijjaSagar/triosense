import 'package:hive_flutter/hive_flutter.dart';

class HiveBoxes {
  static const String authBoxName = 'auth_box';
  static const String locationsBoxName = 'locations_box';

  static Future<void> init() async {
    await Hive.openBox<String>(authBoxName);
    await Hive.openBox<String>(locationsBoxName);
  }

  static Box<String> get auth => Hive.box<String>(authBoxName);
  static Box<String> get locations => Hive.box<String>(locationsBoxName);
}
