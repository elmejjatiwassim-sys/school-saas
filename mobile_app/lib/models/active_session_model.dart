class SessionSlotModel {
  final int timetableId;
  final String subjectName;
  final String classroomName;
  final int classroomId;
  final String startTime;
  final String endTime;
  final String dayOfWeek;

  SessionSlotModel({
    required this.timetableId,
    required this.subjectName,
    required this.classroomName,
    required this.classroomId,
    required this.startTime,
    required this.endTime,
    required this.dayOfWeek,
  });

  factory SessionSlotModel.fromJson(Map<String, dynamic> json) {
    return SessionSlotModel(
      timetableId: json['timetable_id'] as int? ?? 0,
      subjectName: json['subject_name'] as String? ?? '',
      classroomName: json['classroom_name'] as String? ?? '',
      classroomId: json['classroom_id'] as int? ?? 0,
      startTime: json['start_time'] as String? ?? '',
      endTime: json['end_time'] as String? ?? '',
      dayOfWeek: json['day_of_week'] as String? ?? '',
    );
  }
}

class StudentSessionItem {
  final int id;
  final String firstName;
  final String lastName;
  final String fullName;
  final String? photo;
  final String gender;
  final String? registrationNumber;
  String currentStatus; // 'present', 'absent', 'late', 'excused'

  StudentSessionItem({
    required this.id,
    required this.firstName,
    required this.lastName,
    required this.fullName,
    this.photo,
    required this.gender,
    this.registrationNumber,
    this.currentStatus = 'present',
  });

  factory StudentSessionItem.fromJson(Map<String, dynamic> json) {
    return StudentSessionItem(
      id: json['id'] as int? ?? 0,
      firstName: json['first_name'] as String? ?? '',
      lastName: json['last_name'] as String? ?? '',
      fullName: json['full_name'] as String? ??
          '${json['first_name'] ?? ''} ${json['last_name'] ?? ''}'.trim(),
      photo: json['photo'] as String?,
      gender: json['gender'] as String? ?? 'male',
      registrationNumber: json['registration_number'] as String?,
      currentStatus: json['current_status'] as String? ?? 'present',
    );
  }

  Map<String, dynamic> toRecordPayload() {
    return {
      'student_id': id,
      'status': currentStatus,
    };
  }
}

class ActiveSessionResponse {
  final bool hasActiveSession;
  final String? message;
  final SessionSlotModel? session;
  final List<StudentSessionItem> students;

  ActiveSessionResponse({
    required this.hasActiveSession,
    this.message,
    this.session,
    this.students = const [],
  });

  factory ActiveSessionResponse.fromJson(Map<String, dynamic> json) {
    final hasActive = json['has_active_session'] as bool? ?? false;
    SessionSlotModel? sessionSlot;
    if (hasActive && json['session'] != null && json['session'] is Map<String, dynamic>) {
      sessionSlot = SessionSlotModel.fromJson(json['session'] as Map<String, dynamic>);
    }

    final rawStudents = json['students'] as List<dynamic>? ?? [];
    final studentsList = rawStudents
        .whereType<Map<String, dynamic>>()
        .map((s) => StudentSessionItem.fromJson(s))
        .toList();

    return ActiveSessionResponse(
      hasActiveSession: hasActive,
      message: json['message'] as String?,
      session: sessionSlot,
      students: studentsList,
    );
  }
}
