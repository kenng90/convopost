<?php

namespace App\Services\Messaging;

use App\Enums\MessagingChannelType;
use App\Models\Company;
use App\Models\Messaging\ChannelConnection;
use App\Services\Messaging\Contracts\MessagingChannel;
use Illuminate\Support\Collection;

class MessagingChannelRegistry
{
    /** @var array<string, MessagingChannel> */
    private array $channels = [];

    public function register(MessagingChannel $channel): void
    {
        $this->channels[$channel->channel()->value] = $channel;
    }

    public function get(MessagingChannelType $channel): MessagingChannel
    {
        $adapter = $this->channels[$channel->value] ?? null;

        if ($adapter === null) {
            throw new \InvalidArgumentException('Messaging channel not registered: '.$channel->value);
        }

        return $adapter;
    }

    public function has(MessagingChannelType $channel): bool
    {
        return isset($this->channels[$channel->value]);
    }

    /**
     * @return Collection<int, ChannelConnection>
     */
    public function connectedForCompany(Company $company): Collection
    {
        return ChannelConnection::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('status', 'connected')
            ->get();
    }
}
