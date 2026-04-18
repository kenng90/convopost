<?php

namespace App\Livewire;

use App\Models\WhatsappFlow;
use App\Services\WhatsappMetaFlowService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Http;
use Livewire\Component;

class FlowsBuilder extends Component
{
    public ?array $flowData = null;
    public string $flowName = '';
    public string $flowDescription = '';
    public array $screens = [];
    public ?string $selectedScreenId = null; // Now string (e.g., "screen_1")
    public array $fields = [];
    public ?int $selectedFieldId = null;
    public int $nextScreenId = 1;
    public int $nextFieldId = 1;
    public ?int $newlyCreatedFlowId = null;

    public array $fieldTypes = [
        // Text Components (4)
        'heading' => ['label' => 'TextHeading', 'icon' => '📝', 'category' => 'text', 'meta' => 'TextHeading'],
        'subheading' => ['label' => 'TextSubheading', 'icon' => '📋', 'category' => 'text', 'meta' => 'TextSubheading'],
        'body' => ['label' => 'TextBody', 'icon' => '📄', 'category' => 'text', 'meta' => 'TextBody'],
        'caption' => ['label' => 'TextCaption', 'icon' => '💬', 'category' => 'text', 'meta' => 'TextCaption'],

        // Rich Text (1)
        'richtext' => ['label' => 'RichText (Markdown)', 'icon' => '✨', 'category' => 'text', 'meta' => 'RichText'],

        // Input Components (6)
        'text' => ['label' => 'TextEntry', 'icon' => '✏️', 'category' => 'input', 'meta' => 'TextEntry'],
        'textarea' => ['label' => 'TextEntry (Multi)', 'icon' => '📝', 'category' => 'input', 'meta' => 'TextEntry'],
        'radio' => ['label' => 'RadioButtonsGroup', 'icon' => '⭕', 'category' => 'input', 'meta' => 'RadioButtonsGroup'],
        'checkbox' => ['label' => 'CheckboxGroup', 'icon' => '☑️', 'category' => 'input', 'meta' => 'CheckboxGroup'],
        'select' => ['label' => 'Dropdown', 'icon' => '▼', 'category' => 'input', 'meta' => 'Dropdown'],
        'date' => ['label' => 'DatePicker', 'icon' => '📅', 'category' => 'input', 'meta' => 'DatePicker'],

        // Media Components (3)
        'image' => ['label' => 'Image (Base64)', 'icon' => '🖼️', 'category' => 'media', 'meta' => 'Image'],
        'media_upload' => ['label' => 'Media Upload', 'icon' => '📤', 'category' => 'media', 'meta' => 'MediaUpload'],
        'image_carousel' => ['label' => 'Image Carousel', 'icon' => '🎠', 'category' => 'media', 'meta' => 'ImageCarousel'],

        // Rich Content (2)
        'embedded_link' => ['label' => 'Embedded Link', 'icon' => '🔗', 'category' => 'content', 'meta' => 'EmbeddedLink'],
        'optin' => ['label' => 'Opt-in (Consent)', 'icon' => '✅', 'category' => 'content', 'meta' => 'OptIn'],

        // Interactive Components (1)
        'chips' => ['label' => 'Chips Selector', 'icon' => '🏷️', 'category' => 'input', 'meta' => 'ChipsSelector'],

        // Layout/Navigation (1)
        'footer' => ['label' => 'Footer', 'icon' => '📌', 'category' => 'layout', 'meta' => 'Footer'],

        // Conditional (2)
        'if_condition' => ['label' => 'If (Conditional)', 'icon' => '🔄', 'category' => 'logic', 'meta' => 'If'],
        'switch' => ['label' => 'Switch (Logic)', 'icon' => '🔀', 'category' => 'logic', 'meta' => 'Switch'],

        // Legacy (kept for compatibility)
        'button' => ['label' => 'Button', 'icon' => '🔘', 'category' => 'action', 'meta' => 'ButtonGroup'],
    ];

    public function mount()
    {
        $this->loadFlowFromRoute();
    }

    /**
     * Load flow data from route parameter
     */
    public function loadFlowFromRoute()
    {
        $id = request()->route('id');

        if ($id) {
            $this->loadFlow($id);
        } else {
            // Create initial screen for new flow
            if (empty($this->screens)) {
                $this->addScreen();
            }
        }
    }

