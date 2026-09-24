import 'package:flutter/material.dart';
import '../../core/api/api_client.dart';
import '../../core/constants/app_colors.dart';
import '../../models/active_session_model.dart';
import '../auth/auth_service.dart';
import '../auth/login_screen.dart';
import 'teacher_service.dart';

class TeacherSessionScreen extends StatefulWidget {
  const TeacherSessionScreen({super.key});

  @override
  State<TeacherSessionScreen> createState() => _TeacherSessionScreenState();
}

class _TeacherSessionScreenState extends State<TeacherSessionScreen> {
  final TeacherService _teacherService = TeacherService();
  final AuthService _authService = AuthService();

  bool _isLoading = true;
  bool _isSubmitting = false;
  String? _errorMessage;

  ActiveSessionResponse? _sessionData;

  @override
  void initState() {
    super.initState();
    _loadActiveSession();
  }

  Future<void> _loadActiveSession() async {
    setState(() {
      _isLoading = true;
      _errorMessage = null;
    });

    try {
      final data = await _teacherService.getActiveSession();
      setState(() {
        _sessionData = data;
      });
    } catch (e) {
      setState(() {
        _errorMessage = ApiClient.formatError(e);
      });
    } finally {
      if (mounted) {
        setState(() {
          _isLoading = false;
        });
      }
    }
  }

