class ChildModel {
  final int id;
  final String firstName;
  final String lastName;
  final String fullName;
  final String? registrationNumber;
  final String? massarCode;
  final String gender;
  final String? dateOfBirth;
  final String? photo;
  final double monthlyTuitionFee;
  final String? classroomName;
  final String? gradeLevel;

  ChildModel({
    required this.id,
    required this.firstName,
    required this.lastName,
    required this.fullName,
    this.registrationNumber,
    this.massarCode,
    this.gender = 'male',
    this.dateOfBirth,
    this.photo,
    this.monthlyTuitionFee = 0.0,
    this.classroomName,
    this.gradeLevel,
  });

  factory ChildModel.fromJson(Map<String, dynamic> json) {
    String? classRoom;
    String? grade;
    if (json['classroom'] != null && json['classroom'] is Map<String, dynamic>) {
      final c = json['classroom'] as Map<String, dynamic>;
      classRoom = c['name'] as String?;
      grade = c['grade_level'] as String?;
    }

    return ChildModel(
      id: json['id'] as int? ?? 0,
      firstName: json['first_name'] as String? ?? '',
      lastName: json['last_name'] as String? ?? '',
      fullName: json['full_name'] as String? ??
          '${json['first_name'] ?? ''} ${json['last_name'] ?? ''}'.trim(),
      registrationNumber: json['registration_number'] as String?,
      massarCode: json['massar_code'] as String?,
      gender: json['gender'] as String? ?? 'male',
      dateOfBirth: json['date_of_birth'] as String?,
      photo: json['photo'] as String?,
      monthlyTuitionFee: (json['monthly_tuition_fee'] as num?)?.toDouble() ?? 0.0,
      classroomName: classRoom,
      gradeLevel: grade,
    );
  }
}
