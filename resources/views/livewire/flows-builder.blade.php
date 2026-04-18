<div class="flex flex-col h-screen bg-gray-50 dark:bg-gray-900" wire:key="flows-builder">
    <!-- Notification Toast -->
    <div id="notification-toast" class="fixed top-4 right-4 px-6 py-3 rounded-lg text-white shadow-lg z-50 hidden" style="min-width: 300px;">
        <p id="notification-message"></p>
    </div>

    <!-- Top Navigation Bar -->
    <div class="bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700 px-6 py-4 flex items-center justify-between mt-4">
        <div class="flex items-center gap-4">
            <a href="/whatsapp-flows" class="text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                </svg>
            </a>
            <div>
                <h1 class="text-xl font-bold text-gray-900 dark:text-white">{{ $flowData ? 'Edit Flow' : 'Create Flow' }}</h1>
                <p class="text-sm text-gray-500 dark:text-gray-400">WhatsApp Flow Builder</p>
            </div>
        </div>

        <div class="flex items-center gap-3">
            <button class="px-4 py-2 text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-700 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-600 transition">
                Preview
            </button>

            @if ($flowData)
                <a
                    href="{{ route('admin.apps.company') }}#facebook_developer"
                    class="px-4 py-2 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-300 rounded-lg transition font-medium text-sm flex items-center gap-1"
                    title="Manage encryption keys in Company Settings"
                >
                    🔑 Encryption Keys
                </a>

                @if ($flowData['meta_flow_id'])
                    {{-- Already published — show Re-publish --}}
                    <button
                        wire:click="republishFlow"
                        wire:loading.attr="disabled"
                        wire:confirm="This will push your latest changes to Meta and re-publish the flow. Users will see the updated version immediately. Continue?"
                        class="px-4 py-2 bg-green-600 hover:bg-green-700 disabled:bg-gray-400 text-white rounded-lg transition font-medium flex items-center gap-2"
                        title="Push latest changes and re-publish this flow on Meta"
                    >
                        <span wire:loading.remove wire:target="republishFlow">
                            &#8635; Re-publish
                        </span>
                        <span wire:loading wire:target="republishFlow" class="flex items-center gap-2">
                            <svg class="w-4 h-4 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                            </svg>
                            Re-publishing...
                        </span>
                    </button>
                @else
                    {{-- Not yet published — show Publish to Meta --}}
                    <button
                        wire:click="publishFlowToMeta"
                        wire:loading.attr="disabled"
                        class="px-4 py-2 bg-purple-600 hover:bg-purple-700 disabled:bg-gray-400 text-white rounded-lg transition font-medium flex items-center gap-2"
                    >
                        <span wire:loading.remove wire:target="publishFlowToMeta">
                            🚀 Publish to Meta
                        </span>
                        <span wire:loading wire:target="publishFlowToMeta" class="flex items-center gap-2">
                            <svg class="w-4 h-4 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                            </svg>
                            Publishing...
                        </span>
                    </button>
                @endif
            @endif

            <button
                wire:click="saveFlow"
                wire:loading.attr="disabled"
                class="px-4 py-2 bg-blue-600 hover:bg-blue-700 disabled:bg-gray-400 text-white rounded-lg transition font-medium flex items-center gap-2"
            >
                <span wire:loading.remove>
                    {{ $flowData ? 'Update Flow' : 'Create Flow' }}
                </span>
                <span wire:loading class="flex items-center gap-2">
                    <svg class="w-4 h-4 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                    </svg>
                    Saving...
                </span>
            </button>
        </div>
    </div>

    <!-- Endpoint URL Info Bar -->
    @php $endpointUrl = $this->getWebhookEndpointUrl(); @endphp
    <div class="bg-blue-50 dark:bg-blue-900/20 border-b border-blue-200 dark:border-blue-800 px-4 py-2 flex items-center gap-3 text-sm">
        <svg class="w-4 h-4 text-blue-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"></path>
        </svg>
        <span class="text-blue-800 dark:text-blue-200 font-medium flex-shrink-0">Flow Endpoint URI:</span>
        <code class="text-blue-700 dark:text-blue-300 bg-blue-100 dark:bg-blue-900/40 px-2 py-0.5 rounded text-xs font-mono flex-1 truncate" title="{{ $endpointUrl }}">
            {{ $endpointUrl }}
        </code>
        <button
            type="button"
            onclick="navigator.clipboard.writeText('{{ $endpointUrl }}').then(() => { this.textContent = 'Copied!'; setTimeout(() => this.textContent = 'Copy', 1500) })"
            class="flex-shrink-0 px-3 py-1 bg-blue-600 hover:bg-blue-700 text-white text-xs rounded transition font-medium"
        >Copy</button>
        <a href="https://business.facebook.com/wa/manage/flows/" target="_blank"
           class="flex-shrink-0 text-blue-600 hover:text-blue-800 text-xs underline whitespace-nowrap">
            Open Meta →
        </a>
    </div>

    <!-- Main Content Area -->
    <div class="flex flex-1 overflow-hidden">
        <!-- Left Sidebar - Flow Configuration & Screen Manager -->
        <div class="w-80 bg-white dark:bg-gray-800 border-r border-gray-200 dark:border-gray-700 overflow-y-auto flex flex-col">
            <!-- Flow Details Section -->
            <div class="p-4 border-b border-gray-200 dark:border-gray-700">
                <h3 class="text-sm font-bold text-gray-900 dark:text-white mb-3">Flow Details</h3>
                <div class="space-y-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Flow Name</label>
                        <input
                            type="text"
                            wire:model="flowName"
                            class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                            placeholder="My Flow"
                        />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Description</label>
                        <textarea
                            wire:model="flowDescription"
                            class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                            placeholder="What is this flow for?"
                            rows="2"
                        ></textarea>
                    </div>
                </div>
            </div>

            <!-- Screens Section -->
            <div class="p-4 border-b border-gray-200 dark:border-gray-700 flex-shrink-0">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="text-sm font-bold text-gray-900 dark:text-white">Screens</h3>
                    <button
                        wire:click="addScreen"
                        class="text-xs bg-blue-600 hover:bg-blue-700 text-white rounded px-2 py-1 transition"
                    >
                        + Add
                    </button>
                </div>

                <div class="space-y-2">
                    @forelse ($screens as $screen)
                        <div
                            wire:click="selectScreen('{{ $screen['id'] }}')"
                            @class([
                                'p-3 rounded-lg cursor-pointer transition border-2',
                                'border-blue-500 bg-blue-50 dark:bg-blue-900/20' => $selectedScreenId === $screen['id'],
                                'border-gray-300 dark:border-gray-600 hover:border-gray-400 dark:hover:border-gray-500 bg-gray-50 dark:bg-gray-700' => $selectedScreenId !== $screen['id'],
                            ])
                        >
                            <div class="flex items-center justify-between">
                                <span class="text-sm font-medium text-gray-900 dark:text-white">{{ $screen['title'] }}</span>
                                <button
                                    wire:click.stop="removeScreen('{{ $screen['id'] }}')"
                                    class="text-gray-400 hover:text-red-600 dark:hover:text-red-400 transition"
                                >
                                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path>
                                    </svg>
                                </button>
                            </div>
                        </div>
                    @empty
                        <p class="text-xs text-gray-500 dark:text-gray-400 text-center py-4">No screens yet. Click + Add to create one.</p>
                    @endforelse
                </div>
            </div>

            <!-- Edit Content Section -->
            <div class="p-4 flex-1 overflow-y-auto">
                @if ($selectedScreenId)
                    <div class="mb-4">
                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-2">Screen Title</label>
                        <input
                            type="text"
                            value="{{ $selectedScreen['title'] ?? '' }}"
                            wire:change="updateScreenTitle('{{ $selectedScreenId }}', $event.target.value)"
                            class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                        />
                    </div>

                    <div class="mb-3">
                        <h4 class="text-xs font-bold text-gray-900 dark:text-white mb-2">Content</h4>
                        <div class="space-y-2 mb-3">
                            @forelse ($fields as $field)
                                @php
                                    $fieldHasError = match($field['type']) {
                                        'image' => empty($field['image_url'] ?? ''),
                                        'image_carousel' => empty($field['images'] ?? []) ||
                                            collect($field['images'] ?? [])->contains(fn($img) => empty($img['src'] ?? '')),
                                        'embedded_link' => empty($field['url'] ?? ''),
                                        default => false,
                                    };
                                @endphp
                                <div
                                    wire:click="selectField({{ $field['id'] }})"
                                    @class([
                                        'p-2 rounded-lg flex items-center justify-between cursor-pointer transition',
                                        'bg-blue-100 dark:bg-blue-900/30 border-2 border-blue-500' => $selectedFieldId === $field['id'] && !$fieldHasError,
                                        'bg-red-50 dark:bg-red-900/20 border-2 border-red-400' => $fieldHasError,
                                        'bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 border-2 border-transparent' => $selectedFieldId !== $field['id'] && !$fieldHasError,
                                    ])
                                >
                                    <div class="flex items-center gap-1 min-w-0">
                                        @if ($fieldHasError)
                                            <span class="text-red-500 flex-shrink-0" title="Required field missing — click to fix">⚠️</span>
                                        @endif
                                        <span class="text-xs text-gray-900 dark:text-white font-medium truncate">{{ $field['label'] }}</span>
                                    </div>
                                    <button
                                        wire:click.stop="removeField({{ $field['id'] }})"
                                        class="text-red-500 hover:text-red-700 dark:text-red-400 flex-shrink-0"
                                    >
                                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path>
                                        </svg>
                                    </button>
                                </div>
                            @empty
                                <p class="text-xs text-gray-500 dark:text-gray-400">No content added yet</p>
                            @endforelse
                        </div>

                        <!-- Quick Add Buttons - Organized by Category -->
                        @php
                            $existingTypes = array_column($fields, 'type');
                            $hasRichText = in_array('richtext', $existingTypes);
                            $hasFooter = in_array('footer', $existingTypes);
                            $hasOtherComponents = !empty(array_filter($existingTypes, fn($t) => !in_array($t, ['richtext', 'footer'])));
                            // When RichText is present: only Footer is allowed; when screen has other components: RichText is locked
                            $richTextLocked = $hasRichText;      // RichText on screen → lock everything except Footer
                            $otherLocked = $hasOtherComponents;  // Other components present → lock RichText
                        @endphp

                        @if($hasRichText)
                            <div class="mb-2 px-2 py-1 bg-amber-50 dark:bg-amber-900/20 border border-amber-300 dark:border-amber-700 rounded text-amber-700 dark:text-amber-300 text-xs">
                                ⚠️ RichText is present. Only <strong>Footer</strong> can be added.
                            </div>
                        @endif

                        <div class="space-y-2 text-xs">
                            <!-- Text Components -->
                            <div>
                                <p class="font-bold text-gray-700 dark:text-gray-300 mb-1">📝 Text</p>
                                <div class="grid grid-cols-2 gap-1">
                                    @foreach([
                                        ['heading', 'Heading'], ['subheading', 'Subheading'],
                                        ['body', 'Body'], ['caption', 'Caption'],
                                    ] as [$fieldType, $label])
                                        @php $disabled = $richTextLocked @endphp
                                        <button
                                            wire:click="{{ $disabled ? '' : "addField('{$fieldType}')" }}"
                                            {{ $disabled ? 'disabled' : '' }}
                                            title="{{ $disabled ? 'Remove RichText before adding other components' : '' }}"
                                            class="px-2 py-1 rounded text-xs transition border
                                                {{ $disabled
                                                    ? 'bg-gray-100 dark:bg-gray-800 border-gray-200 dark:border-gray-700 text-gray-400 dark:text-gray-600 cursor-not-allowed'
                                                    : 'bg-blue-50 dark:bg-blue-900/20 border-blue-200 dark:border-blue-800 text-blue-700 dark:text-blue-300 hover:bg-blue-100 dark:hover:bg-blue-900/30' }}"
                                        >{{ $label }}</button>
                                    @endforeach

                                    {{-- RichText - disabled if other components exist --}}
                                    @php $richtextDisabled = $otherLocked || $hasRichText @endphp
                                    <button
                                        wire:click="{{ $richtextDisabled ? '' : "addField('richtext')" }}"
                                        {{ $richtextDisabled ? 'disabled' : '' }}
                                        title="{{ $hasRichText ? 'Only one RichText allowed per screen' : ($otherLocked ? 'Remove other components before adding RichText' : '') }}"
                                        class="px-2 py-1 col-span-2 rounded text-xs transition border
                                            {{ $richtextDisabled
                                                ? 'bg-gray-100 dark:bg-gray-800 border-gray-200 dark:border-gray-700 text-gray-400 dark:text-gray-600 cursor-not-allowed'
                                                : 'bg-blue-50 dark:bg-blue-900/20 border-blue-200 dark:border-blue-800 text-blue-700 dark:text-blue-300 hover:bg-blue-100 dark:hover:bg-blue-900/30' }}"
                                    >RichText {{ $hasRichText ? '(added)' : '' }}</button>
                                </div>
                            </div>

                            <!-- Input Components -->
                            <div>
                                <p class="font-bold text-gray-700 dark:text-gray-300 mb-1">⌨️ Input</p>
                                <div class="grid grid-cols-2 gap-1">
                                    @foreach([
                                        ['text', 'Text', false], ['textarea', 'TextArea', false],
                                        ['radio', 'Radio', false], ['checkbox', 'Checkbox', false],
                                        ['select', 'Dropdown', false], ['date', 'Date', false],
                                        ['chips', 'Chips', true],
                                    ] as [$fieldType, $label, $fullWidth])
                                        @php $disabled = $richTextLocked @endphp
                                        <button
                                            wire:click="{{ $disabled ? '' : "addField('{$fieldType}')" }}"
                                            {{ $disabled ? 'disabled' : '' }}
                                            title="{{ $disabled ? 'Remove RichText before adding other components' : '' }}"
                                            class="px-2 py-1 rounded text-xs transition border {{ $fullWidth ? 'col-span-2' : '' }}
                                                {{ $disabled
                                                    ? 'bg-gray-100 dark:bg-gray-800 border-gray-200 dark:border-gray-700 text-gray-400 dark:text-gray-600 cursor-not-allowed'
                                                    : 'bg-green-50 dark:bg-green-900/20 border-green-200 dark:border-green-800 text-green-700 dark:text-green-300 hover:bg-green-100 dark:hover:bg-green-900/30' }}"
                                        >{{ $label }}</button>
                                    @endforeach
                                </div>
                            </div>

                            <!-- Media Components -->
                            <div>
                                <p class="font-bold text-gray-700 dark:text-gray-300 mb-1">🖼️ Media</p>
                                <div class="grid grid-cols-2 gap-1">
                                    @foreach([
                                        ['image', 'Image', false], ['media_upload', 'Upload', false],
                                        ['image_carousel', 'Carousel', true],
                                    ] as [$fieldType, $label, $fullWidth])
                                        @php $disabled = $richTextLocked @endphp
                                        <button
                                            wire:click="{{ $disabled ? '' : "addField('{$fieldType}')" }}"
                                            {{ $disabled ? 'disabled' : '' }}
                                            title="{{ $disabled ? 'Remove RichText before adding other components' : '' }}"
                                            class="px-2 py-1 rounded text-xs transition border {{ $fullWidth ? 'col-span-2' : '' }}
                                                {{ $disabled
                                                    ? 'bg-gray-100 dark:bg-gray-800 border-gray-200 dark:border-gray-700 text-gray-400 dark:text-gray-600 cursor-not-allowed'
                                                    : 'bg-purple-50 dark:bg-purple-900/20 border-purple-200 dark:border-purple-800 text-purple-700 dark:text-purple-300 hover:bg-purple-100 dark:hover:bg-purple-900/30' }}"
                                        >{{ $label }}</button>
                                    @endforeach
                                </div>
                            </div>

                            <!-- Other Components -->
                            <div>
                                <p class="font-bold text-gray-700 dark:text-gray-300 mb-1">✨ Other</p>
                                <div class="grid grid-cols-2 gap-1">
                                    @foreach([
                                        ['embedded_link', 'Link', false], ['optin', 'OptIn', false],
                                        ['button', 'Button', false],
                                    ] as [$fieldType, $label, $fullWidth])
                                        @php $disabled = $richTextLocked @endphp
                                        <button
                                            wire:click="{{ $disabled ? '' : "addField('{$fieldType}')" }}"
                                            {{ $disabled ? 'disabled' : '' }}
                                            title="{{ $disabled ? 'Remove RichText before adding other components' : '' }}"
                                            class="px-2 py-1 rounded text-xs transition border {{ $fullWidth ? 'col-span-2' : '' }}
                                                {{ $disabled
                                                    ? 'bg-gray-100 dark:bg-gray-800 border-gray-200 dark:border-gray-700 text-gray-400 dark:text-gray-600 cursor-not-allowed'
                                                    : 'bg-indigo-50 dark:bg-indigo-900/20 border-indigo-200 dark:border-indigo-800 text-indigo-700 dark:text-indigo-300 hover:bg-indigo-100 dark:hover:bg-indigo-900/30' }}"
                                        >{{ $label }}</button>
                                    @endforeach

                                    {{-- Footer - allowed with RichText but only one allowed --}}
                                    @php $footerDisabled = $hasFooter  @endphp
                                    <button
                                        wire:click="{{ $footerDisabled ? '' : "addField('footer')" }}"
                                        {{ $footerDisabled ? 'disabled' : '' }}
                                        title="{{ $hasFooter ? 'Only one Footer allowed per screen' : '' }}"
                                        class="px-2 py-1 rounded text-xs transition border
                                            {{ $footerDisabled
                                                ? 'bg-gray-100 dark:bg-gray-800 border-gray-200 dark:border-gray-700 text-gray-400 dark:text-gray-600 cursor-not-allowed'
                                                : 'bg-indigo-50 dark:bg-indigo-900/20 border-indigo-200 dark:border-indigo-800 text-indigo-700 dark:text-indigo-300 hover:bg-indigo-100 dark:hover:bg-indigo-900/30' }}"
                                    >Continue {{ $hasFooter ? '(added)' : '' }}</button>
                                </div>
                            </div>
                        </div>
                    </div>
                @else
                    <div class="text-center py-8 text-gray-500 dark:text-gray-400">
                        <p class="text-sm">Select or create a screen to start editing</p>
                    </div>
                @endif
            </div>
        </div>

        <!-- Center Canvas - WhatsApp Phone Mockup Preview -->
        <div class="flex-1 bg-gray-100 dark:bg-gray-900 overflow-y-auto p-8 flex items-center justify-center">
            <div class="flex justify-center">
                <!-- Phone Mockup Container -->
                <div class="w-80 bg-black rounded-3xl shadow-2xl p-3 border-8 border-gray-800">
                    <!-- Phone Screen Content -->
                    <div class="bg-white dark:bg-gray-800 rounded-2xl h-full overflow-hidden flex flex-col" style="height: 640px;">
                        <!-- Status Bar -->
                        <div class="bg-gray-900 dark:bg-gray-900 text-white px-4 py-1 flex items-center justify-between text-xs">
                            <span>9:41</span>
                            <div class="flex gap-1">
                                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M1 9l2 2c4.97-4.97 13.03-4.97 18 0l2-2C16.93 2.93 7.08 2.93 1 9zm8 8l3 3 3-3c-1.65-1.66-4.34-1.66-6 0zm-4-4l2 2c2.76-2.76 7.24-2.76 10 0l2-2C15.14 9.14 8.87 9.14 5 13z"/></svg>
                                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M1 21h22L12 2 1 21zm12-3h-2v-2h2v2zm0-4h-2v-4h2v4z"/></svg>
                                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M15.5 1h-8C6.12 1 5 2.12 5 3.5v17C5 21.88 6.12 23 7.5 23h8c1.38 0 2.5-1.12 2.5-2.5v-17C18 2.12 16.88 1 15.5 1zm-4 21c-.83 0-1.5-.67-1.5-1.5s.67-1.5 1.5-1.5 1.5.67 1.5 1.5-.67 1.5-1.5 1.5zm4.5-4H7V4h9v14z"/></svg>
                            </div>
                        </div>

                        <!-- Chat Header -->
                        <div class="bg-green-600 text-white px-4 py-3 flex items-center gap-3">
                            <div class="w-10 h-10 bg-green-700 rounded-full flex items-center justify-center">
                                <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.42 0-8-3.58-8-8s3.58-8 8-8 8 3.58 8 8-3.58 8-8 8zm3.5-9c.83 0 1.5-.67 1.5-1.5S16.33 8 15.5 8 14 8.67 14 9.5s.67 1.5 1.5 1.5zm-7 0c.83 0 1.5-.67 1.5-1.5S9.33 8 8.5 8 7 8.67 7 9.5 7.67 11 8.5 11zm3.5 6.5c2.33 0 4.31-1.46 5.11-3.5H6.89c.8 2.04 2.78 3.5 5.11 3.5z"/>
                                </svg>
                            </div>
                            <div class="flex-1">
                                <p class="font-medium text-sm">Business Account</p>
                                <p class="text-xs opacity-90">{{ $flowName || 'My Flow' }}</p>
                            </div>
                            <button class="text-white hover:opacity-75">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z"></path>
                                </svg>
                            </button>
                        </div>

                        <!-- Message Area -->
                        <div class="flex-1 overflow-y-auto bg-gray-50 dark:bg-gray-700 p-4 space-y-3">
                            @if (empty($fields))
                                <div class="text-center py-12 text-gray-400 dark:text-gray-500">
                                    <svg class="w-8 h-8 mx-auto mb-2 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m0 0h6"></path>
                                    </svg>
                                    <p class="text-xs">No content added</p>
                                </div>
                            @else
                                @foreach ($fields as $field)
                                    <div
                                        wire:click="selectField({{ $field['id'] }})"
                                        class="cursor-pointer transition hover:opacity-80"
                                    >
                                    @switch($field['type'])
                                        {{-- Text Components --}}
                                        @case('heading')
                                            <div class="bg-white dark:bg-gray-800 rounded-lg p-3 text-left">
                                                <p class="text-base font-bold text-gray-900 dark:text-white">{{ $field['label'] }}</p>
                                            </div>
                                            @break

                                        @case('subheading')
                                            <div class="bg-white dark:bg-gray-800 rounded-lg p-3 text-left">
                                                <p class="text-sm font-semibold text-gray-900 dark:text-white">{{ $field['label'] }}</p>
                                            </div>
                                            @break

                                        @case('body')
                                            <div class="bg-white dark:bg-gray-800 rounded-lg p-3 text-left">
                                                <p class="text-sm text-gray-700 dark:text-gray-300">{{ $field['placeholder'] ?? $field['label'] ?? 'Body text' }}</p>
                                            </div>
                                            @break

                                        @case('caption')
                                            <div class="bg-white dark:bg-gray-800 rounded-lg p-3 text-left">
                                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ $field['placeholder'] ?? $field['label'] ?? 'Caption' }}</p>
                                            </div>
                                            @break

                                        @case('richtext')
                                            <div class="bg-white dark:bg-gray-800 rounded-lg p-3 text-left">
                                                <div class="text-sm text-gray-700 dark:text-gray-300 prose dark:prose-invert prose-sm">
                                                    <p>{{ $field['placeholder'] ?? $field['label'] ?? 'Rich text content' }}</p>
                                                </div>
                                            </div>
                                            @break

                                        {{-- Input Components --}}
                                        @case('text')
                                            <div class="bg-white dark:bg-gray-800 rounded-lg p-3 text-left">
                                                <p class="text-xs text-gray-500 dark:text-gray-400 mb-2">{{ $field['label'] }}</p>
                                                <input type="text" placeholder="{{ $field['placeholder'] ?? 'Enter text' }}" class="w-full px-2 py-1 text-xs border border-gray-300 dark:border-gray-600 rounded bg-gray-50 dark:bg-gray-700 text-gray-900 dark:text-white" disabled />
                                            </div>
                                            @break

                                        @case('textarea')
                                            <div class="bg-white dark:bg-gray-800 rounded-lg p-3 text-left">
                                                <p class="text-xs text-gray-500 dark:text-gray-400 mb-2">{{ $field['label'] }}</p>
                                                <textarea placeholder="{{ $field['placeholder'] ?? 'Enter text' }}" rows="2" class="w-full px-2 py-1 text-xs border border-gray-300 dark:border-gray-600 rounded bg-gray-50 dark:bg-gray-700 text-gray-900 dark:text-white" disabled></textarea>
                                            </div>
                                            @break

                                        @case('radio')
                                            <div class="bg-white dark:bg-gray-800 rounded-lg p-3 text-left">
                                                <p class="text-xs font-medium text-gray-700 dark:text-gray-300 mb-2">{{ $field['label'] }}</p>
                                                <div class="space-y-2">
                                                    @foreach ($field['options'] ?? [] as $option)
                                                        <label class="flex items-center gap-2 cursor-pointer text-xs">
                                                            <div class="w-4 h-4 rounded-full border-2 border-gray-300 dark:border-gray-600"></div>
                                                            <span class="text-gray-700 dark:text-gray-300">{{ $option['label'] }}</span>
                                                        </label>
                                                    @endforeach
                                                </div>
                                            </div>
                                            @break

                                        @case('checkbox')
                                            <div class="bg-white dark:bg-gray-800 rounded-lg p-3 text-left">
                                                <p class="text-xs font-medium text-gray-700 dark:text-gray-300 mb-2">{{ $field['label'] }}</p>
                                                <div class="space-y-2">
                                                    @foreach ($field['options'] ?? [] as $option)
                                                        <label class="flex items-center gap-2 cursor-pointer text-xs">
                                                            <input type="checkbox" class="w-4 h-4 rounded border-gray-300 dark:border-gray-600" disabled />
                                                            <span class="text-gray-700 dark:text-gray-300">{{ $option['label'] }}</span>
                                                        </label>
                                                    @endforeach
                                                </div>
                                            </div>
                                            @break

                                        @case('select')
                                            <div class="bg-white dark:bg-gray-800 rounded-lg p-3 text-left">
                                                <p class="text-xs font-medium text-gray-700 dark:text-gray-300 mb-2">{{ $field['label'] }}</p>
                                                <select class="w-full px-2 py-1 text-xs border border-gray-300 dark:border-gray-600 rounded bg-gray-50 dark:bg-gray-700 text-gray-900 dark:text-white" disabled>
                                                    <option>Select...</option>
                                                    @foreach ($field['options'] ?? [] as $option)
                                                        <option>{{ $option['label'] }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            @break

                                        @case('date')
                                            <div class="bg-white dark:bg-gray-800 rounded-lg p-3 text-left">
                                                <p class="text-xs font-medium text-gray-700 dark:text-gray-300 mb-2">{{ $field['label'] }}</p>
                                                <div class="flex items-center gap-2 px-2 py-1 text-xs border border-gray-300 dark:border-gray-600 rounded bg-gray-50 dark:bg-gray-700">
                                                    <svg class="w-3 h-3 text-gray-500 dark:text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                                                        <path fill-rule="evenodd" d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v2h16V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z" clip-rule="evenodd"></path>
                                                    </svg>
                                                    <span class="text-gray-500 dark:text-gray-400">{{ $field['placeholder'] ?? 'Select date' }}</span>
                                                </div>
                                            </div>
                                            @break

                                        @case('chips')
                                            <div class="bg-white dark:bg-gray-800 rounded-lg p-3 text-left">
                                                <p class="text-xs font-medium text-gray-700 dark:text-gray-300 mb-2">{{ $field['label'] }}</p>
                                                <div class="flex flex-wrap gap-2">
                                                    @foreach ($field['options'] ?? [] as $option)
                                                        <button class="px-3 py-1 text-xs bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-full text-blue-700 dark:text-blue-300 hover:bg-blue-100 dark:hover:bg-blue-900/30 transition">
                                                            {{ $option['label'] }}
                                                        </button>
                                                    @endforeach
                                                </div>
                                            </div>
                                            @break

                                        {{-- Media Components --}}
                                        @case('image')
                                            <div class="bg-white dark:bg-gray-800 rounded-lg p-3 text-left">
                                                <div class="w-full h-32 bg-gray-200 dark:bg-gray-600 rounded flex items-center justify-center">
                                                    <svg class="w-8 h-8 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                                    </svg>
                                                </div>
                                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-2">{{ $field['label'] ?? 'Image' }}</p>
                                            </div>
                                            @break

                                        @case('media_upload')
                                            <div class="bg-white dark:bg-gray-800 rounded-lg p-3 text-left">
                                                <p class="text-xs text-gray-500 dark:text-gray-400 mb-2">{{ $field['label'] }}</p>
                                                <div class="border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-lg p-4 text-center">
                                                    <svg class="w-6 h-6 text-gray-400 dark:text-gray-500 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                                                    </svg>
                                                    <p class="text-xs text-gray-500 dark:text-gray-400">Tap to upload</p>
                                                </div>
                                            </div>
                                            @break

                                        @case('image_carousel')
                                            <div class="bg-white dark:bg-gray-800 rounded-lg p-3 text-left">
                                                <p class="text-xs font-medium text-gray-700 dark:text-gray-300 mb-2">{{ $field['label'] }}</p>
                                                <div class="flex gap-2 overflow-x-auto">
                                                    @for($i = 1; $i <= 3; $i++)
                                                        <div class="flex-shrink-0 w-20 h-20 bg-gray-200 dark:bg-gray-600 rounded flex items-center justify-center">
                                                            <svg class="w-6 h-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                                            </svg>
                                                        </div>
                                                    @endfor
                                                </div>
                                            </div>
                                            @break

                                        {{-- Rich Content --}}
                                        @case('embedded_link')
                                            <div class="bg-white dark:bg-gray-800 rounded-lg p-3 text-left">
                                                <a href="{{ $field['url'] ?? '#' }}" class="text-blue-600 dark:text-blue-400 hover:underline text-xs font-medium">
                                                    {{ $field['button_label'] ?? $field['label'] ?? 'Open Link' }}
                                                </a>
                                            </div>
                                            @break

                                        @case('optin')
                                            <div class="bg-white dark:bg-gray-800 rounded-lg p-3 text-left">
                                                <label class="flex items-center gap-2 cursor-pointer text-xs">
                                                    <input type="checkbox" class="w-4 h-4 rounded border-gray-300 dark:border-gray-600" disabled />
                                                    <span class="text-gray-700 dark:text-gray-300">{{ $field['label'] }}</span>
                                                </label>
                                            </div>
                                            @break

                                        @case('footer')
                                            <div class="bg-gray-100 dark:bg-gray-700 rounded-lg p-2 text-center text-xs text-gray-600 dark:text-gray-400 border-t border-gray-300 dark:border-gray-600 mt-2">
                                                {{ $field['label'] ?? 'Footer' }}
                                            </div>
                                            @break

                                        {{-- Logic Components --}}
                                        @case('if_condition')
                                            <div class="bg-yellow-50 dark:bg-yellow-900/20 rounded-lg p-3 text-left border border-yellow-200 dark:border-yellow-800">
                                                <p class="text-xs font-medium text-yellow-700 dark:text-yellow-300">🔄 {{ $field['label'] ?? 'Conditional Logic' }}</p>
                                                <p class="text-xs text-yellow-600 dark:text-yellow-400 mt-1">If condition then: {{ $field['then_action'] ?? 'show' }}</p>
                                            </div>
                                            @break

                                        @case('switch')
                                            <div class="bg-yellow-50 dark:bg-yellow-900/20 rounded-lg p-3 text-left border border-yellow-200 dark:border-yellow-800">
                                                <p class="text-xs font-medium text-yellow-700 dark:text-yellow-300">🔀 {{ $field['label'] ?? 'Switch Logic' }}</p>
                                                <p class="text-xs text-yellow-600 dark:text-yellow-400 mt-1">Cases: {{ count($field['cases'] ?? []) }}</p>
                                            </div>
                                            @break

                                        {{-- Action --}}
                                        @case('button')
                                            <button class="w-full bg-green-600 hover:bg-green-700 text-white rounded-lg py-2 px-3 text-xs font-medium transition">
                                                {{ $field['label'] ?? 'Submit' }}
                                            </button>
                                            @break

                                        @default
                                            <div class="bg-gray-100 dark:bg-gray-700 rounded-lg p-3 text-left">
                                                <p class="text-xs text-gray-600 dark:text-gray-400">{{ $field['type'] }} - {{ $field['label'] }}</p>
                                            </div>
                                    @endswitch
                                    </div>
                                @endforeach
                            @endif
                        </div>

                        <!-- Chat Footer -->
                        <div class="bg-gray-100 dark:bg-gray-700 px-4 py-2 text-xs text-gray-600 dark:text-gray-400 text-center border-t border-gray-200 dark:border-gray-600">
                            Managed by the business
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Sidebar - Field Properties -->
        <div class="w-64 bg-white dark:bg-gray-800 border-l border-gray-200 dark:border-gray-700 overflow-y-auto">
            @if ($selectedField)
                <div class="p-4 border-b border-gray-200 dark:border-gray-700">
                    <h3 class="text-sm font-bold text-gray-900 dark:text-white">Field Settings</h3>
                </div>

                <div class="p-4 space-y-4">
                    <!-- Label -->
                    <div>
                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Label</label>
                        <input
                            type="text"
                            value="{{ $selectedField['label'] }}"
                            wire:change="updateFieldProperty({{ $selectedField['id'] }}, 'label', $event.target.value)"
                            class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                        />
                    </div>

                    <!-- Placeholder (if applicable) -->
                    @if (in_array($selectedField['type'], ['text', 'textarea', 'body', 'caption', 'richtext', 'date', 'embedded_link']))
                        <div>
                            <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">{{ in_array($selectedField['type'], ['body', 'caption', 'richtext']) ? 'Content' : 'Placeholder' }}</label>
                            <textarea
                                wire:change="updateFieldProperty({{ $selectedField['id'] }}, 'placeholder', $event.target.value)"
                                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                                rows="2"
                            >{{ $selectedField['placeholder'] }}</textarea>
                        </div>
                    @endif

                    <!-- Image URL (required, blocking Meta publish if empty) -->
                    @if ($selectedField['type'] === 'image')
                        @php
                            $imgSrc = $selectedField['image_url'] ?? '';
                            $isBase64 = str_starts_with($imgSrc, 'data:image');
                            $isUrl = str_starts_with($imgSrc, 'http');
                        @endphp
                        <div>
                            <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Image Source <span class="text-red-500">*</span>
                            </label>
                            <input
                                type="text"
                                value="{{ $imgSrc }}"
                                wire:change="updateFieldProperty({{ $selectedField['id'] }}, 'image_url', $event.target.value)"
                                class="w-full px-3 py-2 border {{ empty($imgSrc) ? 'border-red-400' : 'border-gray-300 dark:border-gray-600' }} rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                                placeholder="https://example.com/image.jpg or data:image/jpeg;base64,..."
                            />
                            @if (empty($imgSrc))
                                <p class="text-xs text-red-500 mt-1">⚠️ Required — image source cannot be empty</p>
                            @elseif ($isUrl)
                                <p class="text-xs text-blue-500 dark:text-blue-400 mt-1">✓ URL detected — will be auto-converted to base64 when publishing</p>
                            @elseif ($isBase64)
                                <p class="text-xs text-green-600 dark:text-green-400 mt-1">✓ Base64 image ready</p>
                            @endif
                            <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">Meta requires base64. Enter a public URL and it will be fetched &amp; converted automatically on publish.</p>
                        </div>
                    @endif

                    <!-- URL for Embedded Link -->
                    @if ($selectedField['type'] === 'embedded_link')
                        <div>
                            <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">
                                URL <span class="text-red-500">*</span>
                            </label>
                            <input
                                type="text"
                                value="{{ $selectedField['url'] ?? '' }}"
                                wire:change="updateFieldProperty({{ $selectedField['id'] }}, 'url', $event.target.value)"
                                class="w-full px-3 py-2 border {{ empty($selectedField['url'] ?? '') ? 'border-red-400' : 'border-gray-300 dark:border-gray-600' }} rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                                placeholder="https://example.com"
                            />
                            @if (empty($selectedField['url'] ?? ''))
                                <p class="text-xs text-red-500 mt-1">⚠️ Required — Meta will reject empty link URLs</p>
                            @endif
                        </div>
                    @endif

                    <!-- Image Carousel Images (required, blocking Meta publish if empty/missing src) -->
                    @if ($selectedField['type'] === 'image_carousel')
                        <div class="pt-4 border-t border-gray-200 dark:border-gray-700">
                            <div class="flex items-center justify-between mb-2">
                                <h4 class="text-xs font-bold text-gray-900 dark:text-white">
                                    Images <span class="text-red-500">*</span>
                                </h4>
                                <button
                                    wire:click="addCarouselImage({{ $selectedField['id'] }})"
                                    class="text-xs text-blue-600 dark:text-blue-400 hover:text-blue-700 dark:hover:text-blue-300 font-medium"
                                >
                                    + Add Image
                                </button>
                            </div>

                            @if (empty($selectedField['images'] ?? []))
                                <p class="text-xs text-red-500 mb-2">⚠️ Add at least one image with a valid URL</p>
                            @endif

                            <div class="space-y-3">
                                @foreach ($selectedField['images'] ?? [] as $imgIndex => $img)
                                    <div class="p-2 bg-gray-100 dark:bg-gray-700 rounded-lg">
                                        <div class="flex items-center justify-between mb-1">
                                            <span class="text-xs font-medium text-gray-600 dark:text-gray-400">Image {{ $imgIndex + 1 }}</span>
                                            <button
                                                wire:click="removeCarouselImage({{ $selectedField['id'] }}, {{ $imgIndex }})"
                                                class="text-xs text-red-500 hover:text-red-700 dark:text-red-400"
                                            >Remove</button>
                                        </div>
                                        @php
                                            $cSrc = $img['src'] ?? '';
                                            $cIsBase64 = str_starts_with($cSrc, 'data:image');
                                            $cIsUrl = str_starts_with($cSrc, 'http');
                                        @endphp
                                        <input
                                            type="text"
                                            placeholder="https://example.com/image.jpg"
                                            value="{{ $cSrc }}"
                                            wire:change="updateCarouselImage({{ $selectedField['id'] }}, {{ $imgIndex }}, 'src', $event.target.value)"
                                            class="w-full px-2 py-1 mb-1 border {{ empty($cSrc) ? 'border-red-400' : 'border-gray-300 dark:border-gray-600' }} rounded bg-white dark:bg-gray-600 text-gray-900 dark:text-white text-xs focus:outline-none focus:ring-1 focus:ring-blue-500"
                                        />
                                        @if (empty($cSrc))
                                            <p class="text-xs text-red-500 mb-1">⚠️ URL required</p>
                                        @elseif ($cIsUrl)
                                            <p class="text-xs text-blue-500 mb-1">✓ Auto-converted on publish</p>
                                        @elseif ($cIsBase64)
                                            <p class="text-xs text-green-600 mb-1">✓ Base64 ready</p>
                                        @endif
                                        <input
                                            type="text"
                                            placeholder="Alt text (optional)"
                                            value="{{ $img['alt_text'] ?? '' }}"
                                            wire:change="updateCarouselImage({{ $selectedField['id'] }}, {{ $imgIndex }}, 'alt_text', $event.target.value)"
                                            class="w-full px-2 py-1 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-gray-600 text-gray-900 dark:text-white text-xs focus:outline-none focus:ring-1 focus:ring-blue-500"
                                        />
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                    @if ($selectedField['type'] === 'select')
    @php $optionCount = count($selectedField['options'] ?? []); @endphp
    @if ($optionCount < 8)
        <p class="text-xs text-amber-500 mt-1">
            ⚠️ Meta recommends Dropdown only for 8+ options. Consider using Radio instead.
        </p>
    @endif
@endif
                    <!-- Required Toggle (for input fields) -->
                    @if (!in_array($selectedField['type'], ['heading', 'subheading', 'body', 'caption', 'richtext', 'footer', 'button', 'if_condition', 'switch', 'image', 'image_carousel']))
                        <div class="flex items-center">
                            <input
                                type="checkbox"
                                {{ $selectedField['required'] ? 'checked' : '' }}
                                wire:change="updateFieldProperty({{ $selectedField['id'] }}, 'required', $event.target.checked)"
                                id="required-{{ $selectedField['id'] }}"
                                class="w-4 h-4 rounded border-gray-300"
                            />
                            <label for="required-{{ $selectedField['id'] }}" class="ml-2 text-sm text-gray-700 dark:text-gray-300">
                                Required
                            </label>
                        </div>
                    @endif

                    <!-- Options for select, radio, checkbox, chips -->
                    @if (in_array($selectedField['type'], ['select', 'radio', 'checkbox', 'chips']))
                        <div class="pt-4 border-t border-gray-200 dark:border-gray-700">
                            <div class="flex items-center justify-between mb-3">
                                <h4 class="text-xs font-bold text-gray-900 dark:text-white">Options</h4>
                                <button
                                    wire:click="addFieldOption({{ $selectedField['id'] }})"
                                    class="text-xs text-blue-600 dark:text-blue-400 hover:text-blue-700 dark:hover:text-blue-300 font-medium"
                                >
                                    + Add
                                </button>
                            </div>

                            <div class="space-y-2">
                                @foreach ($selectedField['options'] as $option)
                                    <div class="p-2 bg-gray-100 dark:bg-gray-700 rounded-lg">
                                        <input
                                            type="text"
                                            placeholder="Option label"
                                            value="{{ $option['label'] }}"
                                            wire:change="updateFieldOption({{ $selectedField['id'] }}, '{{ $option['id'] }}', 'label', $event.target.value)"
                                            class="w-full px-2 py-1 mb-1 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-gray-600 text-gray-900 dark:text-white text-xs focus:outline-none focus:ring-2 focus:ring-blue-500"
                                        />
                                        <button
                                            wire:click="removeFieldOption({{ $selectedField['id'] }}, '{{ $option['id'] }}')"
                                            class="text-xs text-red-500 hover:text-red-700 dark:text-red-400 dark:hover:text-red-300"
                                        >
                                            Remove
                                        </button>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>
            @else
                <div class="h-full flex items-center justify-center p-4 text-center">
                    <div>
                        <p class="text-gray-500 dark:text-gray-400">No field selected</p>
                        <p class="text-xs text-gray-400 dark:text-gray-500 mt-2">Select a field to edit its properties</p>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>

@script
<script>
    function showNotification(message, type = 'success') {
        const toast = document.getElementById('notification-toast');
        const messageEl = document.getElementById('notification-message');

        toast.classList.remove('hidden', 'bg-red-500', 'bg-green-500');
        toast.classList.add(type === 'error' ? 'bg-red-500' : 'bg-green-500');
        messageEl.textContent = message;
        toast.classList.remove('hidden');

        setTimeout(() => {
            toast.classList.add('hidden');
        }, 3000);
    }

    document.addEventListener('livewire:init', function() {
        // Show notification handler
        Livewire.on('showNotification', ({ type, message }) => {
            showNotification(message, type);
        });

        Livewire.on('field-selected', (fieldId) => {
            console.log('Field selected:', fieldId);
        });

        Livewire.on('screen-selected', (screenId) => {
            console.log('Screen selected:', screenId);
        });
    });
</script>
@endscript