  Future<void> _submitAttendance() async {
    final session = _sessionData?.session;
    final students = _sessionData?.students;

    if (session == null || students == null || students.isEmpty) return;

    setState(() {
      _isSubmitting = true;
    });

    try {
      final result = await _teacherService.submitBulkAttendance(
        timetableId: session.timetableId,
        students: students,
      );

      if (!mounted) return;

      final count = result['recorded_count'] ?? students.length;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            'تم تسجيل وتأكيد غياب $count تلميذ بنجاح وإرسال الإشعارات لأولياء الأمور.',
            style: const TextStyle(fontWeight: FontWeight.bold),
          ),
          backgroundColor: AppColors.success,
          behavior: SnackBarBehavior.floating,
        ),
      );
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(ApiClient.formatError(e)),
          backgroundColor: AppColors.danger,
          behavior: SnackBarBehavior.floating,
        ),
      );
    } finally {
      if (mounted) {
        setState(() {
          _isSubmitting = false;
        });
      }
    }
  }

  void _markAllPresent() {
    if (_sessionData == null) return;
    setState(() {
      for (var student in _sessionData!.students) {
        student.currentStatus = 'present';
      }
    });
  }

  Future<void> _handleLogout() async {
    await _authService.logout();
    if (!mounted) return;
    Navigator.of(context).pushReplacement(
      MaterialPageRoute(builder: (_) => const LoginScreen()),
    );
  }

  int get _presentCount =>
      _sessionData?.students.where((s) => s.currentStatus == 'present').length ??
      0;
  int get _absentCount =>
      _sessionData?.students.where((s) => s.currentStatus == 'absent').length ??
      0;
  int get _lateCount =>
      _sessionData?.students.where((s) => s.currentStatus == 'late').length ?? 0;

  @override
  Widget build(BuildContext context) {
    return Directionality(
      textDirection: TextDirection.rtl,
      child: Scaffold(
        backgroundColor: AppColors.background,
        appBar: AppBar(
          title: const Text('تسجيل الغياب السريع (حصة نشطة)'),
          actions: [
            IconButton(
              icon: const Icon(Icons.refresh_rounded),
              tooltip: 'تحديث الحصة',
              onPressed: _isLoading ? null : _loadActiveSession,
            ),
            IconButton(
              icon: const Icon(Icons.logout_rounded),
              tooltip: 'تسجيل الخروج',
              onPressed: _handleLogout,
            ),
          ],
        ),
        body: _buildBody(),
        bottomNavigationBar:
            (_sessionData != null && _sessionData!.hasActiveSession)
                ? _buildBottomBar()
                : null,
      ),
    );
  }

  Widget _buildBody() {
    if (_isLoading) {
      return const Center(
        child: CircularProgressIndicator(color: AppColors.primary),
      );
    }

    if (_errorMessage != null) {
      return Center(
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              const Icon(Icons.error_outline_rounded,
                  size: 56, color: AppColors.danger),
              const SizedBox(height: 16),
              Text(
                _errorMessage!,
                textAlign: TextAlign.center,
                style: const TextStyle(
                    fontSize: 15, color: AppColors.textPrimary),
              ),
              const SizedBox(height: 20),
              ElevatedButton.icon(
                onPressed: _loadActiveSession,
                icon: const Icon(Icons.refresh),
                label: const Text('إعادة المحاولة'),
              ),
            ],
          ),
        ),
      );
    }

    if (_sessionData == null || !_sessionData!.hasActiveSession) {
      return _buildEmptyActiveSessionCard();
    }

    final session = _sessionData!.session!;
    final students = _sessionData!.students;

    return RefreshIndicator(
      onRefresh: _loadActiveSession,
      color: AppColors.primary,
      child: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          // Active Slot Header Card
          _buildSessionHeaderCard(session),
          const SizedBox(height: 14),

          // Fast Summary Counters & Mark All Action
          _buildCounterRow(students.length),
          const SizedBox(height: 16),

          // Students List Header
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Text(
                'لائحة التلاميذ (${students.length})',
                style: const TextStyle(
                  fontSize: 16,
                  fontWeight: FontWeight.bold,
                  color: AppColors.textPrimary,
                ),
              ),
              TextButton.icon(
                onPressed: _markAllPresent,
                icon: const Icon(Icons.done_all,
                    size: 18, color: AppColors.present),
                label: const Text(
                  'تعليم الكل كحاضر',
                  style: TextStyle(
                    fontSize: 13,
                    color: AppColors.present,
                    fontWeight: FontWeight.bold,
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 8),

          // Students Attendance Items
          ...students.map((student) => _buildStudentCard(student)),
          const SizedBox(height: 80), // Padding for bottom floating bar
        ],
      ),
    );
  }

  Widget _buildEmptyActiveSessionCard() {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(32),
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Container(
              width: 90,
              height: 90,
              decoration: BoxDecoration(
                color: AppColors.primary.withAlpha(20),
                shape: BoxShape.circle,
              ),
              child: const Icon(
                Icons.event_busy_rounded,
                size: 50,
                color: AppColors.primary,
              ),
            ),
            const SizedBox(height: 20),
            const Text(
              'لا توجد حصة دراسية نشطة حالياً',
              style: TextStyle(
                fontSize: 18,
                fontWeight: FontWeight.bold,
                color: AppColors.textPrimary,
              ),
            ),
            const SizedBox(height: 10),
            Text(
              _sessionData?.message ??
                  'يتم الكشف تلقائياً عن جدول الحصص الخاص بك حسب الوقت الحالي واليوم.',
              textAlign: TextAlign.center,
              style: const TextStyle(
                fontSize: 14,
                color: AppColors.textSecondary,
                height: 1.4,
              ),
            ),
            const SizedBox(height: 24),
            SizedBox(
              width: 180,
              child: ElevatedButton.icon(
                onPressed: _loadActiveSession,
                icon: const Icon(Icons.refresh_rounded),
                label: const Text('تحديث الآن'),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildSessionHeaderCard(SessionSlotModel session) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        gradient: const LinearGradient(
          colors: [AppColors.primary, AppColors.primaryDark],
          begin: Alignment.topRight,
          end: Alignment.bottomLeft,
        ),
        borderRadius: BorderRadius.circular(16),
        boxShadow: [
          BoxShadow(
            color: AppColors.primary.withAlpha(40),
            blurRadius: 10,
            offset: const Offset(0, 4),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Container(
                padding:
                    const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                decoration: BoxDecoration(
                  color: Colors.white.withAlpha(40),
                  borderRadius: BorderRadius.circular(8),
                ),
                child: Row(
                  children: [
                    const Icon(Icons.class_outlined,
                        color: Colors.white, size: 16),
                    const SizedBox(width: 6),
                    Text(
                      session.classroomName,
                      style: const TextStyle(
                        color: Colors.white,
                        fontWeight: FontWeight.bold,
                        fontSize: 13,
                      ),
                    ),
                  ],
                ),
              ),
              Container(
                padding:
                    const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                decoration: BoxDecoration(
                  color: Colors.white.withAlpha(40),
                  borderRadius: BorderRadius.circular(8),
                ),
                child: Row(
                  children: [
                    const Icon(Icons.access_time_rounded,
                        color: Colors.white, size: 16),
                    const SizedBox(width: 6),
                    Text(
                      '${session.startTime} - ${session.endTime}',
                      style: const TextStyle(
                        color: Colors.white,
                        fontWeight: FontWeight.bold,
                        fontSize: 13,
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),
          Text(
            session.subjectName,
            style: const TextStyle(
              fontSize: 20,
              fontWeight: FontWeight.bold,
              color: Colors.white,
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildCounterRow(int total) {
    return Row(
      children: [
        _buildCounterChip('حاضر', _presentCount, AppColors.present,
            AppColors.presentLight),
        const SizedBox(width: 8),
        _buildCounterChip(
            'غائب', _absentCount, AppColors.absent, AppColors.absentLight),
        const SizedBox(width: 8),
        _buildCounterChip(
            'متأخر', _lateCount, AppColors.late, AppColors.lateLight),
        const SizedBox(width: 8),
        _buildCounterChip('المجموع', total, AppColors.textPrimary,
            AppColors.cardBorder.withAlpha(120)),
      ],
    );
  }

  Widget _buildCounterChip(
      String label, int count, Color color, Color bgColor) {
    return Expanded(
      child: Container(
        padding: const EdgeInsets.symmetric(vertical: 8),
        decoration: BoxDecoration(
          color: bgColor,
          borderRadius: BorderRadius.circular(10),
        ),
        child: Column(
          children: [
            Text(
              '$count',
              style: TextStyle(
                fontSize: 16,
                fontWeight: FontWeight.bold,
                color: color,
              ),
            ),
            const SizedBox(height: 2),
            Text(
              label,
              style: TextStyle(
                fontSize: 11,
                color: color.withAlpha(200),
                fontWeight: FontWeight.w600,
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildStudentCard(StudentSessionItem student) {
    return Card(
      margin: const EdgeInsets.only(bottom: 10),
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
        child: Row(
          children: [
            // Student Avatar
            CircleAvatar(
              radius: 20,
              backgroundColor: AppColors.primary.withAlpha(20),
              backgroundImage:
                  student.photo != null ? NetworkImage(student.photo!) : null,
              child: student.photo == null
                  ? Text(
                      student.firstName.isNotEmpty
                          ? student.firstName[0].toUpperCase()
                          : 'ت',
                      style: const TextStyle(
                        color: AppColors.primary,
                        fontWeight: FontWeight.bold,
                      ),
                    )
                  : null,
            ),
            const SizedBox(width: 12),

            // Student Info
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    student.fullName,
                    style: const TextStyle(
                      fontSize: 14,
                      fontWeight: FontWeight.bold,
                      color: AppColors.textPrimary,
                    ),
                  ),
                  if (student.registrationNumber != null) ...[
                    const SizedBox(height: 2),
                    Text(
                      student.registrationNumber!,
                      style: const TextStyle(
                        fontSize: 12,
                        color: AppColors.textSecondary,
                      ),
                    ),
                  ],
                ],
              ),
            ),

            // Fast Toggle Buttons (حاضر / غائب / متأخر)
            _buildStatusToggleChip(student, 'present', 'حاضر', AppColors.present,
                AppColors.presentLight),
            const SizedBox(width: 6),
            _buildStatusToggleChip(student, 'absent', 'غائب', AppColors.absent,
                AppColors.absentLight),
            const SizedBox(width: 6),
            _buildStatusToggleChip(student, 'late', 'متأخر', AppColors.late,
                AppColors.lateLight),
          ],
        ),
      ),
    );
  }

  Widget _buildStatusToggleChip(
    StudentSessionItem student,
    String targetStatus,
    String label,
    Color activeColor,
    Color activeBgColor,
  ) {
    final isSelected = student.currentStatus == targetStatus;

    return InkWell(
      onTap: () {
        setState(() {
          student.currentStatus = targetStatus;
        });
      },
      borderRadius: BorderRadius.circular(8),
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 150),
        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
        decoration: BoxDecoration(
          color: isSelected ? activeColor : Colors.transparent,
          borderRadius: BorderRadius.circular(8),
          border: Border.Border.all(
            color: isSelected ? activeColor : AppColors.cardBorder,
            width: 1.2,
          ),
        ),
        child: Text(
          label,
          style: TextStyle(
            fontSize: 12,
            fontWeight: FontWeight.bold,
            color: isSelected ? Colors.white : AppColors.textSecondary,
          ),
        ),
      ),
    );
  }

  Widget _buildBottomBar() {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.white,
        boxShadow: [
          BoxShadow(
            color: Colors.black.withAlpha(15),
            blurRadius: 10,
            offset: const Offset(0, -4),
          ),
        ],
      ),
      child: SafeArea(
        child: ElevatedButton.icon(
          onPressed: _isSubmitting ? null : _submitAttendance,
          icon: _isSubmitting
              ? const SizedBox(
                  width: 20,
                  height: 20,
                  child: CircularProgressIndicator(
                    strokeWidth: 2,
                    color: Colors.white,
                  ),
                )
              : const Icon(Icons.send_rounded, color: Colors.white),
          label: Text(
            _isSubmitting
                ? 'جاري إرسال الغياب...'
                : 'تأكيد وإرسال الغياب (${_absentCount} غائب)',
            style: const TextStyle(fontSize: 16, fontWeight: FontWeight.bold),
          ),
          style: ElevatedButton.styleFrom(
            backgroundColor:
                _absentCount > 0 ? AppColors.primary : AppColors.present,
            minimumSize: const Size.fromHeight(50),
          ),
        ),
      ),
    );
  }
}
