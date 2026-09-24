<?php

namespace App\Filament\Pages;

use App\Enums\AiActionLogStatus;
use App\Models\AiActionLog;
use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\School;
use App\Services\AiCopilotService;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Collection;

class AiCopilot extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-sparkles';

    protected static ?string $navigationGroup = 'AI & Automation';

    protected static ?int $navigationSort = 1;

    protected static ?string $title = 'AI Copilot (المساعد الذكي)';

    protected static string $view = 'filament.pages.ai-copilot';

    public ?int $activeConversationId = null;

    public string $prompt = '';

    public function mount(): void
    {
        $school = Filament::getTenant();
        $user = auth()->user();

        if ($school && $user) {
            $latest = AiConversation::where('school_id', $school->id)
                ->where('user_id', $user->id)
                ->where('is_archived', false)
                ->latest()
                ->first();

            if (! $latest) {
                $latest = AiConversation::create([
                    'school_id' => $school->id,
                    'user_id' => $user->id,
                    'title' => 'Admin Session - '.now()->format('M d, H:i'),
                ]);
            }

            $this->activeConversationId = $latest->id;
        }
    }

    public function sendMessage(AiCopilotService $copilotService): void
    {
        $promptText = trim($this->prompt);
        if (blank($promptText)) {
            return;
        }

        $school = Filament::getTenant();
        $user = auth()->user();

        if (! $school || ! $user || ! $this->activeConversationId) {
            Notification::make()->title('Session expired or tenant not set')->danger()->send();

            return;
        }

        // Quota Enforcement
        $remaining = $this->remainingAiMessages;
        if ($remaining <= 0) {
            Notification::make()
                ->title('تم استنفاد كوطة رسائل المساعد الذكي لهذا الشهر. سيتجدد الرصيد تلقائياً بداية الشهر القادم.')
                ->danger()
                ->send();

            return;
        }

        $conversation = AiConversation::find($this->activeConversationId);
        if (! $conversation) {
            $conversation = AiConversation::create([
                'school_id' => $school->id,
                'user_id' => $user->id,
                'title' => mb_substr($promptText, 0, 40),
            ]);
            $this->activeConversationId = $conversation->id;
        }

        // 1. Record user message
        AiMessage::create([
            'conversation_id' => $conversation->id,
            'sender' => 'user',
            'message' => $promptText,
        ]);

        $this->prompt = '';

        // Increment monthly message usage
        $school->increment('ai_messages_used_this_month');
        $school->refresh();

        // 2. Process with AI Copilot Service (generates proposal or chat response)
        $response = $copilotService->processPrompt($promptText, $school, $user);

        // 3. Record assistant message
        AiMessage::create([
            'conversation_id' => $conversation->id,
            'sender' => 'assistant',
            'message' => $response['message'],
            'action_log_id' => $response['action_log']?->id,
        ]);
    }

    public function confirmAction(?int $actionLogId = null, ?AiCopilotService $copilotService = null): void
    {
        $copilotService ??= app(AiCopilotService::class);
        $user = auth()->user();

        if ($actionLogId) {
            $log = AiActionLog::findOrFail($actionLogId);
        } else {
            $log = AiActionLog::where('school_id', Filament::getTenant()?->id)
                ->where('status', AiActionLogStatus::PendingConfirmation)
                ->latest()
                ->firstOrFail();
        }

        try {
            $result = $copilotService->executeAction($log, $user);

            // Record confirmation in chat
            if ($this->activeConversationId) {
                AiMessage::create([
                    'conversation_id' => $this->activeConversationId,
                    'sender' => 'system',
                    'message' => '✅ '.$result['message'],
                ]);
            }

            unset($this->messages);
            $this->dispatch('$refresh');

            Notification::make()
                ->title('Action Executed (تم التنفيذ بنجاح)')
                ->body($result['message'])
                ->success()
                ->send();
        } catch (\Exception $e) {
            Notification::make()
                ->title('Execution Failed')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function cancelAction(?int $actionLogId = null, ?AiCopilotService $copilotService = null): void
    {
        $copilotService ??= app(AiCopilotService::class);
        $user = auth()->user();

        if ($actionLogId) {
            $log = AiActionLog::findOrFail($actionLogId);
        } else {
            $log = AiActionLog::where('school_id', Filament::getTenant()?->id)
                ->where('status', AiActionLogStatus::PendingConfirmation)
                ->latest()
                ->firstOrFail();
        }

        $copilotService->cancelAction($log, $user);

        if ($this->activeConversationId) {
            AiMessage::create([
                'conversation_id' => $this->activeConversationId,
                'sender' => 'system',
                'message' => '❌ Action was cancelled by administrator (تم إلغاء الإجراء دون تعديل البيانات).',
            ]);
        }

        unset($this->messages);
        $this->dispatch('$refresh');

        Notification::make()
            ->title('Action Cancelled (تم الإلغاء)')
            ->body('Action has been safely cancelled without making changes.')
            ->info()
            ->send();
    }

    public function startNewConversation(): void
    {
        $school = Filament::getTenant();
        $user = auth()->user();

        if ($school && $user) {
            $conversation = AiConversation::create([
                'school_id' => $school->id,
                'user_id' => $user->id,
                'title' => 'Admin Session - '.now()->format('M d, H:i'),
            ]);
            $this->activeConversationId = $conversation->id;
        }
    }

    public function selectConversation(int $id): void
    {
        $this->activeConversationId = $id;
    }

    /**
     * @return Collection<int, AiMessage>
     */
    public function getMessagesProperty(): Collection
    {
        if (! $this->activeConversationId) {
            return collect();
        }

        return AiMessage::where('conversation_id', $this->activeConversationId)
            ->with(['actionLog'])
            ->orderBy('id', 'asc')
            ->get();
    }

    /**
     * @return Collection<int, AiConversation>
     */
    public function getConversationsProperty(): Collection
    {
        $school = Filament::getTenant();
        $user = auth()->user();

        if (! $school || ! $user) {
            return collect();
        }

        return AiConversation::where('school_id', $school->id)
            ->where('user_id', $user->id)
            ->orderByDesc('updated_at')
            ->take(15)
            ->get();
    }

    public function getSchoolProperty(): ?School
    {
        return Filament::getTenant();
    }

    public function getAiQuotaProperty(): int
    {
        return (int) ($this->school?->ai_monthly_messages_quota ?? 50);
    }

    public function getAiUsedThisMonthProperty(): int
    {
        return (int) ($this->school?->ai_messages_used_this_month ?? 0);
    }

    public function getRemainingAiMessagesProperty(): int
    {
        if (! $this->school) {
            return 0;
        }

        return max(0, $this->aiQuota - $this->aiUsedThisMonth);
    }
}