    /**
     * Load a specific flow by ID
     */
    private function loadFlow(int $id)
    {
        $companyId = auth()->user()->company_id;
        $flow = WhatsappFlow::where('id', $id)
            ->where('company_id', $companyId)
            ->first();

        if ($flow) {
            $this->flowData = $flow->toArray();
            $this->flowName = $flow->name;
            $this->flowDescription = $flow->description ?? '';

            // Load screens and ensure they have required Meta fields
            $screenIndex = 0;
            $this->screens = array_map(function ($screen) use (&$screenIndex) {
                $screenIndex++;

                // Use existing ID or generate a new one with only letters (A, B, C, etc.)
                if (!isset($screen['id'])) {
                    $letter = chr(64 + $screenIndex); // A=65, B=66, etc.
                    $screenId = 'SCREEN_' . $letter;
                } else {
                    $screenId = $screen['id'];
                }

                $screenData = [
                    'id' => (string)$screenId, // Meta requires string ID with only letters/underscores
                    'title' => $screen['title'] ?? 'Screen',
                    'terminal' => $screen['terminal'] ?? false,
                    'fields' => $screen['fields'] ?? [],
                ];

                // Only include optional fields if they exist
                if (!empty($screen['success'])) {
                    $screenData['success'] = $screen['success'];
                }
                if (!empty($screen['data'])) {
                    $screenData['data'] = $screen['data'];
                }

                return $screenData;
            }, $flow->flow_json['screens'] ?? []);

            // Set highest IDs for generation
            foreach ($this->screens as $screen) {
                // Extract letter from string ID like "SCREEN_A", "SCREEN_B", etc.
                if (preg_match('/SCREEN_([A-Z])/', $screen['id'], $matches)) {
                    $letter = $matches[1];
                    $numericId = ord($letter) - 64; // A=1, B=2, C=3, etc.
                    if ($numericId >= $this->nextScreenId) {
                        $this->nextScreenId = $numericId + 1;
                    }
                }
                foreach ($screen['fields'] ?? [] as $field) {
                    if ($field['id'] >= $this->nextFieldId) {
                        $this->nextFieldId = $field['id'] + 1;
                    }
                }
            }

            // Select first screen
            if (!empty($this->screens)) {
                $this->selectedScreenId = $this->screens[0]['id'];
                $this->loadFieldsForScreen($this->selectedScreenId);
            }
        }
    }

    /**
     * Returns the WhatsApp Flows endpoint URL that must be set in Meta Flow Builder.
     * Format: {app_url}/webhook/wpbox/flows/{token}
     *
     * Tries multiple sources for the token (manual setup, embedded signup, etc.)
     * and auto-generates one if none exists.
     */
    public function getWebhookEndpointUrl(): string
    {
        $user    = auth()->user();
        $company = \App\Models\Company::find($user->company_id);

        // 1. Try company-level config (most common — set by setup wizard)
        $token = $company ? $company->getConfig('plain_token', '') : '';

        // 2. Try user-level config (set by admin setup path)
        if (empty($token)) {
            $token = $user->getConfig('plain_token', '');
        }

        // 3. Auto-generate: Sanctum tokens are stored hashed so we can't recover
        //    the plaintext of an existing token. Create a dedicated "Flows Webhook"
        //    token once and persist its plaintext for future use.
        if (empty($token)) {
            // Delete any stale "Flows Webhook" tokens to avoid accumulation
            \Laravel\Sanctum\PersonalAccessToken::where('tokenable_id', $user->id)
                ->where('tokenable_type', 'App\Models\User')
                ->where('name', 'Flows Webhook')
                ->delete();

            $newToken = $user->createToken('Flows Webhook');
            $parts    = explode('|', $newToken->plainTextToken);
            $token    = $parts[1] ?? $newToken->plainTextToken;

            // Persist so this URL never changes on subsequent loads
            if ($company) {
                $company->setConfig('plain_token', $token);
            }
            $user->setConfig('plain_token', $token);
        }

        return rtrim(config('app.url'), '/') . '/webhook/wpbox/flows/' . $token;
    }

    public function render(): View
    {
        // If we're on an edit page but flowData is empty, try to load it
        if (!$this->flowData && request()->route('id')) {
            $this->loadFlowFromRoute();
        }

        return view('livewire.flows-builder', [
            'fieldTypes' => $this->fieldTypes,
            'selectedScreen' => $this->getSelectedScreen(),
            'selectedField' => $this->getSelectedField(),
        ]);
    }

