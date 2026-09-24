import 'dart:io';
import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import '../../core/api/api_client.dart';
import '../../core/constants/app_colors.dart';
import '../../models/attendance_model.dart';
import '../../models/student_model.dart';
import 'guardian_service.dart';

class StudentAttendanceScreen extends StatefulWidget {
  final ChildModel child;

  const StudentAttendanceScreen({super.key, required this.child});

  @override
  State<StudentAttendanceScreen> createState() =>
      _StudentAttendanceScreenState();
}

class _StudentAttendanceScreenState extends State<StudentAttendanceScreen> {
  final GuardianService _guardianService = GuardianService();

  bool _isLoading = true;
  String? _errorMessage;
  List<AttendanceRecordModel> _records = [];
  int _currentPage = 1;
  bool _hasMore = false;
  bool _isLoadingMore = false;

  @override
  void initState() {
    super.initState();
    _loadAttendances();
  }

  Future<void> _loadAttendances({bool refresh = false}) async {
    if (refresh) {
      _currentPage = 1;
    }

    setState(() {
      if (_currentPage == 1) _isLoading = true;
      _errorMessage = null;
    });

    try {
      final result = await _guardianService.getStudentAttendances(
        studentId: widget.child.id,
        page: _currentPage,
      );

      setState(() {
        if (_currentPage == 1) {
          _records = result.records;
        } else {
          _records.addAll(result.records);
        }
        _hasMore = result.hasMore;
      });
    } catch (e) {
      setState(() {
        _errorMessage = ApiClient.formatError(e);
      });
    } finally {
      if (mounted) {
        setState(() {
          _isLoading = false;
          _isLoadingMore = false;
        });
      }
    }
  }

