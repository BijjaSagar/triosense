import 'package:flutter/material.dart';

class AppTheme {
  static const Color maroon700 = Color(0xFF6D1A2C);
  static const Color gold400 = Color(0xFFD4A437);

  static ThemeData get light => ThemeData(
        colorScheme: ColorScheme.fromSeed(
          seedColor: maroon700,
          primary: maroon700,
          secondary: gold400,
        ),
        scaffoldBackgroundColor: const Color(0xFFFAF8F5),
        appBarTheme: const AppBarTheme(
          backgroundColor: maroon700,
          foregroundColor: Colors.white,
          elevation: 0,
        ),
        useMaterial3: true,
      );
}