    // Screen Management
    public function addScreen()
    {
        // Save fields from previous screen if one was selected
        $this->saveFieldsToScreen();

        $screenId = $this->nextScreenId++;
        // Meta requires ID to contain ONLY letters and underscores (no numbers!)
        // Convert to alphabetic: 1=A, 2=B, 3=C, etc.
        $letter = chr(64 + $screenId); // A=65, B=66, etc.
        $newScreenId = 'SCREEN_' . $letter;

        $this->screens[] = [
            'id' => $newScreenId,
            'title' => 'Screen ' . (count($this->screens) + 1),
            'terminal' => false, // Whether this is the last screen
            'fields' => [],
        ];

        // Only auto-select if this is the first screen
        if ($this->selectedScreenId === null) {
            $this->selectedScreenId = $newScreenId;
            $this->selectedFieldId = null;
            $this->loadFieldsForScreen($newScreenId);
        }
    }

    public function removeScreen($id)
    {
        $this->screens = array_filter($this->screens, fn($s) => $s['id'] !== $id);
        $this->screens = array_values($this->screens);

        if ($this->selectedScreenId === $id) {
            $this->selectedScreenId = !empty($this->screens) ? $this->screens[0]['id'] : null;
            $this->fields = [];
            $this->selectedFieldId = null;
        }
    }

    public function selectScreen($id)
    {
        // Save current fields before switching
        $this->saveFieldsToScreen();

        $this->selectedScreenId = $id;
        $this->selectedFieldId = null;
        $this->loadFieldsForScreen($id);
    }

    public function updateScreenTitle($id, string $title)
    {
        foreach ($this->screens as &$screen) {
            if ($screen['id'] === $id) {
                $screen['title'] = $title;
                break;
            }
        }
    }

    private function loadFieldsForScreen($screenId)
    {
        $screen = $this->getScreenById($screenId);
        $this->fields = $screen['fields'] ?? [];
    }

    private function saveFieldsToScreen()
    {
        if ($this->selectedScreenId === null) {
            return;
        }

        foreach ($this->screens as &$screen) {
            if ($screen['id'] === $this->selectedScreenId) {
                $screen['fields'] = $this->fields;
                break;
            }
        }
    }

    private function getScreenById($id): ?array
    {
        return collect($this->screens)->firstWhere('id', $id);
    }

    private function getSelectedScreen(): ?array
    {
        return $this->getScreenById($this->selectedScreenId);
    }

    // Field Management
    public function addField(string $type)
    {
        if (!array_key_exists($type, $this->fieldTypes)) {
            $this->dispatch('showNotification', type: 'error', message: 'Invalid field type');
            return;
        }

        $existingTypes = array_column($this->fields, 'type');
        $hasRichText = in_array('richtext', $existingTypes);
        $hasFooter = in_array('footer', $existingTypes);
        $hasOtherComponents = !empty(array_filter($existingTypes, fn($t) => !in_array($t, ['richtext', 'footer'])));

        // RichText exclusivity rules:
        // 1. Cannot add anything except Footer to a screen that already has RichText
        if ($hasRichText && $type !== 'footer' && $type !== 'richtext') {
            $this->dispatch('showNotification', type: 'error', message: 'RichText can only be paired with a Footer component. Remove RichText first to add other components.');
            return;
        }

        // 2. Cannot add RichText to a screen that already has non-Footer components
        if ($type === 'richtext' && $hasOtherComponents) {
            $this->dispatch('showNotification', type: 'error', message: 'RichText must be alone or paired only with Footer. Remove other components first.');
            return;
        }

        // 3. Cannot add more than one RichText
        if ($type === 'richtext' && $hasRichText) {
            $this->dispatch('showNotification', type: 'error', message: 'Only one RichText component is allowed per screen.');
            return;
        }

        // 4. Footer only allowed with RichText (on terminal screens)
        if ($type === 'footer' && $hasFooter) {
            $this->dispatch('showNotification', type: 'error', message: 'Only one Footer component is allowed per screen.');
            return;
        }

        $fieldId = $this->nextFieldId++;
        $defaults = $this->getFieldDefaults($type);

        $newField = [
            'id' => $fieldId,
            'type' => $type,
            'label' => $defaults['label'],
            'placeholder' => $defaults['placeholder'] ?? '',
            'required' => false,
            'has_error' => false,
            'options' => $defaults['options'] ?? [],
        ];

        $this->fields[] = $newField;
        $this->selectedFieldId = $fieldId;
    }

