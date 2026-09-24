<?php

namespace App\Notifications;

use App\Models\Invoice;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class InvoiceReminderNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Invoice $invoice,
        public string $reminderMessage,
        public string $type = 'initial'
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $title = $this->type === 'initial'
            ? 'إشعار واجب التمدرس الشهري / Monthly Tuition Notice'
            : 'تذكير ودي بأداء واجب التمدرس / Tuition Payment Reminder';

        return [
            'type' => 'invoice_reminder',
            'reminder_type' => $this->type,
            'title' => $title,
            'message' => $this->reminderMessage,
            'invoice_id' => $this->invoice->id,
            'invoice_number' => $this->invoice->invoice_number,
            'total_amount' => (float) $this->invoice->total_amount,
            'paid_amount' => (float) $this->invoice->paid_amount,
            'remaining_amount' => (float) ($this->invoice->total_amount - $this->invoice->paid_amount),
            'billing_month' => $this->invoice->billing_month,
            'billing_year' => $this->invoice->billing_year,
            'due_date' => $this->invoice->due_date?->toDateString() ?? (string) $this->invoice->due_date,
        ];
    }
}
