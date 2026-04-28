<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WhatsApp Flow Builder</title>
    @vite(['resources/css/app.css'])
    <script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
</head>
<body class="bg-gray-50">

<div
    x-data="flowBuilder({{ $flowId ?? 'null' }})"
    x-init="init()"
    class="flex flex-col h-screen bg-gray-50 dark:bg-gray-900"
>

    {{-- Notification Toast --}}
    <div
        x-show="notification.visible"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 translate-y-2"
        x-transition:enter-end="opacity-100 translate-y-0"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        :class="notification.type === 'error' ? 'bg-red-500' : 'bg-green-500'"
        class="fixed top-4 right-4 px-6 py-3 rounded-lg text-white shadow-lg z-50 min-w-72"
        style="display:none"
    >
        <p x-text="notification.message"></p>
    </div>

    {{-- ── Top Navigation Bar ──────────────────────────────────────────────── --}}
    <div class="bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700 px-1 py-2 mt-3 flex items-center justify-between">
        <div class="flex items-center gap-4">
            <a href="/whatsapp-flows" class="text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
            </a>
            <div>
                <h1 class="text-lg font-bold text-gray-900 dark:text-white" x-text="flowId ? 'Edit Flow' : 'Create Flow'"></h1>
                <p class="text-xs text-gray-500 dark:text-gray-400">WhatsApp Flow Builder</p>
            </div>
            {{-- Dirty indicator --}}
            <span
                x-show="isDirty"
                class="flex items-center gap-1.5 text-xs text-amber-600 dark:text-amber-400 font-medium"
            >
                <span class="w-2 h-2 rounded-full bg-amber-500 inline-block animate-pulse"></span>
                Unsaved changes
            </span>
        </div>

        <div class="flex items-center gap-2">
            {{-- Import --}}
            <button
                @click="showImportModal = true"
                class="px-3 py-1.5 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-300 rounded-lg transition text-sm"
            >⬇️ Import</button>

            {{-- Encryption Keys --}}
            <a
                href="{{ route('admin.apps.company') }}#facebook_developer"
                class="px-3 py-1.5 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-300 rounded-lg transition text-sm"
            >🔑 Keys</a>

            {{-- Re-publish (only when already on Meta) --}}
            <button
                x-show="metaFlowId"
                @click="republish()"
                :disabled="saving"
                class="px-3 py-1.5 bg-green-600 hover:bg-green-700 disabled:bg-gray-400 text-white rounded-lg transition text-sm font-medium flex items-center gap-1.5"
            >
                <svg x-show="saving" class="w-3.5 h-3.5 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                </svg>
                <span x-text="saving ? 'Re-publishing...' : '↻ Re-publish'"></span>
            </button>

            {{-- Publish to Meta (first time) --}}
            <button
                x-show="!metaFlowId"
                @click="publish()"
                :disabled="saving"
                class="px-3 py-1.5 bg-purple-600 hover:bg-purple-700 disabled:bg-gray-400 text-white rounded-lg transition text-sm font-medium flex items-center gap-1.5"
            >
                <svg x-show="saving" class="w-3.5 h-3.5 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                </svg>
                <span x-text="saving ? 'Publishing...' : '🚀 Publish to Meta'"></span>
            </button>

            {{-- Save --}}
            <button
                @click="save()"
                :disabled="saving"
                class="px-3 py-1.5 bg-blue-600 hover:bg-blue-700 disabled:bg-gray-400 text-white rounded-lg transition text-sm font-medium flex items-center gap-1.5"
            >
                <svg x-show="saving" class="w-3.5 h-3.5 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                </svg>
                <span x-text="flowId ? (saving ? 'Saving...' : 'Save') : (saving ? 'Creating...' : 'Create Flow')"></span>
            </button>
        </div>
    </div>

    {{-- ── Endpoint URI Bar ────────────────────────────────────────────────── --}}
    <div class="bg-blue-50 dark:bg-blue-900/20 border-b border-blue-200 dark:border-blue-800 px-4 py-1.5 flex items-center gap-3 text-xs">
        <svg class="w-3.5 h-3.5 text-blue-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/>
        </svg>
        <span class="text-blue-800 dark:text-blue-200 font-medium flex-shrink-0">Endpoint:</span>
        <code class="text-blue-700 dark:text-blue-300 bg-blue-100 dark:bg-blue-900/40 px-2 py-0.5 rounded font-mono flex-1 truncate" x-text="endpointUrl"></code>
        <button
            @click="navigator.clipboard.writeText(endpointUrl).then(() => notify('Copied!', 'success'))"
            class="flex-shrink-0 px-2.5 py-0.5 bg-blue-600 hover:bg-blue-700 text-white rounded transition font-medium"
        >Copy</button>
        <a href="https://business.facebook.com/wa/manage/flows/" target="_blank"
           class="flex-shrink-0 text-blue-600 hover:text-blue-800 underline whitespace-nowrap">Open Meta →</a>
    </div>

    {{-- ── Loading state ───────────────────────────────────────────────────── --}}
    <div x-show="loading" class="flex-1 flex items-center justify-center">
        <div class="flex flex-col items-center gap-3 text-gray-500 dark:text-gray-400">
            <svg class="w-8 h-8 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
            </svg>
            <p class="text-sm">Loading flow...</p>
        </div>
    </div>

    {{-- Main Builder --}}
    <div x-show="!loading" class="flex flex-1 overflow-hidden">
         <div x-show="!loading" class="flex flex-1 overflow-hidden">

        {{-- ── Left: Screen Navigator ──────────────────────────────────────── --}}
        <div class="w-56 flex-1 p-4 bg-white dark:bg-gray-800 border-r border-gray-200 dark:border-gray-700 flex flex-col flex-shrink-0">
            {{-- Flow Details --}}
            <div class="w-54 bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-1 flex-shrink-0 mt-2 space-y-3">
                <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Flow Details</h3>
                <div>
                    <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Name</label>
                    <input type="text" x-model="flowName" @input="isDirty = true"
                        class="w-full px-2 py-1.5 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="My Flow"/>
                </div>
                <div>
                    <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Description</label>
                    <textarea x-model="flowDescription" @input="isDirty = true" rows="2"
                        class="w-full px-2 py-1.5 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="What is this flow for?"></textarea>
                </div>
                <div>
                    <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Category</label>
                    <select x-model="flowCategory" @change="isDirty = true"
                        class="w-full px-2 py-1.5 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="OTHER">Other</option>
                        <option value="SIGN_UP">Sign Up</option>
                        <option value="SIGN_IN">Sign In</option>
                        <option value="APPOINTMENT_BOOKING">Appointment Booking</option>
                        <option value="LEAD_GENERATION">Lead Generation</option>
                        <option value="CONTACT_US">Contact Us</option>
                        <option value="CUSTOMER_SUPPORT">Customer Support</option>
                        <option value="SURVEY">Survey</option>
                    </select>
                </div>
            </div>
            {{-- Screen list header --}}
            <div class="flex items-center justify-between px-3 py-2 border-b border-gray-200 dark:border-gray-700">
                <span class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">Screens</span>
                <button
                    @click="addScreen()"
                    class="w-5 h-5 flex items-center justify-center rounded-full bg-blue-600 hover:bg-blue-700 text-white text-sm font-bold leading-none"
                >+</button>
            </div>

            {{-- Screen list --}}
            <div class="flex-1 overflow-y-auto py-1.5 px-2 space-y-1">
                <template x-for="(screen, si) in screens" :key="screen.id">
                    <div
                        @click="selectScreen(screen.id)"
                        :class="selectedScreenId === screen.id
                            ? 'border-blue-500 bg-blue-50 dark:bg-blue-900/30'
                            : 'border-transparent hover:border-gray-300 dark:hover:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700'"
                        class="group flex items-center justify-between px-2.5 py-2 rounded-lg cursor-pointer transition border"
                    >
                        <div class="flex items-center gap-2 min-w-0">
                            <span
                                :class="selectedScreenId === screen.id ? 'bg-blue-600 text-white' : 'bg-gray-200 dark:bg-gray-600 text-gray-600 dark:text-gray-300'"
                                class="flex-shrink-0 w-5 h-5 rounded-full text-xs font-bold flex items-center justify-center"
                                x-text="si + 1"
                            ></span>
                            <span class="text-sm font-medium text-gray-900 dark:text-white truncate" x-text="screen.title"></span>
                        </div>
                        <div class="flex items-center gap-1 flex-shrink-0">
                            <span class="text-xs text-gray-400 dark:text-gray-500" x-text="(screen.fields || []).length"></span>
                            <button
                                @click.stop="removeScreen(screen.id)"
                                class="opacity-0 group-hover:opacity-100 transition-opacity text-gray-400 hover:text-red-500"
                            >
                                <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
                                </svg>
                            </button>
                        </div>
                    </div>
                </template>
                <template x-if="screens.length === 0">
                    <p class="text-xs text-gray-400 text-center py-6">No screens yet</p>
                </template>
            </div>

            {{-- Flow path --}}
            <div x-show="screens.length > 1" class="px-3 py-2 border-t border-gray-200 dark:border-gray-700">
                <p class="text-xs text-gray-400 dark:text-gray-500 truncate" x-text="screens.map(s => s.id).join(' → ')"></p>
            </div>
        </div>

        {{-- ── Center: Component list + Add palette ────────────────────────── --}}
        <div class="w-80 bg-gray-50 flex-1 dark:bg-gray-900 border-r border-gray-200 dark:border-gray-700 flex flex-col flex-shrink-0">
            <template x-if="selectedScreen">
                <div class="flex flex-col h-full">

                    {{-- Screen title --}}
                    <div class="bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700 px-3 py-2.5">
                        <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Screen Title</label>
                        <input
                            type="text"
                            :value="selectedScreen.title"
                            @blur="updateScreenTitle(selectedScreen.id, $event.target.value)"
                            class="w-full px-2 py-1.5 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-sm font-medium focus:outline-none focus:ring-2 focus:ring-blue-500"
                        />
                        <p class="text-xs text-gray-400 mt-1" x-text="`${(selectedScreen.fields||[]).length} component(s)`"></p>
                    </div>

                    {{-- Component list (top half, scrollable) --}}
                    <div class="flex flex-col border-b border-gray-200 dark:border-gray-700" style="height:45%;min-height:120px">
                        <div class="flex items-center justify-between px-3 py-2 bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700">
                            <span class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">Components</span>
                            <span class="text-xs px-1.5 py-0.5 bg-gray-100 dark:bg-gray-700 rounded-full text-gray-500" x-text="(selectedScreen.fields||[]).length"></span>
                        </div>
                        <div class="flex-1 overflow-y-auto px-2 py-1.5 space-y-1">
                            <template x-for="field in (selectedScreen.fields || [])" :key="field.id">
                                <div
                                    @click="selectField(field.id)"
                                    :class="selectedFieldId === field.id
                                        ? 'border-blue-500 bg-blue-50 dark:bg-blue-900/20'
                                        : fieldHasError(field)
                                            ? 'border-red-400 bg-red-50 dark:bg-red-900/20'
                                            : 'border-transparent bg-white dark:bg-gray-700 hover:border-gray-300'"
                                    class="group flex items-center gap-2 px-2.5 py-1.5 rounded-lg cursor-pointer transition border"
                                >
                                    <span class="text-gray-300 dark:text-gray-600 text-xs cursor-grab flex-shrink-0">⠿</span>
                                    <span class="flex-shrink-0 w-5 h-5 rounded bg-gray-100 dark:bg-gray-600 flex items-center justify-center text-xs text-gray-500" x-text="typeIcon(field.type)"></span>
                                    <span class="flex-1 text-xs font-medium text-gray-800 dark:text-gray-200 truncate" x-text="field.label || field.type"></span>
                                    <span class="text-xs text-gray-300 dark:text-gray-600 font-mono flex-shrink-0" x-text="field.type"></span>
                                    <span x-show="fieldHasError(field)" class="text-red-500 text-xs flex-shrink-0">⚠</span>
                                    <button
                                        @click.stop="removeField(field.id)"
                                        class="opacity-0 group-hover:opacity-100 text-gray-400 hover:text-red-500 flex-shrink-0"
                                    >
                                        <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
                                        </svg>
                                    </button>
                                </div>
                            </template>
                            <template x-if="(selectedScreen.fields||[]).length === 0">
                                <p class="text-xs text-gray-400 text-center py-4">No components — add from palette below</p>
                            </template>
                        </div>
                    </div>

                    {{-- Add palette (bottom half, scrollable) --}}
                    <div class="flex-1 overflow-y-auto bg-white dark:bg-gray-800">
                        <div class="px-0 py-2 border-b border-gray-200 dark:border-gray-700  top-0 bg-white dark:bg-gray-800 z-10">
                            <span class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">Add Component</span>
                        </div>

                        <template x-if="hasRichText()">
                            <div class="mx-3 mt-2 px-2.5 py-1.5 bg-amber-50 dark:bg-amber-900/20 border border-amber-300 dark:border-amber-700 rounded text-amber-700 dark:text-amber-300 text-xs">
                                RichText mode — only Footer can be added
                            </div>
                        </template>

                        <div class="px-3 py-2 space-y-3">

                            {{-- Text --}}
                            <div>
                                <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-1.5">📝 Text</p>
                                <div class="grid grid-cols-2 gap-1">
                                    <template x-for="[type, label] in [['heading','Heading'],['subheading','Subheading'],['body','Body'],['caption','Caption']]">
                                        <button
                                            @click="!hasRichText() && addField(type)"
                                            :disabled="hasRichText()"
                                            :class="hasRichText() ? 'opacity-40 cursor-not-allowed' : 'hover:bg-blue-100 dark:hover:bg-blue-900/40'"
                                            class="px-2 py-1.5 rounded-lg text-xs font-medium transition border bg-blue-50 dark:bg-blue-900/20 border-blue-200 dark:border-blue-800 text-blue-700 dark:text-blue-300 text-left"
                                            x-text="label"
                                        ></button>
                                    </template>
                                    <button
                                        @click="!canAddRichText() || addField('richtext')"
                                        :disabled="!canAddRichText()"
                                        :class="!canAddRichText() ? 'opacity-40 cursor-not-allowed' : 'hover:bg-blue-100 dark:hover:bg-blue-900/40'"
                                        class="col-span-2 px-2 py-1.5 rounded-lg text-xs font-medium transition border bg-blue-50 dark:bg-blue-900/20 border-blue-200 dark:border-blue-800 text-blue-700 dark:text-blue-300 text-left"
                                        x-text="hasRichText() ? 'RichText ✓' : 'RichText'"
                                    ></button>
                                </div>
                            </div>

                            {{-- Input --}}
                            <div>
                                <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-1.5">⌨️ Input</p>
                                <div class="grid grid-cols-2 gap-1">
                                    <template x-for="[type, label, full] in [['text','Text',false],['textarea','TextArea',false],['radio','Radio',false],['checkbox','Checkbox',false],['select','Dropdown',false],['date','Date',false],['chips','Chips',true],['optin','OptIn',true]]">
                                        <button
                                            @click="!hasRichText() && addField(type)"
                                            :disabled="hasRichText()"
                                            :class="[hasRichText() ? 'opacity-40 cursor-not-allowed' : 'hover:bg-green-100 dark:hover:bg-green-900/40', full ? 'col-span-2' : '']"
                                            class="px-2 py-1.5 rounded-lg text-xs font-medium transition border bg-green-50 dark:bg-green-900/20 border-green-200 dark:border-green-800 text-green-700 dark:text-green-300 text-left"
                                            x-text="label"
                                        ></button>
                                    </template>
                                </div>
                            </div>

                            {{-- Media --}}
                            <div>
                                <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-1.5">🖼 Media</p>
                                <div class="grid grid-cols-2 gap-1">
                                    <template x-for="[type, label, full] in [['image','Image',false],['media_upload','Upload',false],['image_carousel','Carousel',true]]">
                                        <button
                                            @click="!hasRichText() && addField(type)"
                                            :disabled="hasRichText()"
                                            :class="[hasRichText() ? 'opacity-40 cursor-not-allowed' : 'hover:bg-purple-100 dark:hover:bg-purple-900/40', full ? 'col-span-2' : '']"
                                            class="px-2 py-1.5 rounded-lg text-xs font-medium transition border bg-purple-50 dark:bg-purple-900/20 border-purple-200 dark:border-purple-800 text-purple-700 dark:text-purple-300 text-left"
                                            x-text="label"
                                        ></button>
                                    </template>
                                </div>
                            </div>

                            {{-- Actions --}}
                            <div>
                                <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-1.5">✦ Actions</p>
                                <div class="grid grid-cols-2 gap-1">
                                    <template x-for="[type, label] in [['embedded_link','Link'],['button','Button']]">
                                        <button
                                            @click="!hasRichText() && addField(type)"
                                            :disabled="hasRichText()"
                                            :class="hasRichText() ? 'opacity-40 cursor-not-allowed' : 'hover:bg-indigo-100 dark:hover:bg-indigo-900/40'"
                                            class="px-2 py-1.5 rounded-lg text-xs font-medium transition border bg-indigo-50 dark:bg-indigo-900/20 border-indigo-200 dark:border-indigo-800 text-indigo-700 dark:text-indigo-300 text-left"
                                            x-text="label"
                                        ></button>
                                    </template>
                                    <button
                                        @click="!hasFooter() && addField('footer')"
                                        :disabled="hasFooter()"
                                        :class="hasFooter() ? 'opacity-40 cursor-not-allowed' : 'hover:bg-indigo-100 dark:hover:bg-indigo-900/40'"
                                        class="col-span-2 px-2 py-1.5 rounded-lg text-xs font-medium transition border bg-indigo-50 dark:bg-indigo-900/20 border-indigo-200 dark:border-indigo-800 text-indigo-700 dark:text-indigo-300 text-left"
                                        x-text="hasFooter() ? 'Continue / Submit ✓' : 'Continue / Submit'"
                                    ></button>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>
            </template>

            <template x-if="!selectedScreen">
                <div class="flex-1 flex items-center justify-center text-gray-400 dark:text-gray-500">
                    <p class="text-sm">Select or create a screen</p>
                </div>
            </template>
        </div>

        {{-- ── Center Canvas: Phone Mockup ─────────────────────────────────── --}}
        <div class="flex-1 bg-gray-100 dark:bg-gray-900 overflow-y-auto flex items-start justify-center p-8 gap-8">



            {{-- Phone mockup --}}
            <div class="w-80 bg-black rounded-3xl shadow-2xl p-3 border-8 border-gray-800 flex-shrink-0">
                <div class="bg-white dark:bg-gray-800 rounded-2xl overflow-hidden flex flex-col" style="height:640px">

                    {{-- Status bar --}}
                    <div class="bg-gray-900 text-white px-4 py-1 flex items-center justify-between text-xs">
                        <span>9:41</span>
                        <div class="flex gap-1">
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M1 9l2 2c4.97-4.97 13.03-4.97 18 0l2-2C16.93 2.93 7.08 2.93 1 9zm8 8l3 3 3-3c-1.65-1.66-4.34-1.66-6 0zm-4-4l2 2c2.76-2.76 7.24-2.76 10 0l2-2C15.14 9.14 8.87 9.14 5 13z"/></svg>
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M15.5 1h-8C6.12 1 5 2.12 5 3.5v17C5 21.88 6.12 23 7.5 23h8c1.38 0 2.5-1.12 2.5-2.5v-17C18 2.12 16.88 1 15.5 1zm-4 21c-.83 0-1.5-.67-1.5-1.5s.67-1.5 1.5-1.5 1.5.67 1.5 1.5-.67 1.5-1.5 1.5zm4.5-4H7V4h9v14z"/></svg>
                        </div>
                    </div>

                    {{-- Chat header --}}
                    <div class="bg-green-600 text-white px-4 py-2.5 flex items-center gap-3">
                        <div class="w-9 h-9 bg-green-700 rounded-full flex items-center justify-center">
                            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.42 0-8-3.58-8-8s3.58-8 8-8 8 3.58 8 8-3.58 8-8 8z"/></svg>
                        </div>
                        <div>
                            <p class="font-medium text-sm">Business Account</p>
                            <p class="text-xs opacity-90" x-text="flowName || 'My Flow'"></p>
                        </div>
                    </div>

                    {{-- Message area --}}
                    <div class="flex-1 overflow-y-auto bg-gray-50 dark:bg-gray-700 p-3 space-y-2">
                        <template x-if="!selectedScreen || (selectedScreen.fields||[]).length === 0">
                            <div class="flex flex-col items-center justify-center h-full text-gray-400 dark:text-gray-500">
                                <p class="text-xs">No components added</p>
                            </div>
                        </template>

                        <template x-for="field in (selectedScreen ? selectedScreen.fields : [])" :key="field.id">
                            <div @click="selectField(field.id)" class="cursor-pointer hover:opacity-80 transition">

                                {{-- Heading --}}
                                <template x-if="field.type === 'heading'">
                                    <div class="bg-white dark:bg-gray-800 rounded-lg p-3">
                                        <p class="text-base font-bold text-gray-900 dark:text-white" x-text="field.label"></p>
                                    </div>
                                </template>

                                {{-- Subheading --}}
                                <template x-if="field.type === 'subheading'">
                                    <div class="bg-white dark:bg-gray-800 rounded-lg p-3">
                                        <p class="text-sm font-semibold text-gray-900 dark:text-white" x-text="field.label"></p>
                                    </div>
                                </template>

                                {{-- Body / Caption / RichText --}}
                                <template x-if="['body','caption','richtext'].includes(field.type)">
                                    <div class="bg-white dark:bg-gray-800 rounded-lg p-3">
                                        <p :class="field.type === 'caption' ? 'text-xs text-gray-500' : 'text-sm text-gray-700 dark:text-gray-300'"
                                           x-text="field.placeholder || field.label"></p>
                                    </div>
                                </template>

                                {{-- Text input --}}
                                <template x-if="field.type === 'text'">
                                    <div class="bg-white dark:bg-gray-800 rounded-lg p-3">
                                        <p class="text-xs text-gray-500 mb-1.5" x-text="field.label"></p>
                                        <div class="w-full px-2 py-1 text-xs border border-gray-300 rounded bg-gray-50 text-gray-400" x-text="field.placeholder || 'Enter text'"></div>
                                    </div>
                                </template>

                                {{-- Textarea --}}
                                <template x-if="field.type === 'textarea'">
                                    <div class="bg-white dark:bg-gray-800 rounded-lg p-3">
                                        <p class="text-xs text-gray-500 mb-1.5" x-text="field.label"></p>
                                        <div class="w-full px-2 py-2 text-xs border border-gray-300 rounded bg-gray-50 text-gray-400 h-12" x-text="field.placeholder || 'Enter text'"></div>
                                    </div>
                                </template>

                                {{-- Radio --}}
                                <template x-if="['radio','chips'].includes(field.type)">
                                    <div class="bg-white dark:bg-gray-800 rounded-lg p-3">
                                        <p class="text-xs font-medium text-gray-700 dark:text-gray-300 mb-2" x-text="field.label"></p>
                                        <div :class="field.type === 'chips' ? 'flex flex-wrap gap-1.5' : 'space-y-1.5'">
                                            <template x-for="opt in (field.options || [])">
                                                <template x-if="field.type === 'chips'">
                                                    <span class="px-2.5 py-1 text-xs bg-blue-50 border border-blue-200 rounded-full text-blue-700" x-text="opt.label"></span>
                                                </template>
                                                <template x-if="field.type !== 'chips'">
                                                    <div class="flex items-center gap-2">
                                                        <div class="w-4 h-4 rounded-full border-2 border-gray-300 flex-shrink-0"></div>
                                                        <span class="text-xs text-gray-700 dark:text-gray-300" x-text="opt.label"></span>
                                                    </div>
                                                </template>
                                            </template>
                                        </div>
                                    </div>
                                </template>

                                {{-- Checkbox --}}
                                <template x-if="field.type === 'checkbox'">
                                    <div class="bg-white dark:bg-gray-800 rounded-lg p-3">
                                        <p class="text-xs font-medium text-gray-700 dark:text-gray-300 mb-2" x-text="field.label"></p>
                                        <div class="space-y-1.5">
                                            <template x-for="opt in (field.options || [])">
                                                <div class="flex items-center gap-2">
                                                    <div class="w-4 h-4 rounded border-2 border-gray-300 flex-shrink-0"></div>
                                                    <span class="text-xs text-gray-700 dark:text-gray-300" x-text="opt.label"></span>
                                                </div>
                                            </template>
                                        </div>
                                    </div>
                                </template>

                                {{-- Select / Date --}}
                                <template x-if="['select','date'].includes(field.type)">
                                    <div class="bg-white dark:bg-gray-800 rounded-lg p-3">
                                        <p class="text-xs font-medium text-gray-700 dark:text-gray-300 mb-1.5" x-text="field.label"></p>
                                        <div class="flex items-center gap-2 px-2 py-1 text-xs border border-gray-300 rounded bg-gray-50 text-gray-400">
                                            <span x-text="field.type === 'date' ? '📅 Select date' : 'Select...'"></span>
                                        </div>
                                    </div>
                                </template>

                                {{-- OptIn --}}
                                <template x-if="field.type === 'optin'">
                                    <div class="bg-white dark:bg-gray-800 rounded-lg p-3 flex items-center gap-2">
                                        <div class="w-4 h-4 rounded border-2 border-gray-300 flex-shrink-0"></div>
                                        <span class="text-xs text-gray-700 dark:text-gray-300" x-text="field.label"></span>
                                    </div>
                                </template>

                                {{-- Image --}}
                                <template x-if="field.type === 'image'">
                                    <div class="bg-white dark:bg-gray-800 rounded-lg p-3">
                                        <div class="w-full h-28 bg-gray-200 dark:bg-gray-600 rounded flex items-center justify-center">
                                            <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                        </div>
                                    </div>
                                </template>

                                {{-- Embedded link --}}
                                <template x-if="field.type === 'embedded_link'">
                                    <div class="bg-white dark:bg-gray-800 rounded-lg p-3">
                                        <span class="text-xs text-blue-600 underline" x-text="field.button_label || field.label || 'Open Link'"></span>
                                    </div>
                                </template>

                                {{-- Footer / Button --}}
                                <template x-if="['footer','button','navigate'].includes(field.type)">
                                    <div class="bg-green-600 text-white rounded-lg py-2 px-3 text-xs font-medium text-center">
                                        <span x-text="field.label || 'Continue'"></span>
                                    </div>
                                </template>

                            </div>
                        </template>
                    </div>

                    <div class="bg-gray-100 dark:bg-gray-700 px-4 py-2 text-xs text-gray-500 text-center border-t border-gray-200 dark:border-gray-600">
                        Managed by the business
                    </div>
                </div>
            </div>
        </div>

        {{-- ── Right: Field Properties ──────────────────────────────────────── --}}
        <div class="w-64 bg-white dark:bg-gray-800 border-l border-gray-200 dark:border-gray-700 overflow-y-auto flex-shrink-0">
            <template x-if="selectedField">
                <div>
                    <div class="p-4 border-b border-gray-200 dark:border-gray-700">
                        <p class="text-xs text-gray-400 uppercase tracking-wide mb-0.5" x-text="selectedField.type"></p>
                        <h3 class="text-sm font-bold text-gray-900 dark:text-white" x-text="selectedField.label || 'Field Settings'"></h3>
                        <p class="text-xs text-gray-400 font-mono mt-0.5" x-text="`field id: ${selectedField.id}`"></p>
                    </div>

                    <div class="p-4 space-y-4">

                        {{-- Label --}}
                        <div>
                            <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Label</label>
                            <input type="text"
                                :value="selectedField.label"
                                @blur="updateField(selectedField.id, 'label', $event.target.value)"
                                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                            />
                        </div>

                        {{-- Placeholder / Content --}}
                        <template x-if="['text','textarea','body','caption','richtext','date','embedded_link'].includes(selectedField.type)">
                            <div>
                                <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1"
                                    x-text="['body','caption','richtext'].includes(selectedField.type) ? 'Content' : 'Placeholder'"></label>
                                <textarea
                                    :value="selectedField.placeholder"
                                    @blur="updateField(selectedField.id, 'placeholder', $event.target.value)"
                                    rows="2"
                                    class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                                ></textarea>
                            </div>
                        </template>

                        {{-- Image URL --}}
                        <template x-if="selectedField.type === 'image'">
                            <div>
                                <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Image URL <span class="text-red-500">*</span></label>
                                <input type="text"
                                    :value="selectedField.image_url || ''"
                                    @blur="updateField(selectedField.id, 'image_url', $event.target.value)"
                                    placeholder="https://example.com/image.jpg"
                                    :class="!selectedField.image_url ? 'border-red-400' : 'border-gray-300 dark:border-gray-600'"
                                    class="w-full px-3 py-2 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 border"
                                />
                                <p x-show="!selectedField.image_url" class="text-xs text-red-500 mt-1">⚠️ Required</p>
                                <p x-show="selectedField.image_url && selectedField.image_url.startsWith('http')" class="text-xs text-blue-500 mt-1">✓ URL — auto-converted to base64 on publish</p>
                            </div>
                        </template>

                        {{-- Embedded Link URL --}}
                        <template x-if="selectedField.type === 'embedded_link'">
                            <div>
                                <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">URL <span class="text-red-500">*</span></label>
                                <input type="text"
                                    :value="selectedField.url || ''"
                                    @blur="updateField(selectedField.id, 'url', $event.target.value)"
                                    placeholder="https://example.com"
                                    :class="!selectedField.url ? 'border-red-400' : 'border-gray-300 dark:border-gray-600'"
                                    class="w-full px-3 py-2 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 border"
                                />
                                <p x-show="!selectedField.url" class="text-xs text-red-500 mt-1">⚠️ Required</p>
                            </div>
                        </template>

                        {{-- Required toggle --}}
                        <template x-if="!['heading','subheading','body','caption','richtext','footer','button','navigate','image','image_carousel'].includes(selectedField.type)">
                            <div class="flex items-center gap-2">
                                <input type="checkbox"
                                    :checked="selectedField.required"
                                    @change="updateField(selectedField.id, 'required', $event.target.checked)"
                                    class="w-4 h-4 rounded border-gray-300"
                                />
                                <label class="text-sm text-gray-700 dark:text-gray-300">Required</label>
                            </div>
                        </template>

                        {{-- Dynamic source warning (imported fields) --}}
                        <template x-if="selectedField.imported_dynamic_source">
                            <div class="px-3 py-2 bg-amber-50 dark:bg-amber-900/20 border border-amber-300 dark:border-amber-700 rounded-lg text-amber-700 dark:text-amber-300 text-xs">
                                ⚠️ This field used dynamic data in the original flow. Update the options before publishing.
                            </div>
                        </template>

                        {{-- Options --}}
                        <template x-if="['select','radio','checkbox','chips'].includes(selectedField.type)">
                            <div class="border-t border-gray-200 dark:border-gray-700 pt-4">
                                <div class="flex items-center justify-between mb-2">
                                    <h4 class="text-xs font-bold text-gray-900 dark:text-white">Options</h4>
                                    <button @click="addOption(selectedField.id)"
                                        class="text-xs text-blue-600 dark:text-blue-400 hover:underline font-medium">+ Add</button>
                                </div>

                                <template x-if="(selectedField.options || []).length === 0">
                                    <p class="text-xs text-red-500 mb-2">⚠️ Add at least one option — Meta requires minimum 1</p>
                                </template>

                                <div class="space-y-2">
                                    <template x-for="option in (selectedField.options || [])" :key="option.id">
                                        <div class="p-2 bg-gray-100 dark:bg-gray-700 rounded-lg">
                                            <input type="text"
                                                :value="option.label"
                                                @blur="updateOption(selectedField.id, option.id, 'label', $event.target.value)"
                                                placeholder="Option label"
                                                class="w-full px-2 py-1 mb-1 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-gray-600 text-gray-900 dark:text-white text-xs focus:outline-none focus:ring-1 focus:ring-blue-500"
                                            />
                                            <button @click="removeOption(selectedField.id, option.id)"
                                                class="text-xs text-red-500 hover:text-red-700">Remove</button>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </template>

                        {{-- Carousel images --}}
                        <template x-if="selectedField.type === 'image_carousel'">
                            <div class="border-t border-gray-200 dark:border-gray-700 pt-4">
                                <div class="flex items-center justify-between mb-2">
                                    <h4 class="text-xs font-bold text-gray-900 dark:text-white">Images <span class="text-red-500">*</span></h4>
                                    <button @click="addCarouselImage(selectedField.id)"
                                        class="text-xs text-blue-600 dark:text-blue-400 hover:underline font-medium">+ Add</button>
                                </div>
                                <template x-if="(selectedField.images || []).length === 0">
                                    <p class="text-xs text-red-500 mb-2">⚠️ Add at least one image</p>
                                </template>
                                <div class="space-y-2">
                                    <template x-for="(img, idx) in (selectedField.images || [])" :key="idx">
                                        <div class="p-2 bg-gray-100 dark:bg-gray-700 rounded-lg">
                                            <div class="flex justify-between mb-1">
                                                <span class="text-xs text-gray-500" x-text="`Image ${idx+1}`"></span>
                                                <button @click="removeCarouselImage(selectedField.id, idx)"
                                                    class="text-xs text-red-500 hover:text-red-700">Remove</button>
                                            </div>
                                            <input type="text"
                                                :value="img.src"
                                                @blur="updateCarouselImage(selectedField.id, idx, 'src', $event.target.value)"
                                                placeholder="https://example.com/image.jpg"
                                                class="w-full px-2 py-1 mb-1 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-gray-600 text-gray-900 dark:text-white text-xs"
                                            />
                                            <input type="text"
                                                :value="img.alt_text"
                                                @blur="updateCarouselImage(selectedField.id, idx, 'alt_text', $event.target.value)"
                                                placeholder="Alt text (optional)"
                                                class="w-full px-2 py-1 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-gray-600 text-gray-900 dark:text-white text-xs"
                                            />
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </template>

                        {{-- Meta field name info --}}
                        <div class="border-t border-gray-200 dark:border-gray-700 pt-3">
                            <div class="px-3 py-2 bg-gray-50 dark:bg-gray-700 rounded-lg text-xs text-gray-500 dark:text-gray-400">
                                <p class="font-medium mb-1">Meta field name</p>
                                <code class="font-mono text-xs" x-text="`${selectedField.type}_${selectedField.id}`"></code>
                            </div>
                        </div>

                    </div>
                </div>
            </template>

            <template x-if="!selectedField">
                <div class="h-full flex items-center justify-center p-4 text-center">
                    <div>
                        <p class="text-gray-500 dark:text-gray-400 text-sm">No field selected</p>
                        <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">Click a component to edit</p>
                    </div>
                </div>
            </template>
        </div>

     
        </div>
    </div>
    {{-- ── Import Modal ────────────────────────────────────────────────────── --}}
        <template x-if="showImportModal">
        <div class="fixed inset-0 bg-black/50 z-40 flex items-center justify-center" @click.self="showImportModal = false">
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-2xl w-full max-w-2xl mx-4 flex flex-col max-h-[80vh]">
                <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                    <div>
                        <h2 class="text-lg font-bold text-gray-900 dark:text-white">Import Meta Flow JSON</h2>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Paste official Meta Flow JSON (v2–v7)</p>
                    </div>
                    <button @click="showImportModal = false" class="text-gray-400 hover:text-gray-600 transition">
                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
                    </button>
                </div>
                <div class="px-6 py-4 flex-1 overflow-y-auto">
                    <template x-if="importError">
                        <div class="mb-4 px-4 py-3 bg-red-50 dark:bg-red-900/20 border border-red-300 dark:border-red-700 rounded-lg text-red-700 dark:text-red-300 text-sm" x-text="importError"></div>
                    </template>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Flow JSON</label>
                    <textarea x-model="importJsonText" rows="16"
                        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-gray-50 dark:bg-gray-700 text-gray-900 dark:text-white text-xs font-mono focus:outline-none focus:ring-2 focus:ring-blue-500 resize-none"
                        placeholder='{ "version": "7.3", "screens": [...] }'></textarea>
                    <p class="text-xs text-gray-400 mt-2">💡 In Meta's Flow Builder: click JSON → Copy</p>
                </div>
                <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700 flex items-center justify-between">
                    <button @click="showImportModal = false"
                        class="px-4 py-2 text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 rounded-lg transition text-sm">Cancel</button>
                    <button @click="importJson()"
                        class="px-6 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition font-medium text-sm">
                        ⬇️ Import & Populate Builder
                    </button>
                </div>
            </div>
        </div>
        </template>