    public function removeField(int $fieldId)
    {
        $this->fields = array_filter($this->fields, fn($f) => $f['id'] !== $fieldId);
        $this->fields = array_values($this->fields);

        if ($this->selectedFieldId === $fieldId) {
            $this->selectedFieldId = null;
        }
    }

    public function selectField(int $fieldId)
    {
        $this->selectedFieldId = $fieldId;
    }

    public function updateFieldProperty(int $fieldId, string $property, mixed $value)
    {
        foreach ($this->fields as &$field) {
            if ($field['id'] === $fieldId) {
                $field[$property] = $value;
                break;
            }
        }
    }

    public function addFieldOption(int $fieldId)
    {
        foreach ($this->fields as &$field) {
            if ($field['id'] === $fieldId && in_array($field['type'], ['radio', 'checkbox', 'select', 'chips'])) {
                $optionId = 'option_' . uniqid();
                $field['options'][] = [
                    'id' => $optionId,
                    'label' => 'Option ' . (count($field['options'] ?? []) + 1),
                    'value' => 'option_' . uniqid(),
                ];
                break;
            }
        }
    }

    public function removeFieldOption(int $fieldId, string $optionId)
    {
        foreach ($this->fields as &$field) {
            if ($field['id'] === $fieldId) {
                $field['options'] = array_filter($field['options'], fn($o) => $o['id'] !== $optionId);
                $field['options'] = array_values($field['options']);
                break;
            }
        }
    }

    // Image Carousel Management
    public function addCarouselImage(int $fieldId): void
    {
        foreach ($this->fields as &$field) {
            if ($field['id'] === $fieldId && $field['type'] === 'image_carousel') {
                $field['images'][] = [
                    'src' => '',
                    'alt_text' => 'Image ' . (count($field['images'] ?? []) + 1),
                ];
                break;
            }
        }
    }

    public function removeCarouselImage(int $fieldId, int $imageIndex): void
    {
        foreach ($this->fields as &$field) {
            if ($field['id'] === $fieldId && $field['type'] === 'image_carousel') {
                array_splice($field['images'], $imageIndex, 1);
                break;
            }
        }
    }

    public function updateCarouselImage(int $fieldId, int $imageIndex, string $property, string $value): void
    {
        foreach ($this->fields as &$field) {
            if ($field['id'] === $fieldId && $field['type'] === 'image_carousel') {
                if (isset($field['images'][$imageIndex])) {
                    $field['images'][$imageIndex][$property] = $value;
                }
                break;
            }
        }
    }

    public function updateFieldOption(int $fieldId, string $optionId, string $property, string $value)
    {
        foreach ($this->fields as &$field) {
            if ($field['id'] === $fieldId) {
                foreach ($field['options'] as &$option) {
                    if ($option['id'] === $optionId) {
                        $option[$property] = $value;
                        break;
                    }
                }
                break;
            }
        }
    }

    private function getFieldById(?int $fieldId): ?array
    {
        if ($fieldId === null) {
            return null;
        }
        return collect($this->fields)->firstWhere('id', $fieldId);
    }

    private function getSelectedField(): ?array
    {
        return $this->getFieldById($this->selectedFieldId);
    }

