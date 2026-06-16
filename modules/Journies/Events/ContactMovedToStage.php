<?php

namespace Modules\Journies\Events;

use Illuminate\Queue\SerializesModels;

class ContactMovedToStage
{
    use SerializesModels;

    public function __construct(
        public $contact,
        public $stage,
        public ?int $activityId = null,
        public string $source = 'manual',
    ) {
    }
}