</div>

<script>
function flowBuilder(initialFlowId) {
    console.log('=== flowBuilder initialized with ID:', initialFlowId);

    return {
        flowId: initialFlowId,
        flowName: '',
        flowDescription: '',
        flowCategory: 'OTHER',
        metaFlowId: null,
        screens: [],
        selectedScreenId: null,
        selectedFieldId: null,
        isDirty: false,
        saving: false,
        loading: true,
        endpointUrl: '',
        showImportModal: false,
        importJsonText: '',
        importError: '',
        notification: { visible: false, message: '', type: 'success' },

        // ── Meta type map ─────────────────────────────────────────────────────
        metaTypeMap: {
            text: ['string', 'example text'],
            textarea: ['string', 'example text'],
            radio: ['string', 'option1'],
            select: ['string', 'option1'],
            chips: ['string', 'option1'],
            date: ['string', '2026-01-01'],
            checkbox: ['array', ['option1']],
            optin: ['boolean', true],
        },

        defaultLabels: {
            text: 'Text Input',
            textarea: 'Multi-line Text',
            radio: 'Select One',
            checkbox: 'Select Multiple',
            select: 'Dropdown',
            date: 'Select Date',
            chips: 'Quick Select',
            optin: 'I agree to terms',
            heading: 'Page Heading',
            subheading: 'Subheading',
            body: 'Body text',
            caption: 'Caption',
            richtext: 'Rich text',
            image: 'Image',
            image_carousel: 'Image Carousel',
            media_upload: 'Upload File',
            embedded_link: 'Open Link',
            footer: 'Continue',
            button: 'Submit',
        },

        typeIcons: {
            heading: 'T',
            subheading: 'T',
            body: '¶',
            caption: '¶',
            richtext: '¶',
            text: '✏',
            textarea: '✏',
            radio: '◎',
            chips: '◎',
            checkbox: '☑',
            select: '▾',
            date: '📅',
            optin: '✓',
            image: '🖼',
            image_carousel: '🖼',
            media_upload: '📎',
            embedded_link: '🔗',
            footer: '▬',
            button: '▬',
            navigate: '▬',
        },

        // ── Computed ──────────────────────────────────────────────────────────
        get selectedScreen() {
            return this.screens.find(s => s.id === this.selectedScreenId) || null;
        },

        get selectedField() {
            if (!this.selectedScreen || this.selectedFieldId === null) return null;
            return (this.selectedScreen.fields || []).find(f => f.id === this.selectedFieldId) || null;
        },

        async init() {
            console.log('🚀 ALPINE INIT() STARTED with flowId =', this.flowId);
            this.loading = true;

            if (this.flowId && this.flowId !== 'null' && this.flowId !== null) {
                console.log('📡 Making API call to /api/flow-builder/' + this.flowId);

                try {
                    const data = await this.api('GET', `/api/flow-builder/${this.flowId}`);
                    console.log('✅ API Response received:', data);

                    const f = data.flow || data;
                    this.flowName = f.name || '';
                    this.flowDescription = f.description || '';
                    this.flowCategory = f.category || 'OTHER';
                    this.metaFlowId = f.meta_flow_id || null;
                    this.screens = Array.isArray(f.screens) ? f.screens : [];

                    if (this.screens.length > 0) {
                        this.selectedScreenId = this.screens[0].id;
                    }

                    console.log('✅ SUCCESS: Flow data loaded! Screens count:', this.screens.length);
                } catch (e) {
                    console.error('❌ API call failed:', e);
                    this.notify('Failed to load flow', 'error');
                }
            } else {
                console.log('🆕 Create mode - no API call');
            }

            this.loading = false;
            console.log('🏁 init() COMPLETED - loading = false');
        },

        async loadFlow() {
            try {
                const data = await this.api('GET', `/api/flow-builder/${this.flowId}`);
                console.log('✅ API Response:', data);

                const f = data.flow;
                this.flowName = f.name || '';
                this.flowDescription = f.description || '';
                this.flowCategory = f.category || 'OTHER';
                this.metaFlowId = f.meta_flow_id || null;
                this.screens = Array.isArray(f.screens) ? f.screens : [];

                if (this.screens.length > 0) {
                    this.selectedScreenId = this.screens[0].id;
                }

                console.log('✅ Flow data loaded successfully! Screens:', this.screens.length);
            } catch (e) {
                console.error('❌ Failed to load flow:', e);
            } finally {
                this.loading = false;
            }
        },

        async api(method, url, body = null) {
            const opts = {
                method,
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                },
            };

            if (body) opts.body = JSON.stringify(body);

            const res = await fetch(url, opts);
            const data = await res.json();

            if (!res.ok) throw new Error(data.message || `HTTP ${res.status}`);

            return data;
        },

        notify(message, type = 'success') {
            console.log(`Notification: ${message}`);
        },

        // ── Screens ───────────────────────────────────────────────────────────
        addScreen() {
            const count = this.screens.length + 1;
            const id = 'SCREEN_' + String.fromCharCode(64 + count);

            this.screens.push({
                id,
                title: 'Screen ' + count,
                fields: [],
            });

            this.selectedScreenId = id;
            this.selectedFieldId = null;
            this.isDirty = true;
        },

        removeScreen(id) {
            this.screens = this.screens.filter(s => s.id !== id);

            if (this.selectedScreenId === id) {
                this.selectedScreenId = this.screens[0]?.id || null;
                this.selectedFieldId = null;
            }

            this.isDirty = true;
        },

        selectScreen(id) {
            // Pure client-side — zero network calls
            this.selectedScreenId = id;
            this.selectedFieldId = null;
        },

        updateScreenTitle(id, title) {
            const screen = this.screens.find(s => s.id === id);

            if (screen) {
                screen.title = title;
                this.isDirty = true;
            }
        },

        // ── Fields ────────────────────────────────────────────────────────────
        nextFieldId() {
            let max = 0;
            this.screens.forEach(s => (s.fields||[]).forEach(f => { if (f.id > max) max = f.id; }));
            return max + 1;
        },

        addField(type) {
            if (!this.selectedScreenId) return;
            const screen = this.screens.find(s => s.id === this.selectedScreenId);
            if (!screen) return;

            const [metaType, metaExample] = this.metaTypeMap[type] || ['string', 'example'];
            const field = {
                id:           this.nextFieldId(),
                type,
                label:        this.defaultLabels[type] || type,
                placeholder:  '',
                required:     false,
                meta_type:    metaType,
                meta_example: metaExample,
            };

            if (type === 'image')          { field.image_url = ''; field.height = 300; field.scale_type = 'contain'; }
            if (type === 'image_carousel') { field.images = []; }
            if (type === 'embedded_link')  { field.url = ''; field.button_label = 'Open Link'; }
            if (['radio','checkbox','select','chips'].includes(type)) {
                field.options = [
                    { id: this.uuid(), label: 'Option 1', value: 'option_1' },
                    { id: this.uuid(), label: 'Option 2', value: 'option_2' },
                ];
            }

            // Deep copy to avoid cross-screen reference issues
            screen.fields.push(JSON.parse(JSON.stringify(field)));
            this.selectedFieldId = field.id;
            this.isDirty = true;
        },

        removeField(id) {
            const screen = this.screens.find(s => s.id === this.selectedScreenId);
            if (!screen) return;
            screen.fields = screen.fields.filter(f => f.id !== id);
            if (this.selectedFieldId === id) this.selectedFieldId = null;
            this.isDirty = true;
        },

        selectField(id) {
            // Pure client-side — zero network calls
            this.selectedFieldId = id;
        },

        updateField(fieldId, prop, value) {
            for (const screen of this.screens) {
                const field = (screen.fields || []).find(f => f.id === fieldId);
                if (field) { field[prop] = value; this.isDirty = true; return; }
            }
        },

        // ── Options ───────────────────────────────────────────────────────────
        addOption(fieldId) {
            const field = this.findField(fieldId);
            if (!field) return;
            const n = (field.options || []).length + 1;
            if (!field.options) field.options = [];
            field.options.push({ id: this.uuid(), label: 'Option ' + n, value: 'option_' + n });
            this.isDirty = true;
        },

        removeOption(fieldId, optId) {
            const field = this.findField(fieldId);
            if (!field) return;
            field.options = field.options.filter(o => o.id !== optId);
            this.isDirty = true;
        },

        updateOption(fieldId, optId, prop, value) {
            const field = this.findField(fieldId);
            if (!field) return;
            const opt = (field.options || []).find(o => o.id === optId);
            if (opt) {
                opt[prop] = value;
                if (prop === 'label') opt.value = value.toLowerCase().replace(/\s+/g, '_') || optId;
                this.isDirty = true;
            }
        },

           // ── Carousel ──────────────────────────────────────────────────────────
           addCarouselImage(fieldId) {
            const field = this.findField(fieldId);
            if (!field) return;
            if (!field.images) field.images = [];
            field.images.push({ src: '', alt_text: '' });
            this.isDirty = true;
        },

        removeCarouselImage(fieldId, idx) {
            const field = this.findField(fieldId);
            if (!field) return;
            field.images.splice(idx, 1);
            this.isDirty = true;
        },

        updateCarouselImage(fieldId, idx, prop, value) {
            const field = this.findField(fieldId);
            if (!field || !field.images[idx]) return;
            field.images[idx][prop] = value;
            this.isDirty = true;
        },

          // ── Palette helpers ───────────────────────────────────────────────────
          currentFieldTypes() {
            return (this.selectedScreen?.fields || []).map(f => f.type);
        },
        hasRichText()   { return this.currentFieldTypes().includes('richtext'); },
        hasFooter()     { return this.currentFieldTypes().some(t => ['footer','button','navigate'].includes(t)); },
        canAddRichText(){ return !this.hasRichText() && !this.currentFieldTypes().some(t => !['richtext','footer','button','navigate'].includes(t)); },

        // ── Validation helpers ────────────────────────────────────────────────
        fieldHasError(field) {
            if (field.type === 'image')          return !field.image_url;
            if (field.type === 'embedded_link')  return !field.url;
            if (field.type === 'image_carousel') return !(field.images||[]).length || (field.images||[]).some(i => !i.src);
            if (['radio','checkbox','select','chips'].includes(field.type)) return !(field.options||[]).length;
            return false;
        },
        typeIcon(type) { return this.typeIcons[type] || '·'; },

        // ── Save / Publish ────────────────────────────────────────────────────
        async save() {
            if (!this.flowName.trim()) { this.notify('Flow name is required.', 'error'); return; }
            this.saving = true;
            try {
                const payload = {
                    name:        this.flowName,
                    description: this.flowDescription,
                    category:    this.flowCategory,
                    screens:     this.screens,
                };

                if (this.flowId) {
                    await this.api('PUT', `/api/flow-builder/${this.flowId}`, payload);
                } else {
                    const r = await this.api('POST', '/api/flow-builder', payload);
                    this.flowId = r.flow_id;
                    // Update URL without page reload
                    window.history.replaceState({}, '', `/whatsapp-flows/${this.flowId}/edit`);
                }

                this.isDirty = false;
                this.notify('Flow saved successfully.', 'success');
            } catch(e) {
                this.notify(e.message || 'Save failed.', 'error');
            } finally {
                this.saving = false;
            }
        },

        async publish() {
            if (!this.flowName.trim()) { this.notify('Flow name is required.', 'error'); return; }
            this.saving = true;
            try {
                const r = await this.api('POST', `/api/flow-builder/${this.flowId || ''}/publish`, {
                    name: this.flowName, description: this.flowDescription,
                    category: this.flowCategory, screens: this.screens,
                });
                this.metaFlowId = r.meta_flow_id;
                this.isDirty    = false;
                this.notify('Flow published to Meta!', 'success');
            } catch(e) {
                this.notify(e.message || 'Publish failed.', 'error');
            } finally {
                this.saving = false;
            }
        },

        async republish() {
            this.saving = true;
            try {
                await this.api('POST', `/api/flow-builder/${this.flowId}/republish`, {
                    name: this.flowName, screens: this.screens,
                });
                this.isDirty = false;
                this.notify('Flow re-published successfully!', 'success');
            } catch(e) {
                this.notify(e.message || 'Re-publish failed.', 'error');
            } finally {
                this.saving = false;
            }
        },

                // ── Import ────────────────────────────────────────────────────────────
                importJson() {
            this.importError = '';
            let decoded;
            try { decoded = JSON.parse(this.importJsonText); }
            catch { this.importError = 'Invalid JSON.'; return; }

            if (!decoded.screens?.length) { this.importError = 'No screens found.'; return; }

            let nextId = this.nextFieldId();
            const typeMap = {
                TextInput: 'text', TextArea: 'textarea', RadioButtonsGroup: 'radio',
                CheckboxGroup: 'checkbox', Dropdown: 'select', DatePicker: 'date',
                OptIn: 'optin', TextHeading: 'heading', TextSubheading: 'subheading',
                TextBody: 'body', TextCaption: 'caption', RichText: 'richtext',
                Image: 'image', ImageCarousel: 'image_carousel',
                EmbeddedLink: 'embedded_link', Footer: 'footer',
            };

            const parseComponent = (c) => {
                const builderType = typeMap[c.type];
                if (!builderType) return null;
                const [mt, me] = this.metaTypeMap[builderType] || ['string', 'example'];
                const field = {
                    id: nextId++, type: builderType,
                    label: c.label || c.text || this.defaultLabels[builderType] || builderType,
                    placeholder: '', required: !!c.required,
                    meta_type: mt, meta_example: me,
                };
                if (['radio','checkbox','select'].includes(builderType)) {
                    const ds = Array.isArray(c['data-source']) ? c['data-source'] : [];
                    field.options = ds.length
                        ? ds.map(s => ({ id: s.id || this.uuid(), label: s.title || s.id || 'Option', value: s.id || 'option' }))
                        : [{ id: this.uuid(), label: 'Option 1', value: 'option_1' }];
                    if (!ds.length) field.imported_dynamic_source = true;
                }
                if (builderType === 'image')          { field.image_url = c.src || ''; field.height = c.height || 300; field.scale_type = c['scale-type'] || 'contain'; }
                if (builderType === 'image_carousel') { field.images = (c.images||[]).map(i => ({ src: i.src || '', alt_text: i['alt-text'] || '' })); }
                if (builderType === 'embedded_link')  { field.url = c['on-click-action']?.payload?.url || ''; field.button_label = c.text || 'Open Link'; }
                if (builderType === 'footer')         { field.label = c.label || 'Continue'; }
                return field;
            };

            this.screens = decoded.screens.map(ms => ({
                id:     ms.id || 'SCREEN_' + Math.random().toString(36).slice(2,6).toUpperCase(),
                title:  ms.title || 'Imported Screen',
                fields: (ms.layout?.children || []).flatMap(c =>
                    c.type === 'Form'
                        ? (c.children || []).map(parseComponent).filter(Boolean)
                        : [parseComponent(c)].filter(Boolean)
                ),
            }));

            this.selectedScreenId = this.screens[0]?.id || null;
            this.selectedFieldId  = null;
            this.showImportModal  = false;
            this.importJsonText   = '';
            this.isDirty          = true;
            this.notify(`Imported ${this.screens.length} screen(s). Review and save.`, 'success');
        },

        // ── Helpers ───────────────────────────────────────────────────────────
        findField(fieldId) {
            for (const screen of this.screens) {
                const f = (screen.fields || []).find(f => f.id === fieldId);
                if (f) return f;
            }
            return null;
        },

        uuid() {
            return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, c => {
                const r = Math.random() * 16 | 0;
                return (c === 'x' ? r : (r & 0x3 | 0x8)).toString(16);
            });
        },

        notify(message, type = 'success') {
            this.notification = { visible: true, message, type };
            setTimeout(() => { this.notification.visible = false; }, 3500);
        },




    };
}
</script>

</body>
</html>