    private function getFieldDefaults(string $type): array
    {
        return match ($type) {
            // Text Components
            'heading' => ['label' => 'Page Heading', 'placeholder' => 'Your main heading (80 chars max)', 'font_weight' => 'normal'],
            'subheading' => ['label' => 'Subheading', 'placeholder' => 'Secondary heading (80 chars max)', 'font_weight' => 'normal'],
            'body' => ['label' => 'Body Text', 'placeholder' => 'Main text content (4096 chars max)', 'font_weight' => 'normal', 'markdown' => false],
            'caption' => ['label' => 'Caption', 'placeholder' => 'Small text caption (409 chars max)', 'font_weight' => 'normal'],
            'richtext' => ['label' => 'Rich Text', 'placeholder' => 'Markdown formatted text', 'markdown' => true],

            // Input Components
            'text' => ['label' => 'Text Input', 'placeholder' => 'Enter text', 'input_type' => 'text'],
            'textarea' => ['label' => 'Multi-line Text', 'placeholder' => 'Enter long text', 'input_type' => 'text', 'multiline' => true],
            'checkbox' => ['label' => 'Select multiple', 'options' => [
                ['id' => 'opt1', 'label' => 'Option 1', 'value' => 'opt1'],
                ['id' => 'opt2', 'label' => 'Option 2', 'value' => 'opt2'],
            ]],
            'radio' => ['label' => 'Choose one', 'options' => [
                ['id' => 'opt1', 'label' => 'Yes', 'value' => 'yes'],
                ['id' => 'opt2', 'label' => 'No', 'value' => 'no'],
            ]],
            'select' => ['label' => 'Select from list', 'options' => [
                ['id' => 'opt1', 'label' => 'Option 1', 'value' => '1'],
                ['id' => 'opt2', 'label' => 'Option 2', 'value' => '2'],
            ]],
            'date' => ['label' => 'Select Date', 'placeholder' => 'DD/MM/YYYY'],
            'chips' => ['label' => 'Quick Select', 'options' => [
                ['id' => 'opt1', 'label' => 'Option 1', 'value' => 'opt1'],
                ['id' => 'opt2', 'label' => 'Option 2', 'value' => 'opt2'],
            ]],

            // Media Components
            'image' => ['label' => 'Image', 'image_url' => '', 'scale' => 'scale-to-fit'],
            'media_upload' => ['label' => 'Upload File', 'placeholder' => 'Choose media file'],
            'image_carousel' => ['label' => 'Image Carousel', 'images' => []],

            // Rich Content
            'embedded_link' => ['label' => 'Link Button', 'url' => 'https://example.com', 'button_label' => 'Open Link'],
            'optin' => ['label' => 'I agree to terms', 'required' => true],

            // Layout/Navigation
            'footer' => ['label' => 'Continue', 'help_text' => 'Business footer'],

            // Conditional Logic
            'if_condition' => ['label' => 'If Condition', 'condition' => '', 'then_action' => 'show', 'else_action' => 'hide'],
            'switch' => ['label' => 'Switch Logic', 'cases' => []],

            // Action
            'button' => ['label' => 'Submit', 'placeholder' => ''],

            default => ['label' => 'Field', 'placeholder' => ''],
        };
    }

    /**
     * Run pre-publish validation on current flow state and return error strings.
     * Returns empty array if all checks pass.
     */
    private function getPrePublishErrors(): array
    {
        $errors = [];

        if (empty($this->flowName)) {
            $errors[] = 'Flow name is required.';
        }

        if (empty($this->screens)) {
            $errors[] = 'Add at least one screen to the flow.';
            return $errors; // no point checking fields
        }

        foreach ($this->screens as $screen) {
            $screenTitle = $screen['title'] ?? 'Untitled screen';
            $fields = $screen['fields'] ?? [];

            if (empty($fields)) {
                $errors[] = "\"$screenTitle\": screen has no components.";
                continue;
            }

            foreach ($fields as $field) {
                $type = $field['type'] ?? '';

                if ($type === 'image' && empty(trim($field['image_url'] ?? ''))) {
                    $errors[] = "\"$screenTitle\": Image component has no source — enter a public URL or base64 image.";
                }

                if ($type === 'image_carousel') {
                    $images = $field['images'] ?? [];
                    if (empty($images)) {
                        $errors[] = "\"$screenTitle\": Image Carousel has no images — add at least one.";
                    } else {
                        foreach ($images as $i => $img) {
                            if (empty(trim($img['src'] ?? ''))) {
                                $errors[] = "\"$screenTitle\": Image Carousel — image #" . ($i + 1) . " is missing a URL.";
                            }
                        }
                    }
                }

                if ($type === 'embedded_link' && empty(trim($field['url'] ?? ''))) {
                    $errors[] = "\"$screenTitle\": Embedded Link is missing a URL.";
                }
            }
        }

        return $errors;
    }

