<?php

namespace Modules\Embeddedlogin\Services;

final class EmbeddedSignupSession
{
    public function __construct(
        public readonly string $flow,
        public readonly ?string $wabaId = null,
        public readonly ?string $phoneNumberId = null,
        public readonly ?string $pageId = null,
        public readonly ?string $instagramAccountId = null,
        public readonly ?string $businessId = null,
    ) {
    }

    public static function fromRequest(array $input): self
    {
        $pageId = $input['page_id'] ?? null;
        if (! $pageId && ! empty($input['page_ids'])) {
            $ids = is_array($input['page_ids']) ? $input['page_ids'] : json_decode($input['page_ids'], true);
            $pageId = is_array($ids) ? ($ids[0] ?? null) : null;
        }

        $instagramAccountId = $input['instagram_account_id'] ?? null;
        if (! $instagramAccountId && ! empty($input['instagram_account_ids'])) {
            $ids = is_array($input['instagram_account_ids'])
                ? $input['instagram_account_ids']
                : json_decode($input['instagram_account_ids'], true);
            $instagramAccountId = is_array($ids) ? ($ids[0] ?? null) : null;
        }

        return new self(
            flow: (string) ($input['flow'] ?? 'whatsapp_only'),
            wabaId: $input['waba_id'] ?? null,
            phoneNumberId: $input['phone_number_id'] ?? null,
            pageId: $pageId,
            instagramAccountId: $instagramAccountId,
            businessId: $input['business_id'] ?? null,
        );
    }

    public function isOmnichannel(): bool
    {
        return $this->flow === 'omnichannel';
    }
}
