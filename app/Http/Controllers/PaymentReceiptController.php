<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\School;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PaymentReceiptController extends Controller
{
    public function show(Request $request, School $tenant, Payment $payment): Response
    {
        abort_unless((int) $payment->school_id === (int) $tenant->id, 404);

        $payment->load([
            'invoice.items.feeType',
            'invoice.student.guardian',
            'invoice.student.classroom.gradeLevel',
            'invoice.academicYear',
            'school',
        ]);

        $data = [
            'payment' => $payment,
            'invoice' => $payment->invoice,
            'student' => $payment->invoice->student,
            'school' => $payment->school,
        ];

        if ($request->query('format') === 'pdf' || $request->boolean('download')) {
            $pdf = Pdf::loadView('payments.receipt', $data);

            return $pdf->download("Receipt-{$payment->receipt_number}.pdf");
        }

        return response()->view('payments.receipt', $data);
    }
}
