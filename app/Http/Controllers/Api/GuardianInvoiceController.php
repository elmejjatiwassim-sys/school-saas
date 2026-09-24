<?php

namespace App\Http\Controllers\Api;

use App\Enums\InvoiceStatus;
use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Student;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

class GuardianInvoiceController extends Controller
{
    /**
     * Get list of paid and pending invoices for a student.
     */
    public function index(Request $request, int|string $student_id): JsonResponse
    {
        $student = Student::with(['guardian', 'classroom.gradeLevel', 'school'])->findOrFail($student_id);

        $invoices = Invoice::where('student_id', $student->id)
            ->with(['items.feeType', 'payments', 'school', 'academicYear'])
            ->orderBy('due_date', 'asc')
            ->get();

        $formatted = $invoices->map(function (Invoice $invoice) {
            $isPaid = $invoice->status === InvoiceStatus::Paid || (float) $invoice->remaining_amount <= 0;
            $latestPayment = $invoice->payments->sortByDesc('id')->first();

            $receiptDownloadUrl = null;
            if ($latestPayment) {
                $receiptDownloadUrl = route('api.v1.guardian.receipt.download', ['payment' => $latestPayment->id]);
            }

            return [
                'id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'title' => $invoice->title,
                'invoice_type' => $invoice->invoice_type?->value ?? (string) $invoice->invoice_type,
                'billing_month' => $invoice->billing_month,
                'billing_year' => $invoice->billing_year,
                'total_amount' => (float) $invoice->total_amount,
                'paid_amount' => (float) $invoice->paid_amount,
                'remaining_amount' => (float) $invoice->remaining_amount,
                'due_date' => $invoice->due_date?->format('Y-m-d'),
                'status' => $invoice->status instanceof InvoiceStatus ? $invoice->status->value : (string) $invoice->status,
                'is_paid' => $isPaid,
                'receipt_number' => $latestPayment?->receipt_number,
                'receipt_download_url' => $isPaid && $receiptDownloadUrl ? $receiptDownloadUrl : $receiptDownloadUrl,
                'items' => $invoice->items->map(fn ($item) => [
                    'id' => $item->id,
                    'description' => $item->description,
                    'fee_type' => $item->feeType?->name,
                    'amount' => (float) $item->amount,
                    'quantity' => (int) $item->quantity,
                    'subtotal' => (float) $item->subtotal,
                ])->values()->all(),
            ];
        });

        $paidInvoices = $formatted->filter(fn ($inv) => $inv['is_paid'])->values()->all();
        $pendingInvoices = $formatted->filter(fn ($inv) => ! $inv['is_paid'])->values()->all();

        return response()->json([
            'success' => true,
            'student' => [
                'id' => $student->id,
                'first_name' => $student->first_name,
                'last_name' => $student->last_name,
                'full_name' => "{$student->first_name} {$student->last_name}",
                'registration_number' => $student->registration_number,
                'monthly_tuition_fee' => (float) $student->monthly_tuition_fee,
                'classroom' => $student->classroom?->name,
            ],
            'invoices' => $formatted->values()->all(),
            'paid_invoices' => $paidInvoices,
            'pending_invoices' => $pendingInvoices,
            'summary' => [
                'total_invoices' => $formatted->count(),
                'paid_count' => count($paidInvoices),
                'pending_count' => count($pendingInvoices),
                'total_amount' => (float) $formatted->sum('total_amount'),
                'total_paid' => (float) $formatted->sum('paid_amount'),
                'total_pending' => (float) $formatted->sum('remaining_amount'),
            ],
        ]);
    }

    /**
     * Download receipt PDF for a payment.
     */
    public function downloadReceipt(Request $request, Payment $payment): Response
    {
        $user = $request->user();
        if (! $user && $bearerToken = $request->bearerToken()) {
            $user = PersonalAccessToken::findToken($bearerToken)?->tokenable;
        }

        $payment->load([
            'invoice.items.feeType',
            'invoice.student.guardian',
            'invoice.student.classroom.gradeLevel',
            'invoice.academicYear',
            'school',
        ]);

        // Security authorization check: if user is authenticated guardian, ensure payment belongs to their child
        if ($user && $user->role === 'guardian') {
            $guardian = $user->getGuardianRecord();
            $studentGuardianId = $payment->invoice?->student?->guardian_id;
            if ($guardian && $studentGuardianId && (int) $studentGuardianId !== (int) $guardian->id) {
                abort(403, 'غير مصرح لك بتحميل هذا الوصل (Unauthorized access to receipt).');
            }
        }

        $data = [
            'payment' => $payment,
            'invoice' => $payment->invoice,
            'student' => $payment->invoice?->student,
            'school' => $payment->school,
        ];

        $pdf = Pdf::loadView('payments.receipt', $data);

        return $pdf->download("Receipt-{$payment->receipt_number}.pdf");
    }
}