  void _openJustificationSheet(AttendanceRecordModel record) {
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (ctx) => _JustificationBottomSheet(
        record: record,
        childName: widget.child.fullName,
        onSuccess: () {
          Navigator.pop(ctx);
          _loadAttendances(refresh: true);
          ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(
              content: Text(
                'تم إرسال تبرير الغياب بنجاح، وسيتم مراجعته من طرف الإدارة.',
                style: TextStyle(fontWeight: FontWeight.bold),
              ),
              backgroundColor: AppColors.success,
              behavior: SnackBarBehavior.floating,
            ),
          );
        },
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Directionality(
      textDirection: TextDirection.rtl,
      child: Scaffold(
        backgroundColor: AppColors.background,
        appBar: AppBar(
          title: Text('غياب وحضور: ${widget.child.firstName}'),
        ),
        body: _buildBody(),
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
                style: const TextStyle(fontSize: 14),
              ),
              const SizedBox(height: 16),
              ElevatedButton.icon(
                onPressed: () => _loadAttendances(refresh: true),
                icon: const Icon(Icons.refresh),
                label: const Text('إعادة المحاولة'),
              ),
            ],
          ),
        ),
      );
    }

    if (_records.isEmpty) {
      return RefreshIndicator(
        onRefresh: () => _loadAttendances(refresh: true),
        color: AppColors.primary,
        child: Center(
          child: ListView(
            shrinkWrap: true,
            padding: const EdgeInsets.all(32),
            children: const [
              Icon(Icons.event_available_rounded,
                  size: 64, color: AppColors.present),
              SizedBox(height: 16),
              Text(
                'سجل الحضور ممتاز',
                textAlign: TextAlign.center,
                style: TextStyle(
                  fontSize: 18,
                  fontWeight: FontWeight.bold,
                  color: AppColors.textPrimary,
                ),
              ),
              SizedBox(height: 8),
              Text(
                'لم يتم تسجيل أي غياب أو تأخر لهذا التلميذ حتى الآن.',
                textAlign: TextAlign.center,
                style: TextStyle(fontSize: 14, color: AppColors.textSecondary),
              ),
            ],
          ),
        ),
      );
    }

    return RefreshIndicator(
      onRefresh: () => _loadAttendances(refresh: true),
      color: AppColors.primary,
      child: ListView.builder(
        padding: const EdgeInsets.all(16),
        itemCount: _records.length + (_hasMore ? 1 : 0),
        itemBuilder: (context, index) {
          if (index == _records.length) {
            return Center(
              child: Padding(
                padding: const EdgeInsets.all(16),
                child: ElevatedButton(
                  onPressed: _isLoadingMore
                      ? null
                      : () {
                          setState(() {
                            _isLoadingMore = true;
                            _currentPage++;
                          });
                          _loadAttendances();
                        },
                  child: _isLoadingMore
                      ? const SizedBox(
                          width: 20,
                          height: 20,
                          child: CircularProgressIndicator(strokeWidth: 2),
                        )
                      : const Text('تحميل المزيد من السجلات'),
                ),
              ),
            );
          }

          final record = _records[index];
          return _buildAttendanceCard(record);
        },
      ),
    );
  }

  Widget _buildAttendanceCard(AttendanceRecordModel record) {
    Color statusColor;
    Color statusBgColor;
    String statusLabel;
    IconData statusIcon;

    if (record.isAbsent) {
      statusColor = AppColors.absent;
      statusBgColor = AppColors.absentLight;
      statusLabel = 'غياب';
      statusIcon = Icons.cancel_outlined;
    } else if (record.isLate) {
      statusColor = AppColors.late;
      statusBgColor = AppColors.lateLight;
      statusLabel = 'تأخر';
      statusIcon = Icons.schedule_rounded;
    } else if (record.isExcused) {
      statusColor = AppColors.excused;
      statusBgColor = AppColors.excusedLight;
      statusLabel = 'غياب مبرر';
      statusIcon = Icons.check_circle_outline;
    } else {
      statusColor = AppColors.present;
      statusBgColor = AppColors.presentLight;
      statusLabel = 'حضور';
      statusIcon = Icons.check_circle_rounded;
    }

    return Card(
      margin: const EdgeInsets.only(bottom: 12),
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // Top Row: Date & Status Badge
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Row(
                  children: [
                    const Icon(Icons.calendar_today_rounded,
                        size: 16, color: AppColors.textSecondary),
                    const SizedBox(width: 6),
                    Text(
                      record.date,
                      style: const TextStyle(
                        fontWeight: FontWeight.bold,
                        fontSize: 14,
                        color: AppColors.textPrimary,
                      ),
                    ),
                    if (record.sessionTime != null) ...[
                      const SizedBox(width: 8),
                      Text(
                        '(${record.sessionTime})',
                        style: const TextStyle(
                          fontSize: 12,
                          color: AppColors.textSecondary,
                        ),
                      ),
                    ],
                  ],
                ),
                Container(
                  padding:
                      const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                  decoration: BoxDecoration(
                    color: statusBgColor,
                    borderRadius: BorderRadius.circular(20),
                  ),
                  child: Row(
                    children: [
                      Icon(statusIcon, size: 14, color: statusColor),
                      const SizedBox(width: 4),
                      Text(
                        statusLabel,
                        style: TextStyle(
                          color: statusColor,
                          fontWeight: FontWeight.bold,
                          fontSize: 12,
                        ),
                      ),
                    ],
                  ),
                ),
              ],
            ),
            const Divider(height: 20, thickness: 0.8),

            // Subject & Teacher details
            Row(
              children: [
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        record.subjectName ?? 'المادة الدراسية',
                        style: const TextStyle(
                          fontSize: 15,
                          fontWeight: FontWeight.bold,
                          color: AppColors.textPrimary,
                        ),
                      ),
                      if (record.teacherName != null) ...[
                        const SizedBox(height: 2),
                        Text(
                          'الأستاذ(ة): ${record.teacherName}',
                          style: const TextStyle(
                            fontSize: 13,
                            color: AppColors.textSecondary,
                          ),
                        ),
                      ],
                    ],
                  ),
                ),
                if (record.lateArrivalTime != null)
                  Container(
                    padding: const EdgeInsets.symmetric(
                        horizontal: 8, vertical: 4),
                    decoration: BoxDecoration(
                      color: AppColors.lateLight,
                      borderRadius: BorderRadius.circular(6),
                    ),
                    child: Text(
                      'وصل: ${record.lateArrivalTime}',
                      style: const TextStyle(
                        fontSize: 12,
                        fontWeight: FontWeight.bold,
                        color: AppColors.late,
                      ),
                    ),
                  ),
              ],
            ),

            // Existing Justification Display
            if (record.hasJustification) ...[
              const SizedBox(height: 12),
              Container(
                width: double.infinity,
                padding: const EdgeInsets.all(10),
                decoration: BoxDecoration(
                  color: AppColors.background,
                  borderRadius: BorderRadius.circular(8),
                  border: Border.Border.all(color: AppColors.cardBorder),
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        const Text(
                          'التبرير المقدم:',
                          style: TextStyle(
                            fontWeight: FontWeight.bold,
                            fontSize: 12,
                            color: AppColors.textPrimary,
                          ),
                        ),
                        _buildJustificationStatusBadge(
                            record.justificationStatus),
                      ],
                    ),
                    const SizedBox(height: 4),
                    Text(
                      record.guardianJustificationNote!,
                      style: const TextStyle(
                        fontSize: 13,
                        color: AppColors.textSecondary,
                      ),
                    ),
                  ],
                ),
              ),
            ],

            // Submit Justification Button for unexcused absences
            if (record.canSubmitJustification && !record.hasJustification) ...[
              const SizedBox(height: 12),
              OutlinedButton.icon(
                onPressed: () => _openJustificationSheet(record),
                icon: const Icon(Icons.note_alt_outlined, size: 18),
                label: const Text('تقديم تبرير (Submit Justification)'),
                style: OutlinedButton.styleFrom(
                  foregroundColor: AppColors.primary,
                  side: const BorderSide(color: AppColors.primary),
                  minimumSize: const Size.fromHeight(40),
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(8),
                  ),
                ),
              ),
            ],
          ],
        ),
      ),
    );
  }

  Widget _buildJustificationStatusBadge(String? status) {
    Color color;
    String label;

    switch (status) {
      case 'approved':
        color = AppColors.present;
        label = 'تم قبول التبرير';
        break;
      case 'rejected':
        color = AppColors.absent;
        label = 'تم رفض التبرير';
        break;
      default:
        color = AppColors.late;
        label = 'قيد المراجعة';
    }

    return Text(
      label,
      style: TextStyle(
        fontSize: 11,
        fontWeight: FontWeight.bold,
        color: color,
      ),
    );
  }
}