    /**
     * Publish flow to Meta WhatsApp Flows API
     */
    public function publishFlowToMeta()
    {
        if (!$this->flowData || !isset($this->flowData['id'])) {
            \Illuminate\Support\Facades\Log::warning('Publish to Meta failed: No flow data', [
                'has_flow_data' => !empty($this->flowData),
                'flow_id' => $this->flowData['id'] ?? null,
            ]);
            $this->dispatch('showNotification', type: 'error', message: 'Please save the flow first before publishing to Meta');
            return;
        }

        // Save latest changes to screen before validating
        $this->saveFieldsToScreen();

        // Run pre-publish validation before hitting Meta's API
        $preErrors = $this->getPrePublishErrors();
        if (!empty($preErrors)) {
            $message = 'Fix these issues before publishing: ' . implode(' | ', $preErrors);
            $this->dispatch('showNotification', type: 'error', message: $message);
            return;
        }

        try {
            $flowId = $this->flowData['id'];

            \Illuminate\Support\Facades\Log::info('Starting publish to Meta from Livewire (direct service call)', [
                'flow_id' => $flowId,
                'flow_name' => $this->flowData['name'] ?? 'unknown',
            ]);

            // Fetch the flow from database
            $companyId = auth()->user()->company_id;
            $flow = WhatsappFlow::where('id', $flowId)
                ->where('company_id', $companyId)
                ->first();

            if (!$flow) {
                \Illuminate\Support\Facades\Log::error('Flow not found for publishing', [
                    'flow_id' => $flowId,
                    'company_id' => $companyId,
                ]);
                $this->dispatch('showNotification', type: 'error', message: 'Flow not found');
                return;
            }

            \Illuminate\Support\Facades\Log::info('Flow loaded from database for publishing', [
                'flow_id' => $flow->id,
                'flow_name' => $flow->name,
            ]);

            // Call service directly (no HTTP request needed)
            $service = new WhatsappMetaFlowService();
            $result = $service->publishFlow($flow);

            \Illuminate\Support\Facades\Log::debug('Publish service result', [
                'flow_id' => $flowId,
                'success' => $result['success'] ?? false,
                'message' => $result['message'] ?? '',
            ]);

            if ($result['success']) {
                $metaFlowId = $result['meta_flow_id'] ?? null;
                $this->flowData['meta_flow_id'] = $metaFlowId;

                \Illuminate\Support\Facades\Log::info('Flow published to Meta successfully', [
                    'flow_id' => $flowId,
                    'meta_flow_id' => $metaFlowId,
                ]);

                $this->dispatch('showNotification', type: 'success', message: 'Flow published to Meta successfully! Flow ID: ' . $metaFlowId);
            } else {
                \Illuminate\Support\Facades\Log::error('Publish to Meta failed from service', [
                    'flow_id' => $flowId,
                    'message' => $result['message'] ?? '',
                    'error' => $result['error'] ?? null,
                ]);

                $this->dispatch('showNotification', type: 'error', message: $result['message'] ?? 'Failed to publish flow to Meta');
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Publish flow to Meta exception', [
                'flow_id' => $this->flowData['id'] ?? null,
                'exception' => $e->getMessage(),
                'exception_class' => get_class($e),
                'trace' => $e->getTraceAsString(),
            ]);
            $this->dispatch('showNotification', type: 'error', message: 'Error: ' . $e->getMessage());
        }
    }


    /**
     * Re-publish an already-published Meta flow after editing.
     * Saves current state first, then calls POST /{meta-flow-id}/publish.
     */
    public function republishFlow(): void
    {
        if (! $this->flowData || ! isset($this->flowData['id'])) {
            $this->dispatch('showNotification', type: 'error', message: 'Please save the flow first.');
            return;
        }

        $this->saveFieldsToScreen();

        $preErrors = $this->getPrePublishErrors();
        if (! empty($preErrors)) {
            $this->dispatch('showNotification', type: 'error', message: 'Fix these issues before re-publishing: ' . implode(' | ', $preErrors));
            return;
        }

        try {
            $companyId = auth()->user()->company_id;
            $flow      = WhatsappFlow::where('id', $this->flowData['id'])
                ->where('company_id', $companyId)
                ->first();

            if (! $flow) {
                $this->dispatch('showNotification', type: 'error', message: 'Flow not found.');
                return;
            }

            if (! $flow->meta_flow_id) {
                $this->dispatch('showNotification', type: 'error', message: 'This flow has not been published to Meta yet. Use "Publish to Meta" first.');
                return;
            }

            // Push latest local changes to Meta before re-publishing
            $service    = new WhatsappMetaFlowService();
            $updateResult = $service->updateFlowOnMeta($flow);

            if (! $updateResult['success']) {
                $this->dispatch('showNotification', type: 'error', message: 'Could not push updated JSON to Meta: ' . $updateResult['message']);
                return;
            }

            $result = $service->republishFlowOnMeta($flow);

            if ($result['success']) {
                $this->flowData = $flow->fresh()->toArray();
                $this->dispatch('showNotification', type: 'success', message: $result['message']);
            } else {
                $this->dispatch('showNotification', type: 'error', message: 'Re-publish failed: ' . $result['message']);
            }

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('republishFlow exception', ['error' => $e->getMessage()]);
            $this->dispatch('showNotification', type: 'error', message: 'Error: ' . $e->getMessage());
        }
    }

    // Flow Management
    public function saveFlow()
    {
        if (empty($this->flowName)) {
            \Illuminate\Support\Facades\Log::warning('Save flow failed: name is required');
            $this->dispatch('showNotification', type: 'error', message: 'Flow name is required');
            return;
        }

        if (empty($this->screens)) {
            \Illuminate\Support\Facades\Log::warning('Save flow failed: no screens');
            $this->dispatch('showNotification', type: 'error', message: 'Add at least one screen to the flow');
            return;
        }

        // Save current fields to screen before saving
        $this->saveFieldsToScreen();

        try {
            \Illuminate\Support\Facades\Log::info('saveFlow called', [
                'flow_name' => $this->flowName,
                'is_update' => !empty($this->flowData && isset($this->flowData['id'])),
                'screen_count' => count($this->screens),
                'company_id' => auth()->user()->company_id,
            ]);

            $flowJson = [
                'screens' => $this->screens,
                'version' => '1.0',
            ];

            \Illuminate\Support\Facades\Log::debug('Flow JSON prepared', [
                'screen_count' => count($this->screens),
                'screens' => $this->screens,
            ]);

            $data = [
                'name' => $this->flowName,
                'description' => $this->flowDescription,
                'flow_json' => $flowJson,
                'status' => 'draft',
            ];

            if ($this->flowData && isset($this->flowData['id'])) {
                // Update existing flow
                $flowId = $this->flowData['id'];

                \Illuminate\Support\Facades\Log::info('Updating existing flow', [
                    'flow_id' => $flowId,
                    'flow_name' => $this->flowName,
                ]);

                $flow = WhatsappFlow::find($flowId);
                if ($flow) {
                    $flow->update($data);

                    \Illuminate\Support\Facades\Log::info('Flow updated successfully', [
                        'flow_id' => $flow->id,
                        'flow_name' => $flow->name,
                    ]);

                    // If flow was previously published to Meta, push the updated JSON there too
                    if ($flow->meta_flow_id) {
                        $service      = new WhatsappMetaFlowService();
                        $metaResult   = $service->updateFlowOnMeta($flow);

                        if ($metaResult['success']) {
                            $this->dispatch('showNotification', type: 'success', message: 'Flow updated locally and on Meta. Re-publish to make changes live.');
                        } else {
                            $this->dispatch('showNotification', type: 'error', message: 'Saved locally, but Meta update failed: ' . $metaResult['message']);
                        }
                    } else {
                        $this->dispatch('showNotification', type: 'success', message: 'Flow updated successfully.');
                    }

                    // Reload flow data
                    $this->flowData = $flow->toArray();
                } else {
                    \Illuminate\Support\Facades\Log::error('Flow not found for update', [
                        'flow_id' => $flowId,
                    ]);
                }
            } else {
                // Create new flow
                \Illuminate\Support\Facades\Log::info('Creating new flow', [
                    'flow_name' => $this->flowName,
                    'company_id' => auth()->user()->company_id,
                ]);

                $flow = WhatsappFlow::create([
                    'company_id' => auth()->user()->company_id,
                    ...$data,
                ]);

                \Illuminate\Support\Facades\Log::info('New flow created successfully', [
                    'flow_id' => $flow->id,
                    'flow_name' => $flow->name,
                    'company_id' => $flow->company_id,
                ]);

                $this->dispatch('showNotification', type: 'success', message: 'Flow created successfully!');

                // Redirect to edit page using Livewire's redirect
                return $this->redirect(route('whatsapp-flows.edit', $flow->id), navigate: true);
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Save flow failed with exception', [
                'flow_name' => $this->flowName,
                'exception_class' => get_class($e),
                'exception_message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            $this->dispatch('showNotification', type: 'error', message: 'Failed to save flow: ' . $e->getMessage());
        }
    }
}
