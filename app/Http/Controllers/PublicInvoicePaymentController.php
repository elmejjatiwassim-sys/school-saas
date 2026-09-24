<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use Illuminate\View\View;

class PublicInvoicePaymentController extends Controller
{
    /**
     * Display the public invoice payment teaser / coming soon page.
     */
    public function show(string $token): View
    {
        $invoice = Invoice::where('payment_token', $token)
            ->with(['student.classroom.gradeLevel', 'student.guardian', 'school', 'academicYear', 'items'])
            ->firstOrFail();

        return view('public.invoice-payment', [
            'invoice' => $invoice,
            'student' => $invoice->student,
            'school' => $invoice->school,
        ]);
    }
}
