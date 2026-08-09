<?php

namespace App\Console\Commands;

use App\Enums\MessagingChannelType;
use App\Models\Company;
use App\Scopes\CompanyScope;
use App\Services\Messaging\ChannelConnectionService;
use App\Services\Messaging\ConversationService;
use Illuminate\Console\Command;
use Modules\Wpbox\Models\Contact;
use Modules\Wpbox\Models\Message;

class MessagingBackfillConversationsCommand extends Command
{
    protected $signature = 'messaging:backfill-conversations {--company= : Limit to a company ID}';

    protected $description = 'Backfill channel identities, conversations, and message conversation_id values';

    public function handle(
        ChannelConnectionService $connections,
        ConversationService $conversations,
    ): int {
        $query = Contact::withoutGlobalScope(CompanyScope::class)
            ->where('has_chat', 1);

        if ($companyId = $this->option('company')) {
            $query->where('company_id', $companyId);
        }

        $conversationCount = 0;
        $messageCount = 0;

        $query->chunkById(200, function ($contacts) use ($connections, $conversations, &$conversationCount, &$messageCount) {
            foreach ($contacts as $contact) {
                $company = Company::find($contact->company_id);

                if (! $company) {
                    continue;
                }

                $connections->ensureWhatsappConnection($company);
                $conversation = $conversations->ensureForContact($contact, MessagingChannelType::Whatsapp);
                $conversationCount++;

                $updated = Message::withoutGlobalScope(CompanyScope::class)
                    ->where('contact_id', $contact->id)
                    ->whereNull('conversation_id')
                    ->update([
                        'conversation_id' => $conversation->id,
                        'channel' => MessagingChannelType::Whatsapp->value,
                    ]);

                $messageCount += $updated;
            }
        });

        $this->info("Ensured {$conversationCount} conversation(s); linked {$messageCount} message(s).");

        return self::SUCCESS;
    }
}
