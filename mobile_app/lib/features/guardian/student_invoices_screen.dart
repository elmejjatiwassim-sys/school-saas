import 'dart:io';
import 'package:flutter/material.dart';
import '../../core/api/api_client.dart';
import '../../core/constants/app_colors.dart';
import '../../core/utils/file_helper.dart';
import '../../models/invoice_model.dart';
import '../../models/student_model.dart';
import 'guardian_service.dart';

class StudentInvoicesScreen extends StatefulWidget {
  final ChildModel child;

  const StudentInvoicesScreen({super.key, required this.child});

  @override
  State<StudentInvoicesScreen> createState() => _StudentInvoicesScreenState();
}

class _StudentInvoicesScreenState extends State<StudentInvoicesScreen>
    with SingleTickerProviderStateMixin {
  final GuardianService _guardianService = GuardianService();

  late final TabController _tabController;
  bool _isLoading = true;
  String? _errorMessage;
  GuardianInvoicesResponse? _invoicesData;

  // Track downloading receipt state by payment/invoice ID
  final Map<int, bool> _downloadingMap = {};
  final Map<int, bool> _sharingMap = {};

  @override
  void initState() {
    super.initState();
    _tabController = TabController(length: 2, vsync: this);
    _loadInvoices();
  }

  @override
  void dispose() {
    _tabController.dispose();
    super.dispose();
  }

  Future<void> _loadInvoices() async {
    setState(() {
      _isLoading = true;
      _errorMessage = null;
    });

    try {
      final data =
          await _guardianService.getStudentInvoices(widget.child.id);
      setState(() {
        _invoicesData = data;
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

  Future<void> _handleDownloadReceipt(InvoiceModel invoice) async {
    final downloadUrl = invoice.receiptDownloadUrl;
    final receiptNum = invoice.receiptNumber ?? 'REC-${invoice.id}';

    if (downloadUrl == null || downloadUrl.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('رابط تحميل الوصل غير متوفر حالياً.'),
          backgroundColor: AppColors.danger,
        ),
      );
      return;
    }

    setState(() {
      _downloadingMap[invoice.id] = true;
    });

    try {
      final file = await _guardianService.downloadReceipt(
        downloadUrl: downloadUrl,
        receiptNumber: receiptNum,
      );

      if (!mounted) return;

      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('تم تحميل الوصل بنجاح: ${file.path.split('/').last}'),
          backgroundColor: AppColors.success,
          behavior: SnackBarBehavior.floating,
        ),
      );

      // Open the downloaded PDF
      await FileHelper.openPdfFile(file.path);
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('فشل تحميل الوصل: ${ApiClient.formatError(e)}'),
          backgroundColor: AppColors.danger,
        ),
      );
    } finally {
      if (mounted) {
        setState(() {
          _downloadingMap[invoice.id] = false;
        });
      }
    }
  }

  Future<void> _handleShareReceipt(InvoiceModel invoice) async {
    final downloadUrl = invoice.receiptDownloadUrl;
    final receiptNum = invoice.receiptNumber ?? 'REC-${invoice.id}';

    if (downloadUrl == null || downloadUrl.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('رابط الوصل غير متوفر للمشاركة.'),
          backgroundColor: AppColors.danger,
        ),
      );
      return;
    }

    setState(() {
      _sharingMap[invoice.id] = true;
    });

    try {
      final file = await _guardianService.downloadReceipt(
        downloadUrl: downloadUrl,
        receiptNumber: receiptNum,
      );

      await _guardianService.shareReceipt(
        filePath: file.path,
        receiptNumber: receiptNum,
        studentName: widget.child.fullName,
      );
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('فشل مشاركة الوصل: ${ApiClient.formatError(e)}'),
          backgroundColor: AppColors.danger,
        ),
      );
    } finally {
      if (mounted) {
        setState(() {
          _sharingMap[invoice.id] = false;
        });
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return Directionality(
      textDirection: TextDirection.rtl,
      child: Scaffold(
        backgroundColor: AppColors.background,
        appBar: AppBar(
          title: Text('الواجبات الشهرية: ${widget.child.firstName}'),
          bottom: TabBar(
            controller: _tabController,
            indicatorColor: AppColors.primary,
            labelColor: AppColors.primary,
            unselectedLabelColor: AppColors.textSecondary,
            labelStyle: const TextStyle(fontWeight: FontWeight.bold),
            tabs: [
              Tab(
                text:
                    'أشهر غير مؤداة (${_invoicesData?.pendingInvoices.length ?? 0})',
              ),
              Tab(
                text:
                    'أشهر مؤداة ووصولات (${_invoicesData?.paidInvoices.length ?? 0})',
              ),
            ],
          ),
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
                onPressed: _loadInvoices,
                icon: const Icon(Icons.refresh),
                label: const Text('إعادة المحاولة'),
              ),
            ],
          ),
        ),
      );
    }

    final summary = _invoicesData!.summary;

    return Column(
      children: [
        // Summary Header Card
        Container(
          padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
          color: Colors.white,
          child: Row(
            children: [
              Expanded(
                child: _buildSummaryBox(
                  'المؤدى الإجمالي',
                  '${summary.totalPaid.toStringAsFixed(2)} درهم',
                  AppColors.present,
                  AppColors.presentLight,
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: _buildSummaryBox(
                  'المتبقي في الذمة',
                  '${summary.totalPending.toStringAsFixed(2)} درهم',
                  AppColors.absent,
                  AppColors.absentLight,
                ),
              ),
            ],
          ),
        ),

        // Tabs Content
        Expanded(
          child: TabBarView(
            controller: _tabController,
            children: [
              // Tab 1: Pending Invoices
              _buildInvoicesList(
                invoices: _invoicesData!.pendingInvoices,
                isPaidTab: false,
              ),

              // Tab 2: Paid Invoices & Receipts
              _buildInvoicesList(
                invoices: _invoicesData!.paidInvoices,
                isPaidTab: true,
              ),
            ],
          ),
        ),
      ],
    );
  }

  Widget _buildSummaryBox(
      String title, String amount, Color color, Color bgColor) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
      decoration: BoxDecoration(
        color: bgColor,
        borderRadius: BorderRadius.circular(10),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            title,
            style: TextStyle(
              fontSize: 11,
              fontWeight: FontWeight.w600,
              color: color.withAlpha(200),
            ),
          ),
          const SizedBox(height: 4),
          Text(
            amount,
            style: TextStyle(
              fontSize: 15,
              fontWeight: FontWeight.bold,
              color: color,
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildInvoicesList({
    required List<InvoiceModel> invoices,
    required bool isPaidTab,
  }) {
    if (invoices.isEmpty) {
      return Center(
        child: Padding(
          padding: const EdgeInsets.all(32),
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Icon(
                isPaidTab ? Icons.receipt_long : Icons.verified_rounded,
                size: 56,
                color: isPaidTab ? AppColors.textMuted : AppColors.present,
              ),
              const SizedBox(height: 16),
              Text(
                isPaidTab
                    ? 'لا توجد وصولات أداء سابقة'
                    : 'لا توجد مستحقات مالية معلقة',
                style: const TextStyle(
                  fontSize: 16,
                  fontWeight: FontWeight.bold,
                  color: AppColors.textPrimary,
                ),
              ),
              const SizedBox(height: 6),
              Text(
                isPaidTab
                    ? 'ستظهر هنا جميع وصولات الأداء بمجرد سداد الواجبات الشهرية.'
                    : 'جميع الفواتير والواجبات المدرسية مؤداة بانتظام.',
                textAlign: TextAlign.center,
                style: const TextStyle(
                  fontSize: 13,
                  color: AppColors.textSecondary,
                ),
              ),
            ],
          ),
        ),
      );
    }

    return RefreshIndicator(
      onRefresh: _loadInvoices,
      color: AppColors.primary,
      child: ListView.builder(
        padding: const EdgeInsets.all(16),
        itemCount: invoices.length,
        itemBuilder: (context, index) {
          final invoice = invoices[index];
          return isPaidTab
              ? _buildPaidInvoiceCard(invoice)
              : _buildPendingInvoiceCard(invoice);
        },
      ),
    );
  }

  Widget _buildPendingInvoiceCard(InvoiceModel invoice) {
    return Card(
      margin: const EdgeInsets.only(bottom: 12),
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Text(
                  invoice.title,
                  style: const TextStyle(
                    fontSize: 15,
                    fontWeight: FontWeight.bold,
                    color: AppColors.textPrimary,
                  ),
                ),
                Container(
                  padding:
                      const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                  decoration: BoxDecoration(
                    color: AppColors.lateLight,
                    borderRadius: BorderRadius.circular(6),
                  ),
                  child: const Text(
                    'غير مؤداة',
                    style: TextStyle(
                      color: AppColors.late,
                      fontWeight: FontWeight.bold,
                      fontSize: 11,
                    ),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 8),
            if (invoice.dueDate != null)
              Row(
                children: [
                  const Icon(Icons.event_outlined,
                      size: 15, color: AppColors.textSecondary),
                  const SizedBox(width: 4),
                  Text(
                    'أجل الأداء: ${invoice.dueDate}',
                    style: const TextStyle(
                      fontSize: 12,
                      color: AppColors.textSecondary,
                    ),
                  ),
                ],
              ),
            const Divider(height: 18),
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                const Text(
                  'المبلغ المطلوب:',
                  style: TextStyle(
                    fontSize: 13,
                    color: AppColors.textSecondary,
                  ),
                ),
                Text(
                  '${invoice.remainingAmount.toStringAsFixed(2)} درهم',
                  style: const TextStyle(
                    fontSize: 16,
                    fontWeight: FontWeight.bold,
                    color: AppColors.absent,
                  ),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildPaidInvoiceCard(InvoiceModel invoice) {
    final isDownloading = _downloadingMap[invoice.id] ?? false;
    final isSharing = _sharingMap[invoice.id] ?? false;

    return Card(
      margin: const EdgeInsets.only(bottom: 12),
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // Top row: Title and Receipt Number
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Expanded(
                  child: Text(
                    invoice.title,
                    style: const TextStyle(
                      fontSize: 15,
                      fontWeight: FontWeight.bold,
                      color: AppColors.textPrimary,
                    ),
                  ),
                ),
                Container(
                  padding:
                      const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                  decoration: BoxDecoration(
                    color: AppColors.presentLight,
                    borderRadius: BorderRadius.circular(6),
                  ),
                  child: const Row(
                    children: [
                      Icon(Icons.check_circle,
                          size: 12, color: AppColors.present),
                      SizedBox(width: 4),
                      Text(
                        'مؤداة',
                        style: TextStyle(
                          color: AppColors.present,
                          fontWeight: FontWeight.bold,
                          fontSize: 11,
                        ),
                      ),
                    ],
                  ),
                ),
              ],
            ),
            const SizedBox(height: 6),

            // Receipt Number Badge
            if (invoice.receiptNumber != null) ...[
              Row(
                children: [
                  const Icon(Icons.confirmation_number_outlined,
                      size: 14, color: AppColors.primary),
                  const SizedBox(width: 4),
                  Text(
                    'رقم الوصل الرسمي: ${invoice.receiptNumber}',
                    style: const TextStyle(
                      fontSize: 12,
                      fontWeight: FontWeight.bold,
                      color: AppColors.primary,
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 6),
            ],

            // Amount Paid
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                const Text(
                  'المبلغ المؤدى:',
                  style: TextStyle(
                    fontSize: 13,
                    color: AppColors.textSecondary,
                  ),
                ),
                Text(
                  '${invoice.paidAmount.toStringAsFixed(2)} درهم',
                  style: const TextStyle(
                    fontSize: 16,
                    fontWeight: FontWeight.bold,
                    color: AppColors.present,
                  ),
                ),
              ],
            ),
            const Divider(height: 18),

            // Action Buttons: 📥 Download PDF & 📤 Share via WhatsApp
            Row(
              children: [
                // Download PDF Button
                Expanded(
                  child: ElevatedButton.icon(
                    onPressed: isDownloading
                        ? null
                        : () => _handleDownloadReceipt(invoice),
                    icon: isDownloading
                        ? const SizedBox(
                            width: 16,
                            height: 16,
                            child: CircularProgressIndicator(
                              strokeWidth: 2,
                              color: Colors.white,
                            ),
                          )
                        : const Icon(Icons.download_rounded, size: 18),
                    label: const Text(
                      'تحميل الوصل (PDF)',
                      style: TextStyle(fontSize: 12),
                    ),
                    style: ElevatedButton.styleFrom(
                      backgroundColor: AppColors.primary,
                      minimumSize: const Size.fromHeight(38),
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(8),
                      ),
                    ),
                  ),
                ),
                const SizedBox(width: 8),

                // Share via WhatsApp Button
                Expanded(
                  child: OutlinedButton.icon(
                    onPressed: isSharing
                        ? null
                        : () => _handleShareReceipt(invoice),
                    icon: isSharing
                        ? const SizedBox(
                            width: 16,
                            height: 16,
                            child: CircularProgressIndicator(
                              strokeWidth: 2,
                              color: AppColors.primary,
                            ),
                          )
                        : const Icon(Icons.share_rounded, size: 18),
                    label: const Text(
                      'مشاركة (WhatsApp)',
                      style: TextStyle(fontSize: 12),
                    ),
                    style: OutlinedButton.styleFrom(
                      foregroundColor: AppColors.primary,
                      side: const BorderSide(color: AppColors.primary),
                      minimumSize: const Size.fromHeight(38),
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(8),
                      ),
                    ),
                  ),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}
