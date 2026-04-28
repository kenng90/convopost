<?php

namespace App\Livewire;

use App\Models\WhatsappFlow;
use Livewire\Component;

/**
 * FlowsBuilder — minimal Livewire shell.
 *
 * This component only renders the page and passes initial data to Alpine.
 * ALL builder state (screens, fields, selected items, dirty flag) lives in
 * Alpine.js on the client. The server is only called on explicit save/publish
 * via fetch() API calls — never on field clicks or screen switches.
 */
class FlowsBuilder extends Component
{
    public ?int $flowId = null;

    public function mount(?int $flowId = null): void
    {
        $this->flowId = $flowId;
    }

    public function render()
    {
        $flow = $this->flowId ? WhatsappFlow::find($this->flowId) : null;

        return view('livewire.flows-builder', [
            'flow'   => $flow,
            'flowId' => $this->flowId,
        ]);
    }
}
