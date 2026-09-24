import '../../core/api/api_client.dart';
import '../../core/api/api_constants.dart';
import '../../core/storage/secure_storage_service.dart';
import '../../models/user_model.dart';

class AuthService {
  final ApiClient _api = ApiClient();
  final SecureStorageService _storage = SecureStorageService();

  Future<UserModel> login({
    required String identifier,
    required String password,
  }) async {
    final response = await _api.post(
      ApiConstants.login,
      data: {
        'login': identifier.trim(),
        'password': password,
      },
    );

    final data = response.data as Map<String, dynamic>;
    final token = data['token'] as String;
    final userJson = data['user'] as Map<String, dynamic>;

    final user = UserModel.fromJson(userJson);

    // Securely cache session
    await _storage.saveAuthSession(
      token: token,
      role: user.role,
      user: userJson,
    );

    return user;
  }

  Future<void> logout() async {
    try {
      await _api.post(ApiConstants.logout);
    } catch (_) {
      // Ignore network errors on logout to allow offline local clear
    } finally {
      await _storage.clear();
    }
  }

  Future<UserModel?> getMe() async {
    try {
      final response = await _api.get(ApiConstants.me);
      final data = response.data as Map<String, dynamic>;
      final userJson = data['user'] as Map<String, dynamic>;
      return UserModel.fromJson(userJson);
    } catch (_) {
      return null;
    }
  }
}
