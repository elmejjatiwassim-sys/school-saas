import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'core/api/api_client.dart';
import 'core/storage/secure_storage_service.dart';
import 'core/theme/app_theme.dart';
import 'features/auth/login_screen.dart';
import 'features/guardian/guardian_home_screen.dart';
import 'features/teacher/teacher_session_screen.dart';

void main() async {
  WidgetsFlutterBinding.ensureInitialized();

  final storage = SecureStorageService();
  final isLoggedIn = await storage.isLoggedIn();
  final role = await storage.getRole();

  runApp(SchoolMobileApp(
    isLoggedIn: isLoggedIn,
    userRole: role,
  ));
}

class SchoolMobileApp extends StatelessWidget {
  final bool isLoggedIn;
  final String? userRole;

  const SchoolMobileApp({
    super.key,
    required this.isLoggedIn,
    this.userRole,
  });

  Widget _determineInitialScreen() {
    if (!isLoggedIn) {
      return const LoginScreen();
    }

    if (userRole == 'teacher') {
      return const TeacherSessionScreen();
    } else {
      return const GuardianHomeScreen();
    }
  }

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'بوابة المدرسة الذكية',
      debugShowCheckedModeBanner: false,
      navigatorKey: ApiClient.navigatorKey,
      theme: AppTheme.lightTheme,
      locale: const Locale('ar', 'MA'),
      supportedLocales: const [
        Locale('ar', 'MA'),
        Locale('ar'),
        Locale('fr'),
        Locale('en'),
      ],
      localizationsDelegates: const [
        GlobalMaterialLocalizations.delegate,
        GlobalWidgetsLocalizations.delegate,
        GlobalCupertinoLocalizations.delegate,
      ],
      home: _determineInitialScreen(),
      routes: {
        '/login': (context) => const LoginScreen(),
        '/teacher': (context) => const TeacherSessionScreen(),
        '/guardian': (context) => const GuardianHomeScreen(),
      },
    );
  }
}
