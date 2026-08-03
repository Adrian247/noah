import 'package:flutter/material.dart';

abstract final class PhoenixTheme {
  static const amber = Color(0xFFF59E0B);
  static const _darkSurface = Color(0xFF0F172A);
  static const _darkCard = Color(0xFF1E293B);
  static const _lightSurface = Color(0xFFF8FAFC);
  static const _lightCard = Color(0xFFFFFFFF);

  static ThemeData get dark {
    final base = ThemeData(
      useMaterial3: true,
      brightness: Brightness.dark,
      colorScheme: const ColorScheme.dark(
        primary: amber,
        secondary: Color(0xFFFBBF24),
        surface: _darkSurface,
        onSurface: Color(0xFFF1F5F9),
        error: Color(0xFFF87171),
      ),
      scaffoldBackgroundColor: _darkSurface,
      cardColor: _darkCard,
      appBarTheme: const AppBarTheme(
        backgroundColor: _darkSurface,
        foregroundColor: Color(0xFFF1F5F9),
        elevation: 0,
      ),
      inputDecorationTheme: InputDecorationTheme(
        filled: true,
        fillColor: const Color(0xFF334155),
        border: OutlineInputBorder(
          borderRadius: BorderRadius.circular(12),
          borderSide: BorderSide.none,
        ),
        focusedBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(12),
          borderSide: const BorderSide(color: amber, width: 1.5),
        ),
      ),
      elevatedButtonTheme: ElevatedButtonThemeData(
        style: ElevatedButton.styleFrom(
          backgroundColor: amber,
          foregroundColor: const Color(0xFF0F172A),
          padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 14),
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
        ),
      ),
      navigationBarTheme: const NavigationBarThemeData(
        backgroundColor: _darkCard,
        indicatorColor: Color(0x33F59E0B),
        labelTextStyle: WidgetStatePropertyAll(TextStyle(fontSize: 12)),
      ),
    );

    return base;
  }

  static ThemeData get light {
    final base = ThemeData(
      useMaterial3: true,
      brightness: Brightness.light,
      colorScheme: const ColorScheme.light(
        primary: Color(0xFFD97706),
        secondary: amber,
        surface: _lightSurface,
        onSurface: Color(0xFF0F172A),
        error: Color(0xFFDC2626),
      ),
      scaffoldBackgroundColor: _lightSurface,
      cardColor: _lightCard,
      appBarTheme: const AppBarTheme(
        backgroundColor: _lightCard,
        foregroundColor: Color(0xFF0F172A),
        elevation: 0,
        surfaceTintColor: Colors.transparent,
      ),
      cardTheme: CardThemeData(
        color: _lightCard,
        elevation: 0,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(14),
          side: const BorderSide(color: Color(0xFFE2E8F0)),
        ),
      ),
      inputDecorationTheme: InputDecorationTheme(
        filled: true,
        fillColor: const Color(0xFFF1F5F9),
        border: OutlineInputBorder(
          borderRadius: BorderRadius.circular(12),
          borderSide: BorderSide.none,
        ),
        focusedBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(12),
          borderSide: const BorderSide(color: Color(0xFFD97706), width: 1.5),
        ),
      ),
      elevatedButtonTheme: ElevatedButtonThemeData(
        style: ElevatedButton.styleFrom(
          backgroundColor: const Color(0xFFD97706),
          foregroundColor: Colors.white,
          padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 14),
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
        ),
      ),
      navigationBarTheme: const NavigationBarThemeData(
        backgroundColor: _lightCard,
        indicatorColor: Color(0x33D97706),
        labelTextStyle: WidgetStatePropertyAll(TextStyle(fontSize: 12)),
      ),
    );

    return base;
  }
}
