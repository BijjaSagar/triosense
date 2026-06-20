import 'package:flutter/material.dart';
import 'package:hive_flutter/hive_flutter.dart';
import 'package:triosense/app.dart';
import 'package:triosense/core/di.dart';

Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();
  await Hive.initFlutter();
  await setupDependencies();
  runApp(const TrioSenseApp());
}
