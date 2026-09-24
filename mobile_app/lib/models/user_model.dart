class SchoolModel {
  final int id;
  final String name;
  final String? code;
  final String? slug;
  final String? email;
  final String? phone;
  final bool isActive;
  final String? logoUrl;

  SchoolModel({
    required this.id,
    required this.name,
    this.code,
    this.slug,
    this.email,
    this.phone,
    this.isActive = true,
    this.logoUrl,
  });

  factory SchoolModel.fromJson(Map<String, dynamic> json) {
    return SchoolModel(
      id: json['id'] as int? ?? 0,
      name: json['name'] as String? ?? '',
      code: json['code'] as String?,
      slug: json['slug'] as String?,
      email: json['email'] as String?,
      phone: json['phone'] as String?,
      isActive: json['is_active'] as bool? ?? true,
      logoUrl: json['logo_url'] as String?,
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'name': name,
      'code': code,
      'slug': slug,
      'email': email,
      'phone': phone,
      'is_active': isActive,
      'logo_url': logoUrl,
    };
  }
}

class UserModel {
  final int id;
  final String name;
  final String? email;
  final String? phone;
  final String? username;
  final String role; // 'teacher', 'guardian', 'admin', etc.
  final String locale;
  final int? schoolId;
  final int? guardianId;
  final SchoolModel? school;

  UserModel({
    required this.id,
    required this.name,
    this.email,
    this.phone,
    this.username,
    required this.role,
    this.locale = 'ar',
    this.schoolId,
    this.guardianId,
    this.school,
  });

  bool get isTeacher => role == 'teacher';
  bool get isGuardian => role == 'guardian';

  factory UserModel.fromJson(Map<String, dynamic> json) {
    return UserModel(
      id: json['id'] as int? ?? 0,
      name: json['name'] as String? ?? '',
      email: json['email'] as String?,
      phone: json['phone'] as String?,
      username: json['username'] as String?,
      role: json['role'] as String? ?? 'user',
      locale: json['locale'] as String? ?? 'ar',
      schoolId: json['school_id'] as int?,
      guardianId: json['guardian_id'] as int?,
      school: json['school'] != null && json['school'] is Map<String, dynamic>
          ? SchoolModel.fromJson(json['school'] as Map<String, dynamic>)
          : null,
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'name': name,
      'email': email,
      'phone': phone,
      'username': username,
      'role': role,
      'locale': locale,
      'school_id': schoolId,
      'guardian_id': guardianId,
      'school': school?.toJson(),
    };
  }
}
