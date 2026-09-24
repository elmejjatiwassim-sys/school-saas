<?php

namespace App\Console\Commands;

use App\Models\AiConversation;
use Illuminate\Console\Command;

class ArchiveAiConversations extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ai:archive-conversations {--force : Force archive all active conversations}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Archive all active AI Copilot conversations at the end of the academic cycle (August 15 at 23:59)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Starting annual archive of AI Copilot conversations...');

        $count = AiConversation::where('is_archived', false)
            ->update([
                'is_archived' => true,
                'archived_at' => now(),
            ]);

        $this->info("Successfully archived {$count} active AI conversations.");

        return Command::SUCCESS;
    }
}
