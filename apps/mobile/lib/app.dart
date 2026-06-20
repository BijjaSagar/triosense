import 'package:flutter/material.dart';
import 'package:triosense/core/routing/app_router.dart';
import 'package:triosense/core/theme/app_theme.dart';

class TrioSenseApp extends StatelessWidget {
  const TrioSenseApp({super.key});

  @override
  Widget build(BuildContext context) {
    return MaterialApp.router(
      title: 'TrioSense',
      theme: AppTheme.light,
      routerConfig: appRouter,
      debugShowCheckedModeBanner: false,
    );
  }
}
