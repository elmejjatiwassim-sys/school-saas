import 'dart:convert';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';

class SecureStorageService {
  static final SecureStorageService _instance = SecureStorageService._internal();
  factory SecureStorageService() => _instance;
  SecureStorageService._internal();

  final FlutterSecureStorage _storage = const FlutterSecureStorage(
    aOptions: AndroidOptions(encryptedSharedPreferences: true),
    iOptions: IOSOptions(accessibility: KeychainAccessibility.first_unlock),
  );

  static const String _keyToken = 'auth_token';
  static const String _keyRole = 'user_role';
  static const String _keyUser = 'user_profile';

  Future<void> saveAuthSession({
    required String token,
    required String role,
    required Map<String, dynamic> user,
  }) async {
    await _storage.write(key: _keyToken, value: token);
    await _storage.write(key: _keyRole, value: role);
    await _storage.write(key: _keyUser, value: jsonEncode(user));
  }

  Future<String?> getToken() async {
    return await _storage.read(key: _keyToken);
  }

  Future<String?> getRole() async {
    return await _storage.read(key: _keyRole);
  }

  Future<Map<String, dynamic>?> getUserProfile() async {
    final raw = await _storage.read(key: _keyUser);
    if (raw == null) return null;
    try {
      return jsonDecode(raw) as Map<String, dynamic>;
    } catch (_) {
      return null;
    }
  }

  Future<bool> isLoggedIn() async {
    final token = await getToken();
    return token != null && token.isNotEmpty;
  }

  Future<void> clear() async {
    await _storage.deleteAll();
  }
}