class _JustificationBottomSheet extends StatefulWidget {
  final AttendanceRecordModel record;
  final String childName;
  final VoidCallback onSuccess;

  const _JustificationBottomSheet({
    required this.record,
    required this.childName,
    required this.onSuccess,
  });

  @override
  State<_JustificationBottomSheet> createState() =>
      _JustificationBottomSheetState();
}

class _JustificationBottomSheetState extends State<_JustificationBottomSheet> {
  final _reasonController = TextEditingController();
  final GuardianService _service = GuardianService();

  File? _pickedFile;
  String? _pickedFileName;
  bool _isSubmitting = false;
  String? _error;

  @override
  void dispose() {
    _reasonController.dispose();
    super.dispose();
  }

  Future<void> _pickAttachment() async {
    final result = await FilePicker.platform.pickFiles(
      type: FileType.custom,
      allowedExtensions: ['jpg', 'jpeg', 'png', 'pdf'],
    );

    if (result != null && result.files.single.path != null) {
      setState(() {
        _pickedFile = File(result.files.single.path!);
        _pickedFileName = result.files.single.name;
      });
    }
  }

  Future<void> _submit() async {
    final text = _reasonController.text.trim();
    if (text.isEmpty) {
      setState(() {
        _error = 'يرجى كتابة سبب أو تفاصيل تبرير الغياب.';
      });
      return;
    }

    setState(() {
      _isSubmitting = true;
      _error = null;
    });

    try {
      await _service.submitJustification(
        attendanceId: widget.record.id,
        reason: text,
        attachment: _pickedFile,
      );
      widget.onSuccess();
    } catch (e) {
      setState(() {
        _error = ApiClient.formatError(e);
      });
    } finally {
      if (mounted) {
        setState(() {
          _isSubmitting = false;
        });
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return Directionality(
      textDirection: TextDirection.rtl,
      child: Container(
        padding: EdgeInsets.only(
          left: 20,
          right: 20,
          top: 20,
          bottom: MediaQuery.of(context).viewInsets.bottom + 20,
        ),
        decoration: const BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
        ),
        child: SingleChildScrollView(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              // Header
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  const Text(
                    'تقديم تبرير غياب رسمي',
                    style: TextStyle(
                      fontSize: 18,
                      fontWeight: FontWeight.bold,
                      color: AppColors.textPrimary,
                    ),
                  ),
                  IconButton(
                    icon: const Icon(Icons.close),
                    onPressed: () => Navigator.pop(context),
                  ),
                ],
              ),
              const SizedBox(height: 6),
              Text(
                'التلميذ: ${widget.childName} | التاريخ: ${widget.record.date}',
                style: const TextStyle(
                  fontSize: 13,
                  color: AppColors.textSecondary,
                ),
              ),
              const SizedBox(height: 16),

              if (_error != null) ...[
                Container(
                  padding: const EdgeInsets.all(10),
                  decoration: BoxDecoration(
                    color: AppColors.absentLight,
                    borderRadius: BorderRadius.circular(8),
                  ),
                  child: Text(
                    _error!,
                    style: const TextStyle(
                        color: AppColors.danger, fontSize: 13),
                  ),
                ),
                const SizedBox(height: 12),
              ],

              // Reason Input
              TextField(
                controller: _reasonController,
                maxLines: 3,
                decoration: const InputDecoration(
                  labelText: 'سبب الغياب / الملاحظات',
                  hintText: 'مثال: وعكة صحية، شهادة طبية، ظرف عائلي طارئ...',
                  alignLabelWithHint: true,
                ),
              ),
              const SizedBox(height: 14),

              // File Attachment Picker
              InkWell(
                onTap: _pickAttachment,
                borderRadius: BorderRadius.circular(10),
                child: Container(
                  padding: const EdgeInsets.symmetric(
                      horizontal: 14, vertical: 12),
                  decoration: BoxDecoration(
                    color: AppColors.background,
                    borderRadius: BorderRadius.circular(10),
                    border: Border.Border.all(
                      color: AppColors.cardBorder,
                      style: BorderStyle.solid,
                    ),
                  ),
                  child: Row(
                    children: [
                      const Icon(Icons.attach_file_rounded,
                          color: AppColors.primary),
                      const SizedBox(width: 10),
                      Expanded(
                        child: Text(
                          _pickedFileName ??
                              'إرفاق وثيقة أو شهادة طبية (اختياري - PDF/صورة)',
                          style: TextStyle(
                            fontSize: 13,
                            color: _pickedFileName != null
                                ? AppColors.textPrimary
                                : AppColors.textSecondary,
                            fontWeight: _pickedFileName != null
                                ? FontWeight.bold
                                : FontWeight.normal,
                          ),
                          overflow: TextOverflow.ellipsis,
                        ),
                      ),
                      if (_pickedFile != null)
                        IconButton(
                          icon: const Icon(Icons.delete_outline,
                              color: AppColors.danger, size: 20),
                          onPressed: () {
                            setState(() {
                              _pickedFile = null;
                              _pickedFileName = null;
                            });
                          },
                        ),
                    ],
                  ),
                ),
              ),
              const SizedBox(height: 20),

              // Submit Button
              ElevatedButton(
                onPressed: _isSubmitting ? null : _submit,
                child: _isSubmitting
                    ? const SizedBox(
                        width: 20,
                        height: 20,
                        child: CircularProgressIndicator(
                          strokeWidth: 2,
                          color: Colors.white,
                        ),
                      )
                    : const Text('إرسال التبرير إلى الإدارة'),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
