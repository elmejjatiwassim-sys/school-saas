<x-filament-panels::page>
    <div class="grid grid-cols-1 lg:grid-cols-4 gap-6 min-h-[680px]">
        {{-- Left Sidebar: Conversations History --}}
        <div class="lg:col-span-1 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl p-4 flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between mb-4 pb-3 border-b border-gray-100 dark:border-gray-800">
                    <span class="font-semibold text-sm text-gray-800 dark:text-gray-200">
                        💬 Sessions (الجلسات)
                    </span>
                    <button
                        type="button"
                        wire:click="startNewConversation"
                        class="inline-flex items-center px-2.5 py-1 text-xs font-semibold rounded-lg bg-primary-50 text-primary-700 hover:bg-primary-100 dark:bg-primary-950 dark:text-primary-300"
                    >
                        + New Chat
                    </button>
                </div>

                <div class="space-y-1.5 overflow-y-auto max-h-[500px]">
                    @forelse($this->conversations as $conv)
                        <button
                            type="button"
                            wire:click="selectConversation({{ $conv->id }})"
                            class="w-full text-left px-3 py-2 text-xs rounded-lg transition truncate flex items-center justify-between {{ $conv->id === $activeConversationId ? 'bg-primary-500 text-white font-semibold' : 'text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800' }}"
                        >
                            <span class="truncate">{{ $conv->title ?? 'Session #'.$conv->id }}</span>
                            @if($conv->is_archived)
                                <span class="text-[10px] px-1.5 py-0.5 rounded bg-gray-200 text-gray-700 dark:bg-gray-700 dark:text-gray-300">Archived</span>
                            @endif
                        </button>
                    @empty
                        <p class="text-xs text-gray-400 py-4 text-center">No previous sessions.</p>
                    @endforelse
                </div>
            </div>

            <div class="pt-4 border-t border-gray-100 dark:border-gray-800 text-[11px] text-gray-400 flex items-center gap-1.5">
                <span class="inline-block w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                <span>Two-step confirmation active</span>
            </div>
        </div>

        {{-- Main Chat Area --}}
        <div class="lg:col-span-3 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl flex flex-col justify-between overflow-hidden">
            {{-- Chat Header --}}
            <div class="p-4 border-b border-gray-200 dark:border-gray-800 flex items-center justify-between bg-gray-50/50 dark:bg-gray-800/40">
                <div class="flex items-center gap-2.5">
                    <div class="p-2 rounded-lg bg-primary-100 text-primary-700 dark:bg-primary-900/60 dark:text-primary-300">
                        <x-filament::icon icon="heroicon-o-sparkles" class="w-5 h-5" />
                    </div>
                    <div>
                        <h2 class="text-sm font-semibold text-gray-900 dark:text-white">Admin AI Copilot</h2>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Natural language management with safe human-in-the-loop audit confirmation</p>
                    </div>
                </div>

                {{-- Monthly AI Quota Badge --}}
                <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full text-xs font-semibold {{ $this->remainingAiMessages > 0 ? 'bg-primary-50 text-primary-700 border border-primary-200 dark:bg-primary-950 dark:text-primary-300 dark:border-primary-800' : 'bg-red-50 text-red-700 border border-red-200 dark:bg-red-950 dark:text-red-300 dark:border-red-800' }}">
                    <span>⚡</span>
                    <span>الرصيد المتبقي: {{ $this->remainingAiMessages }} / {{ $this->aiQuota }} رسالة لهذا الشهر</span>
                </div>
            </div>

            {{-- Message Scroll Stream --}}
            <div class="p-6 overflow-y-auto space-y-4 flex-1 max-h-[520px]">
                @forelse($this->messages as $msg)
                    @if($msg->sender === 'user')
                        {{-- User Message --}}
                        <div class="flex justify-end">
                            <div class="max-w-[75%] rounded-2xl rounded-tr-none px-4 py-3 bg-primary-600 text-white text-sm shadow-sm">
                                <p class="whitespace-pre-wrap">{{ $msg->message }}</p>
                                <span class="block text-[10px] text-primary-200 text-right mt-1">{{ $msg->created_at->format('H:i') }}</span>
                            </div>
                        </div>
                    @elseif($msg->sender === 'assistant')
                        {{-- Assistant Message --}}
                        <div class="flex justify-start items-start gap-3">
                            <div class="w-8 h-8 rounded-full bg-primary-100 text-primary-700 dark:bg-primary-900 dark:text-primary-300 flex items-center justify-center shrink-0 text-xs font-bold">
                                AI
                            </div>
                            <div class="max-w-[85%] space-y-3">
                                <div class="rounded-2xl rounded-tl-none px-4 py-3 bg-gray-100 text-gray-900 dark:bg-gray-800 dark:text-gray-100 text-sm shadow-sm">
                                    <p class="whitespace-pre-wrap">{{ $msg->message }}</p>
                                    <span class="block text-[10px] text-gray-400 text-right mt-1">{{ $msg->created_at->format('H:i') }}</span>
                                </div>

                                {{-- Confirmation Card --}}
                                @if($msg->actionLog)
                                    @php
                                        $log = $msg->actionLog;
                                        $payload = $log->payload ?? [];
                                        $isPending = $log->status === \App\Enums\AiActionLogStatus::PendingConfirmation;
                                        $isExecuted = $log->status === \App\Enums\AiActionLogStatus::Executed;
                                        $isCancelled = $log->status === \App\Enums\AiActionLogStatus::Cancelled;
                                    @endphp

                                    <div class="rounded-xl border p-4 shadow-sm transition {{ $isPending ? 'border-amber-300 bg-amber-50/50 dark:bg-amber-950/20 dark:border-amber-700/60' : ($isExecuted ? 'border-emerald-300 bg-emerald-50/40 dark:bg-emerald-950/20 dark:border-emerald-800' : 'border-gray-300 bg-gray-50/50 dark:bg-gray-800/40 dark:border-gray-700') }}">
                                        {{-- Card Header --}}
                                        <div class="flex items-center justify-between pb-2 border-b border-gray-200 dark:border-gray-700/60">
                                            <div class="flex items-center gap-2">
                                                <span class="text-base">⚡</span>
                                                <span class="font-bold text-xs uppercase tracking-wide text-gray-800 dark:text-gray-200">
                                                    Action Proposal: {{ str_replace('_', ' ', $log->action_type) }}
                                                </span>
                                            </div>
                                            <span class="px-2 py-0.5 rounded-full text-[11px] font-semibold {{ $isPending ? 'bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-200' : ($isExecuted ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900 dark:text-emerald-200' : 'bg-rose-100 text-rose-800 dark:bg-rose-900 dark:text-rose-200') }}">
                                                {{ $log->status->getLabel() }}
                                            </span>
                                        </div>

                                        {{-- Extracted Payload Table --}}
                                        <div class="py-3 text-xs space-y-3">
                                            <div class="grid grid-cols-2 gap-2 text-gray-700 dark:text-gray-300">
                                                @foreach($payload as $k => $v)
                                                    @if($k !== 'details')
                                                        <div class="flex justify-between border-b border-gray-200/60 dark:border-gray-700/40 pb-1">
                                                            <span class="font-medium text-gray-500 dark:text-gray-400 capitalize">{{ str_replace('_', ' ', $k) }}:</span>
                                                            <span class="font-semibold text-gray-900 dark:text-white">{{ is_array($v) ? implode(', ', $v) : $v }}</span>
                                                        </div>
                                                    @endif
                                                @endforeach
                                            </div>

                                            @if(!empty($payload['details']) && is_array($payload['details']))
                                                <div class="pt-2 border-t border-gray-200 dark:border-gray-700">
                                                    <div class="font-bold text-gray-800 dark:text-gray-200 mb-1.5">قائمة التلاميذ الغائبين وأولياء أمورهم:</div>
                                                    <div class="space-y-1 max-h-40 overflow-y-auto pr-1">
                                                        @foreach($payload['details'] as $item)
                                                            <div class="flex items-center justify-between p-1.5 rounded bg-white/70 dark:bg-gray-800/80 border border-gray-200/50 dark:border-gray-700/50 text-[11px]">
                                                                <span class="font-semibold text-gray-900 dark:text-white">{{ $item['student_name'] ?? 'Student' }} ({{ $item['classroom'] ?? '-' }})</span>
                                                                <span class="text-gray-600 dark:text-gray-400 font-mono">{{ $item['guardian_name'] ?? '' }} - {{ $item['guardian_phone'] ?? '' }}</span>
                                                                <span class="px-1.5 py-0.5 rounded text-[10px] bg-amber-100 text-amber-800 dark:bg-amber-900/60 dark:text-amber-200">{{ $item['guardian_account'] ?? 'SMS' }}</span>
                                                            </div>
                                                        @endforeach
                                                    </div>
                                                </div>
                                            @endif
                                        </div>

                                        {{-- Action Buttons --}}
                                        @if($isPending)
                                            <div class="pt-2 flex items-center justify-end gap-2 border-t border-gray-200 dark:border-gray-700/60">
                                                <button
                                                    type="button"
                                                    wire:click="cancelAction({{ $log->id }})"
                                                    class="inline-flex items-center px-3 py-1.5 rounded-lg text-xs font-semibold text-gray-600 bg-white border border-gray-300 hover:bg-gray-100 dark:bg-gray-800 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-700 transition"
                                                >
                                                    ✗ Cancel (إلغاء)
                                                </button>
                                                <button
                                                    type="button"
                                                    wire:click="confirmAction"
                                                    class="inline-flex items-center px-4 py-1.5 rounded-lg text-xs font-semibold text-white bg-emerald-600 hover:bg-emerald-700 shadow-sm transition"
                                                >
                                                    تأكيد العملية / Confirm Action
                                                </button>
                                            </div>
                                        @elseif($isExecuted)
                                            <div class="pt-2 text-[11px] text-emerald-700 dark:text-emerald-400 flex items-center gap-1.5 font-medium">
                                                <span>✓ Executed by {{ $log->user?->name ?? 'Admin' }} on {{ $log->confirmed_at?->format('d/m/Y H:i') }}</span>
                                            </div>
                                        @elseif($isCancelled)
                                            <div class="pt-2 text-[11px] text-gray-500 dark:text-gray-400 flex items-center gap-1.5">
                                                <span>✗ Cancelled by administrator without database mutations.</span>
                                            </div>
                                        @endif
                                    </div>
                                @endif
                            </div>
                        </div>
                    @elseif($msg->sender === 'system')
                        {{-- System notification --}}
                        <div class="flex justify-center my-2">
                            <span class="px-3 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-300 border border-gray-200 dark:border-gray-700">
                                {{ $msg->message }}
                            </span>
                        </div>
                    @endif
                @empty
                    <div class="text-center py-16 text-gray-400">
                        <div class="text-3xl mb-2">🤖</div>
                        <p class="text-sm font-medium">How can I assist you with school operations today?</p>
                        <p class="text-xs text-gray-400 mt-1">Try one of the quick commands below to see the two-step confirmation flow in action.</p>
                    </div>
                @endforelse
            </div>

            {{-- Bottom Input & Suggestions --}}
            <div class="p-4 bg-gray-50 border-t border-gray-200 dark:bg-gray-800/80 dark:border-gray-700 space-y-3">
                {{-- Quick Suggestions --}}
                <div class="flex flex-wrap items-center gap-2">
                    <button
                        type="button"
                        wire:click="$set('prompt', 'Register student Omar Berrada in CP-A phone 0612345678')"
                        class="px-2.5 py-1 text-xs rounded-full bg-white border border-gray-200 text-gray-700 hover:bg-gray-100 dark:bg-gray-800 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-700"
                    >
                        ➕ Register Omar Berrada in CP-A
                    </button>
                    <button
                        type="button"
                        wire:click="$set('prompt', 'Mark Omar Berrada absent today in morning session')"
                        class="px-2.5 py-1 text-xs rounded-full bg-white border border-gray-200 text-gray-700 hover:bg-gray-100 dark:bg-gray-800 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-700"
                    >
                        📅 Mark Omar absent today
                    </button>
                    <button
                        type="button"
                        wire:click="$set('prompt', 'Generate 10 months tuition invoices for classroom CP-A')"
                        class="px-2.5 py-1 text-xs rounded-full bg-white border border-gray-200 text-gray-700 hover:bg-gray-100 dark:bg-gray-800 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-700"
                    >
                        💰 Generate invoices for CP-A
                    </button>
                </div>

                {{-- Quota Exhausted Notice --}}
                @if($this->remainingAiMessages <= 0)
                    <div class="p-3 rounded-lg bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800 text-amber-800 dark:text-amber-200 text-xs flex items-center gap-2 font-medium">
                        <span class="text-base">⚠️</span>
                        <span>تم استنفاد كوطة رسائل المساعد الذكي لهذا الشهر. سيتجدد الرصيد تلقائياً بداية الشهر القادم.</span>
                    </div>
                @endif

                {{-- Input Row --}}
                <form wire:submit.prevent="sendMessage" class="flex gap-2">
                    <input
                        type="text"
                        wire:model="prompt"
                        @disabled($this->remainingAiMessages <= 0)
                        placeholder="{{ $this->remainingAiMessages <= 0 ? 'تم استنفاد كوطة رسائل المساعد الذكي لهذا الشهر...' : 'Type an administrative instruction (e.g. \'Register student Sarah Alaoui in CM1\')...' }}"
                        class="flex-1 rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 text-sm dark:bg-gray-900 dark:border-gray-700 dark:text-white disabled:opacity-50 disabled:bg-gray-100 dark:disabled:bg-gray-800"
                    />
                    <button
                        type="submit"
                        @disabled($this->remainingAiMessages <= 0)
                        wire:loading.attr="disabled"
                        class="inline-flex items-center gap-1 px-5 py-2 rounded-lg bg-primary-600 hover:bg-primary-700 text-white font-semibold text-sm shadow-sm transition disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        <span wire:loading.remove>Send (إرسال)</span>
                        <span wire:loading>Thinking...</span>
                    </button>
                </form>
            </div>
        </div>
    </div>
</x-filament-panels::page>
