<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WhatsApp Form Builder</title>
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
        @click="notification.visible = false"
        :class="notification.type === 'error' ? 'bg-red-500' : 'bg-green-500'"
        class="fixed top-4 right-4 px-6 py-4 rounded-lg text-white shadow-lg z-50 min-w-72 max-w-lg cursor-pointer"
        style="display:none"
        title="Click to dismiss"
    >
        <p class="text-sm leading-relaxed whitespace-pre-wrap" x-text="notification.message"></p>
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
                <h1 class="text-lg font-bold text-gray-900 dark:text-white" x-text="flowId ? 'Edit Form' : 'Create Form'"></h1>
                <p class="text-xs text-gray-500 dark:text-gray-400">WhatsApp Form Builder</p>
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

        <div class="flex items-center gap-4">
            {{-- Lifecycle badge --}}
            <span
                class="px-2.5 py-1 rounded-full text-xs font-medium"
                :class="metaFlowId
                    ? 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-200'
                    : (readiness.can_publish
                        ? 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-200'
                        : 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-200')"
                x-text="metaFlowId ? 'Live on WhatsApp' : (readiness.can_publish ? 'Ready to publish' : 'Draft')"
            ></span>

            <button
                @click="showReadiness = !showReadiness; if (showReadiness) loadReadiness()"
                class="px-3 py-1.5 bg-indigo-50 dark:bg-indigo-900/30 hover:bg-indigo-100 text-indigo-800 dark:text-indigo-200 rounded-lg transition text-sm font-medium"
            >Live checklist</button>

            {{-- Templates --}}
            <button
                @click="openTemplatesModal()"
                class="px-3 py-1.5 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-300 rounded-lg transition text-sm"
            >Templates</button>

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
                <span x-text="saving ? 'Publishing...' : 'Go Live on WhatsApp'"></span>
            </button>

            {{-- Validate --}}
            <button
                @click="runValidation()"
                :disabled="checking"
                class="px-3 py-1.5 bg-red-400 dark:bg-amber-900/30 hover:bg-amber-200 disabled:opacity-60 disabled:cursor-not-allowed text-amber-800 dark:text-amber-200 rounded-lg transition text-sm font-medium flex items-center gap-1.5"
            >
                <svg x-show="checking" class="w-3.5 h-3.5 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                </svg>
                <span x-text="checking ? 'Checking...' : '✓ Check'"></span>
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

    {{-- ── Live readiness checklist ──────────────────────────────────────── --}}
    <div
        x-show="showReadiness"
        x-cloak
        class="bg-indigo-50 dark:bg-indigo-950/40 border-b border-indigo-200 dark:border-indigo-800 px-4 py-3"
    >
        <div class="flex items-start justify-between gap-4">
            <div class="flex-1">
                <div class="text-sm font-semibold text-indigo-900 dark:text-indigo-100 mb-2">
                    Go Live checklist
                    <span class="font-normal text-indigo-700 dark:text-indigo-300" x-text="`(${readiness.completed || 0}/${readiness.total || 0})`"></span>
                </div>
                <div class="grid gap-2 md:grid-cols-2">
                    <template x-for="step in (readiness.steps || [])" :key="step.key">
                        <div class="flex items-start gap-2 text-xs bg-white/70 dark:bg-gray-900/40 rounded-lg p-2 border border-indigo-100 dark:border-indigo-900">
                            <span
                                class="mt-0.5 inline-flex h-4 w-4 items-center justify-center rounded-full text-[10px] font-bold"
                                :class="step.completed ? 'bg-green-500 text-white' : 'bg-gray-300 text-gray-700'"
                                x-text="step.completed ? '✓' : '·'"
                            ></span>
                            <div>
                                <div class="font-medium text-gray-900 dark:text-white" x-text="step.title"></div>
                                <div class="text-gray-600 dark:text-gray-400" x-text="step.help"></div>
                            </div>
                        </div>
                    </template>
                </div>
                <div class="mt-2 flex flex-wrap gap-2 text-xs">
                    <a href="{{ route('admin.apps.company') }}#facebook_developer" class="text-indigo-700 underline">Setup keys & credentials</a>
                    <button
                        x-show="!metaFlowId && readiness.can_publish"
                        @click="publish()"
                        class="px-2 py-1 bg-indigo-600 text-white rounded font-medium"
                    >Go Live now</button>
                    <span x-show="metaFlowId" class="text-green-700 font-medium">This form is Live — use it in Automations.</span>
                </div>
            </div>
            <button @click="showReadiness = false" class="text-indigo-500 hover:text-indigo-800 text-sm">Close</button>
        </div>
    </div>

    {{-- ── Submission Webhook Bar ─────────────────────────────────────────── --}}
    <div class="bg-emerald-50 dark:bg-emerald-900/20 border-b border-emerald-200 dark:border-emerald-800 px-4 py-2 flex flex-wrap items-center gap-3 text-xs">
        <span class="text-emerald-800 dark:text-emerald-200 font-medium">Submission webhook:</span>
        <label class="inline-flex items-center gap-1.5 text-emerald-900 dark:text-emerald-100">
            <input type="checkbox" x-model="webhookEnabled" @change="isDirty = true" class="rounded">
            Enabled
        </label>
        <input type="url" x-model="webhookUrl" @input="isDirty = true" placeholder="https://hooks.example.com/form-submissions"
            class="flex-1 min-w-[16rem] px-2 py-1 border border-emerald-300 dark:border-emerald-700 rounded bg-white dark:bg-gray-800 text-gray-900 dark:text-white"/>
        <span class="text-emerald-700 dark:text-emerald-300" x-show="lastAutosavedAt">Auto-saved <span x-text="lastAutosavedAt?.toLocaleTimeString?.() || ''"></span></span>
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
    <div x-show="!loading" class="flex flex-1 overflow-hidden min-h-0">
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
                    <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Category (required by WhatsApp)</label>
                    <select x-model="flowCategory" @change="isDirty = true"
                        class="w-full px-2 py-1.5 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="OTHER">Other</option>
                        <option value="SIGN_UP">Sign Up</option>
                        <option value="SIGN_IN">Sign In</option>
                        <option value="APPOINTMENT_BOOKING">Appointment Booking</option>
                        <option value="LEAD_GENERATION">Lead Generation</option>
                        <option value="SHOPPING">Shopping</option>
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
            <div class="flex-1 min-h-[200px] overflow-y-auto py-1.5 px-2 space-y-1">
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
                            <span x-show="screen.terminal" class="text-[10px] px-1 py-0.5 rounded bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300 font-medium flex-shrink-0">Submit</span>
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

            {{-- Screen templates removed: use Flowmaker "Book appointment" node instead --}}
        
            {{-- Validation results --}}
            <div x-show="validationResults.errors.length || validationResults.warnings.length" class="px-3 py-2 flex-1 border-t border-gray-200 dark:border-gray-700 text-xs space-y-1 max-h-32 overflow-y-auto">
                <template x-for="(err, i) in validationResults.errors" :key="'e'+i">
                    <p class="text-red-600 dark:text-red-400" x-text="'⚠ ' + err"></p>
                </template>
                <template x-for="(warn, i) in validationResults.warnings" :key="'w'+i">
                    <p class="text-amber-600 dark:text-amber-400" x-text="'💡 ' + warn"></p>
                </template>
            </div>
        </div>

        {{-- ── Center: Component list + Add palette ────────────────────────── --}}
        <div class="w-80 bg-gray-50 flex-1 dark:bg-gray-900 border-r border-gray-200 dark:border-gray-700 flex flex-col flex-shrink-0 min-h-0">
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
                        <div class="flex flex-wrap gap-2 mt-2">
                            <label class="flex items-center gap-1 text-xs text-gray-500" title="Only one screen can be terminal (submit screen)">
                                <input type="checkbox" :checked="!!selectedScreen.terminal" @change="setScreenProp(selectedScreen.id, 'terminal', $event.target.checked)" class="rounded"/>
                                Terminal (submit screen)
                            </label>
                            <label class="flex items-center gap-1 text-xs text-gray-500">
                                <input type="checkbox" :checked="!!selectedScreen.refresh_on_back" @change="setScreenProp(selectedScreen.id, 'refresh_on_back', $event.target.checked)" class="rounded"/>
                                Refresh on back
                            </label>
                        </div>
                        <p class="text-xs text-gray-400 mt-1" x-text="`${countScreenComponents(selectedScreen)} / 50 components`"></p>
                        <p x-show="selectedScreen.endpoint_template" class="text-xs text-blue-600 dark:text-blue-400 mt-1 font-mono" x-text="`Endpoint template: ${selectedScreen.endpoint_template}`"></p>
                    </div>

                    <!-- {{-- Dynamic data (endpoint) --}}
                    <div class="border-b border-gray-200 dark:border-gray-700 px-3 py-2 bg-blue-50/50 dark:bg-blue-900/10">
                        <div class="flex items-center justify-between mb-1.5">
                            <span class="text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase">Dynamic data</span>
                            <button @click="addDynamicDataEntry(selectedScreen.id)" class="text-xs text-blue-600 hover:underline">+ Add key</button>
                        </div>
                        <p class="text-[10px] text-gray-500 mb-2">Bind in components as <code class="font-mono">${data.key}</code>. Filled by your endpoint on INIT / data_exchange.</p>
                        <template x-if="!(selectedScreen.dynamic_data || []).length">
                            <p class="text-xs text-gray-400 italic">No dynamic keys — add keys or use the booking template.</p>
                        </template>
                        <div class="space-y-2 max-h-36 overflow-y-auto">
                            <template x-for="(entry, idx) in (selectedScreen.dynamic_data || [])" :key="idx">
                                <div class="p-2 bg-white dark:bg-gray-800 rounded border border-gray-200 dark:border-gray-600 space-y-1">
                                    <div class="flex gap-1">
                                        <input type="text" :value="entry.key" @blur="updateDynamicDataEntry(selectedScreen.id, idx, 'key', $event.target.value)"
                                            placeholder="key e.g. available_slots" class="flex-1 px-1.5 py-1 text-xs border rounded font-mono"/>
                                        <select :value="entry.type" @change="updateDynamicDataEntry(selectedScreen.id, idx, 'type', $event.target.value)" class="px-1 py-1 text-xs border rounded">
                                            <option value="string">string</option>
                                            <option value="boolean">boolean</option>
                                            <option value="number">number</option>
                                            <option value="option_list">option list</option>
                                        </select>
                                        <button @click="removeDynamicDataEntry(selectedScreen.id, idx)" class="text-red-500 text-xs px-1">✕</button>
                                    </div>
                                    <p class="text-[10px] text-gray-400 font-mono" x-text="`\${data.${entry.key || 'key'}}`"></p>
                                </div>
                            </template>
                        </div>
                    </div> -->

                    {{-- Component list (top half, scrollable) --}}
                    <div class="flex-1 min-h-[200px] overflow-y-auto py-1.5 px-2 space-y-1">
                        <div class="flex items-center justify-between px-3 py-2 bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700">
                            <span class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">Components</span>
                            <span class="text-xs px-1.5 py-0.5 bg-gray-100 dark:bg-gray-700 rounded-full text-gray-500" x-text="(selectedScreen.fields||[]).length"></span>
                        </div>
                        <div class="flex-1 overflow-y-auto px-2 py-1.5 space-y-1">
                            <template x-for="(field, fieldIndex) in (selectedScreen.fields || [])" :key="'field-' + selectedScreen.id + '-' + field.id + '-' + fieldIndex">
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
                                    <span x-show="['radio','checkbox','select','chips'].includes(field.type) && (field.options||[]).length"
                                        class="text-[10px] text-blue-600 dark:text-blue-400 flex-shrink-0"
                                        x-text="`${(field.options||[]).length} opts`"></span>
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
                                    <template x-for="[type, label, full] in [['text','Text',false],['textarea','TextArea',false],['radio','Radio',false],['checkbox','Checkbox',false],['select','Dropdown',false],['date','Date',false],['calendar','Calendar',false],['chips','Chips',false],['optin','OptIn',false]]">
                                        <button
                                            @click="canAddField(type) && addField(type)"
                                            :disabled="!canAddField(type)"
                                            :class="[!canAddField(type) ? 'opacity-40 cursor-not-allowed' : 'hover:bg-green-100 dark:hover:bg-green-900/40', full ? 'col-span-2' : '']"
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
                                    <template x-for="[type, label, full] in [['image','Image',false],['photo_picker','Photo',false],['document_picker','Document',false],['image_carousel','Carousel',true]]">
                                        <button
                                            @click="canAddField(type) && addField(type)"
                                            :disabled="!canAddField(type)"
                                            :class="[!canAddField(type) ? 'opacity-40 cursor-not-allowed' : 'hover:bg-purple-100 dark:hover:bg-purple-900/40', full ? 'col-span-2' : '']"
                                            class="px-2 py-1.5 rounded-lg text-xs font-medium transition border bg-purple-50 dark:bg-purple-900/20 border-purple-200 dark:border-purple-800 text-purple-700 dark:text-purple-300 text-left"
                                            x-text="label"
                                        ></button>
                                    </template>
                                </div>
                            </div>

                            {{-- Logic & Nav --}}
                            <div>
                                <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-1.5">⚡ Logic & Nav</p>
                                <div class="grid grid-cols-2 gap-1">
                                    <template x-for="[type, label] in [['navigation_list','Nav List'],['if_condition','If'],['switch','Switch']]">
                                        <button
                                            @click="canAddField(type) && addField(type)"
                                            :disabled="!canAddField(type)"
                                            :class="!canAddField(type) ? 'opacity-40 cursor-not-allowed' : 'hover:bg-orange-100 dark:hover:bg-orange-900/40'"
                                            class="px-2 py-1.5 rounded-lg text-xs font-medium transition border bg-orange-50 dark:bg-orange-900/20 border-orange-200 dark:border-orange-800 text-orange-700 dark:text-orange-300 text-left"
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

                        <template x-for="(field, fieldIndex) in (selectedScreen ? selectedScreen.fields : [])" :key="'preview-' + (selectedScreen?.id || '') + '-' + field.id + '-' + fieldIndex">
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
                                            <template x-for="opt in (field.options || [])" :key="opt.id">
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
                                            <template x-for="opt in (field.options || [])" :key="opt.id">
                                                <div class="flex items-center gap-2">
                                                    <div class="w-4 h-4 rounded border-2 border-gray-300 flex-shrink-0"></div>
                                                    <span class="text-xs text-gray-700 dark:text-gray-300" x-text="opt.label"></span>
                                                </div>
                                            </template>
                                        </div>
                                    </div>
                                </template>

                                {{-- Select --}}
                                <template x-if="field.type === 'select'">
                                    <div class="bg-white dark:bg-gray-800 rounded-lg p-3">
                                        <p class="text-xs font-medium text-gray-700 dark:text-gray-300 mb-1.5" x-text="field.label"></p>
                                        <template x-if="(field.options || []).length">
                                            <div class="space-y-1">
                                                <div class="flex items-center gap-2 px-2 py-1 text-xs border border-gray-300 rounded bg-gray-50 text-gray-500">
                                                    <span x-text="(field.options[0] || {}).label || 'Select...'"></span>
                                                    <span class="ml-auto text-gray-400">▾</span>
                                                </div>
                                                <template x-if="field.options.length > 1">
                                                    <div class="pl-2 space-y-0.5">
                                                        <template x-for="opt in field.options.slice(1)" :key="opt.id">
                                                            <p class="text-[10px] text-gray-400 truncate" x-text="opt.label"></p>
                                                        </template>
                                                    </div>
                                                </template>
                                            </div>
                                        </template>
                                        <template x-if="!(field.options || []).length">
                                            <div class="flex items-center gap-2 px-2 py-1 text-xs border border-dashed border-gray-300 rounded bg-gray-50 text-gray-400">
                                                <span x-text="field.dynamic_data_source ? 'Dynamic options (endpoint)' : 'No options yet'"></span>
                                            </div>
                                        </template>
                                    </div>
                                </template>

                                {{-- Date --}}
                                <template x-if="field.type === 'date'">
                                    <div class="bg-white dark:bg-gray-800 rounded-lg p-3">
                                        <p class="text-xs font-medium text-gray-700 dark:text-gray-300 mb-1.5" x-text="field.label"></p>
                                        <div class="flex items-center gap-2 px-2 py-1 text-xs border border-gray-300 rounded bg-gray-50 text-gray-400">
                                            <span>📅 Select date</span>
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

                        {{-- TextInput type & helper --}}
                        <template x-if="selectedField.type === 'text'">
                            <div class="space-y-2 border-t border-gray-200 dark:border-gray-700 pt-3">
                                <div>
                                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Input type</label>
                                    <select :value="selectedField.input_type || 'text'" @change="updateField(selectedField.id, 'input_type', $event.target.value)"
                                        class="w-full px-2 py-1.5 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-sm">
                                        <option value="text">Text</option>
                                        <option value="email">Email</option>
                                        <option value="phone">Phone</option>
                                        <option value="number">Number</option>
                                        <option value="password">Password</option>
                                        <option value="passcode">Passcode</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Helper text</label>
                                    <input type="text" :value="selectedField.helper_text || ''" @blur="updateField(selectedField.id, 'helper_text', $event.target.value)"
                                        class="w-full px-2 py-1.5 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-sm"/>
                                </div>
                                <label class="flex items-center gap-2 text-sm" x-show="['password','passcode'].includes(selectedField.input_type)">
                                    <input type="checkbox" :checked="!!selectedField.sensitive" @change="updateField(selectedField.id, 'sensitive', $event.target.checked)" class="rounded"/>
                                    Sensitive (hide from response summary)
                                </label>
                            </div>
                        </template>

                        {{-- Markdown toggle --}}
                        <template x-if="['body','caption'].includes(selectedField.type)">
                            <label class="flex items-center gap-2 text-sm border-t border-gray-200 dark:border-gray-700 pt-3">
                                <input type="checkbox" :checked="!!selectedField.markdown" @change="updateField(selectedField.id, 'markdown', $event.target.checked)" class="rounded"/>
                                Enable markdown
                            </label>
                        </template>

                        {{-- OptIn read more --}}
                        <template x-if="selectedField.type === 'optin'">
                            <div class="border-t border-gray-200 dark:border-gray-700 pt-3">
                                <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Read more URL (Terms)</label>
                                <input type="text" :value="selectedField.read_more_url || ''" @blur="updateField(selectedField.id, 'read_more_url', $event.target.value)"
                                    placeholder="https://example.com/terms" class="w-full px-2 py-1.5 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-sm"/>
                            </div>
                        </template>

                        {{-- Calendar --}}
                        <template x-if="selectedField.type === 'calendar'">
                            <div class="space-y-2 border-t border-gray-200 dark:border-gray-700 pt-3">
                                <div>
                                    <label class="block text-xs font-medium mb-1">Mode</label>
                                    <select :value="selectedField.calendar_mode || 'single'" @change="updateField(selectedField.id, 'calendar_mode', $event.target.value)" class="w-full px-2 py-1.5 border rounded-lg bg-white dark:bg-gray-700 text-sm">
                                        <option value="single">Single date</option>
                                        <option value="range">Date range</option>
                                    </select>
                                </div>
                                <template x-if="(selectedField.calendar_mode || 'single') === 'range'">
                                    <div class="space-y-2">
                                        <input type="text" placeholder="Start date label" :value="selectedField.label_start || selectedField.label || ''" @blur="updateField(selectedField.id, 'label_start', $event.target.value)" class="w-full px-2 py-1.5 border rounded-lg text-sm"/>
                                        <input type="text" placeholder="End date label" :value="selectedField.label_end || 'End date'" @blur="updateField(selectedField.id, 'label_end', $event.target.value)" class="w-full px-2 py-1.5 border rounded-lg text-sm"/>
                                    </div>
                                </template>
                                <input x-show="(selectedField.calendar_mode || 'single') !== 'range'" type="text" placeholder="Helper text" :value="selectedField.helper_text || ''" @blur="updateField(selectedField.id, 'helper_text', $event.target.value)" class="w-full px-2 py-1.5 border rounded-lg text-sm"/>
                            </div>
                        </template>

                        {{-- If / Switch --}}
                        <template x-if="selectedField.type === 'if_condition'">
                            <div class="space-y-2 border-t border-gray-200 dark:border-gray-700 pt-3">
                                <label class="block text-xs font-medium">Condition</label>
                                <input type="text" :value="selectedField.condition || ''" @blur="updateField(selectedField.id, 'condition', $event.target.value)"
                                    placeholder="${form.optin_1} == true" class="w-full px-2 py-1.5 border rounded-lg text-sm font-mono"/>
                                <p class="text-xs text-gray-400">Then/else branches: add child components via import or JSON for now.</p>
                            </div>
                        </template>
                        <template x-if="selectedField.type === 'switch'">
                            <div class="border-t border-gray-200 dark:border-gray-700 pt-3">
                                <label class="block text-xs font-medium mb-1">Switch value</label>
                                <input type="text" :value="selectedField.switch_value || ''" @blur="updateField(selectedField.id, 'switch_value', $event.target.value)"
                                    placeholder="${data.category}" class="w-full px-2 py-1.5 border rounded-lg text-sm font-mono"/>
                            </div>
                        </template>

                        {{-- Navigation list items --}}
                        <template x-if="selectedField.type === 'navigation_list'">
                            <div class="border-t border-gray-200 dark:border-gray-700 pt-3 space-y-2">
                                <div class="flex justify-between items-center">
                                    <h4 class="text-xs font-bold">List items</h4>
                                    <button @click="addNavListItem(selectedField.id)" class="text-xs text-blue-600">+ Add</button>
                                </div>
                                <template x-for="(item, idx) in (selectedField.list_items || [])" :key="idx">
                                    <div class="p-2 bg-gray-100 dark:bg-gray-700 rounded space-y-1">
                                        <input type="text" :value="item.title" @blur="updateNavListItem(selectedField.id, idx, 'title', $event.target.value)" placeholder="Title" class="w-full px-2 py-1 text-xs border rounded"/>
                                        <input type="text" :value="item.next_screen_id || ''" @blur="updateNavListItem(selectedField.id, idx, 'next_screen_id', $event.target.value)" placeholder="Target screen ID" class="w-full px-2 py-1 text-xs border rounded font-mono"/>
                                        <button @click="removeNavListItem(selectedField.id, idx)" class="text-xs text-red-500">Remove</button>
                                    </div>
                                </template>
                            </div>
                        </template>

                        {{-- Dynamic data source --}}
                        <template x-if="['radio','checkbox','select','chips'].includes(selectedField.type)">
                            <div class="border-t border-gray-200 dark:border-gray-700 pt-3 space-y-2">
                                <label class="flex items-center gap-2 text-xs">
                                    <input type="checkbox" :checked="!!selectedField.dynamic_data_source" @change="updateField(selectedField.id, 'dynamic_data_source', $event.target.checked)" class="rounded"/>
                                    Use dynamic data-source from endpoint
                                </label>
                                <input x-show="selectedField.dynamic_data_source" type="text" :value="selectedField.data_source_key || ''" @blur="updateField(selectedField.id, 'data_source_key', $event.target.value)"
                                    placeholder="data key e.g. available_slots" class="w-full px-2 py-1.5 border rounded-lg text-xs font-mono"/>
                                <select x-show="selectedField.dynamic_data_source" :value="selectedField.on_select_action || ''" @change="updateField(selectedField.id, 'on_select_action', $event.target.value || null)" class="w-full px-2 py-1.5 border rounded-lg text-xs">
                                    <option value="">No on-select action</option>
                                    <option value="data_exchange">data_exchange</option>
                                    <option value="update_data">update_data</option>
                                </select>
                            </div>
                        </template>

                        {{-- Required toggle --}}
                        <template x-if="!['heading','subheading','body','caption','richtext','footer','button','navigate','image','image_carousel','navigation_list','if_condition','switch','photo_picker','document_picker'].includes(selectedField.type)">
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
                        <template x-if="selectedField.imported_dynamic_source && !(selectedField.options || []).length">
                            <div class="px-3 py-2 bg-amber-50 dark:bg-amber-900/20 border border-amber-300 dark:border-amber-700 rounded-lg text-amber-700 dark:text-amber-300 text-xs">
                                ⚠️ Dynamic data-source with no example options in the imported JSON. Add preview options or configure your endpoint.
                            </div>
                        </template>

                        {{-- Options --}}
                        <template x-if="['select','radio','checkbox','chips'].includes(selectedField.type)">
                            <div class="border-t border-gray-200 dark:border-gray-700 pt-4">
                                <div class="flex items-center justify-between mb-2">
                                    <h4 class="text-xs font-bold text-gray-900 dark:text-white">
                                        <span x-text="selectedField.dynamic_data_source ? 'Preview options' : 'Options'"></span>
                                    </h4>
                                    <button @click="addOption(selectedField.id)"
                                        class="text-xs text-blue-600 dark:text-blue-400 hover:underline font-medium">+ Add</button>
                                </div>

                                <!-- <p x-show="selectedField.dynamic_data_source" class="text-[10px] text-gray-500 mb-2">
                                    Imported from screen <code class="font-mono">data.__example__</code>. Publish still uses <code class="font-mono" x-text="`\${data.${selectedField.data_source_key || 'key'}}`"></code> at runtime.
                                </p> -->

                                <template x-if="!selectedField.dynamic_data_source && (selectedField.options || []).length === 0">
                                    <p class="text-xs text-red-500 mb-2">⚠️ Add at least one option — Meta requires minimum 1</p>
                                </template>

                                <div class="space-y-2">
                                    <template x-for="option in (selectedField.options || [])" :key="option.id">
                                        <div class="p-2 bg-gray-100 dark:bg-gray-700 rounded-lg space-y-1">
                                            <input type="text"
                                                :value="option.label"
                                                @blur="updateOption(selectedField.id, option.id, 'label', $event.target.value)"
                                                placeholder="Option label"
                                                class="w-full px-2 py-1 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-gray-600 text-gray-900 dark:text-white text-xs focus:outline-none focus:ring-1 focus:ring-blue-500"
                                            />
                                            <!-- <input type="text"
                                                :value="option.value"
                                                @blur="updateOption(selectedField.id, option.id, 'value', $event.target.value)"
                                                placeholder="Option value (Meta id)"
                                                class="w-full px-2 py-1 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-gray-600 text-gray-900 dark:text-white text-xs font-mono focus:outline-none focus:ring-1 focus:ring-blue-500"
                                            /> -->
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

        {{-- Templates Modal --}}
        <template x-if="showTemplatesModal">
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-xl w-full max-w-2xl max-h-[80vh] flex flex-col">
                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
                    <h2 class="text-lg font-bold text-gray-900 dark:text-white">Form templates</h2>
                    <button @click="showTemplatesModal = false" class="text-gray-400 hover:text-gray-600">✕</button>
                </div>
                <div class="p-6 overflow-y-auto grid gap-3 sm:grid-cols-2">
                    <template x-for="tpl in formTemplates" :key="tpl.key">
                        <button @click="applyTemplate(tpl.key)"
                            class="text-left p-4 border border-gray-200 dark:border-gray-700 rounded-lg hover:border-blue-500 hover:bg-blue-50 dark:hover:bg-blue-900/20 transition">
                            <div class="font-semibold text-gray-900 dark:text-white" x-text="tpl.name"></div>
                            <div class="text-xs text-gray-500 mt-1" x-text="tpl.description"></div>
                            <div class="text-xs text-blue-600 mt-2" x-text="`${tpl.screen_count} screen(s)`"></div>
                        </button>
                    </template>
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
        readiness: { ready: false, live: false, can_publish: false, steps: [], completed: 0, total: 0 },
        showReadiness: false,
        webhookUrl: '',
        webhookEnabled: false,
        showTemplatesModal: false,
        formTemplates: [],
        autosaveTimer: null,
        lastAutosavedAt: null,
        screens: [],
        selectedScreenId: null,
        selectedFieldId: null,
        isDirty: false,
        saving: false,
        checking: false,
        loading: true,
        endpointUrl: '',
        showImportModal: false,
        importJsonText: '',
        importError: '',
        notification: { visible: false, message: '', type: 'success' },
        notificationTimeout: null,
        validationResults: { errors: [], warnings: [] },

        // ── Meta type map ─────────────────────────────────────────────────────
        metaTypeMap: {
            text: ['string', 'example text'],
            textarea: ['string', 'example text'],
            radio: ['string', 'option1'],
            select: ['string', 'option1'],
            chips: ['array', ['option1']],
            date: ['string', '2026-01-01'],
            calendar: ['string', '2026-01-01'],
            checkbox: ['array', ['option1']],
            optin: ['boolean', true],
            photo_picker: ['array', []],
            document_picker: ['array', []],
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
            calendar: 'Select dates',
            photo_picker: 'Upload photo',
            document_picker: 'Upload document',
            navigation_list: 'Choose option',
            if_condition: 'Conditional block',
            switch: 'Switch',
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
            calendar: '📅',
            photo_picker: '📷',
            document_picker: '📎',
            navigation_list: '☰',
            if_condition: '?',
            switch: '⇄',
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
                    this.webhookUrl = f.webhook_url || '';
                    this.webhookEnabled = !!f.webhook_enabled;
                    this.screens = Array.isArray(f.screens) ? f.screens : [];
                    this.normalizeScreensOnLoad();
                    this.hydrateImportedFieldMetadata();
                    this.endpointUrl = data.endpoint_url || '';
                    await this.loadReadiness();
                    if (!this.metaFlowId) {
                        this.showReadiness = true;
                    }

                    if (this.screens.length > 0) {
                        this.ensureTerminalScreen();
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
            this.startAutosave();
            console.log('🏁 init() COMPLETED - loading = false');
        },

        startAutosave() {
            if (this.autosaveTimer) clearInterval(this.autosaveTimer);
            this.autosaveTimer = setInterval(() => {
                if (this.isDirty && this.flowName.trim() && !this.saving) {
                    this.save(true);
                }
            }, 45000);
        },

        async loadReadiness() {
            if (!this.flowId) return;
            try {
                const data = await this.api('GET', `/api/whatsapp-flows/${this.flowId}/readiness`);
                this.readiness = {
                    ready: !!data.ready,
                    live: !!data.live,
                    can_publish: !!data.can_publish,
                    steps: data.steps || [],
                    completed: data.completed || 0,
                    total: data.total || 0,
                };
            } catch (e) {
                console.warn('Readiness load failed', e);
            }
        },

        async openTemplatesModal() {
            this.showTemplatesModal = true;
            if (!this.formTemplates.length) {
                try {
                    const r = await this.api('GET', '/api/whatsapp-flows/templates');
                    this.formTemplates = r.templates || [];
                } catch (e) {
                    this.notify('Could not load templates.', 'error');
                }
            }
        },

        async applyTemplate(key) {
            try {
                const r = await this.api('POST', `/api/whatsapp-flows/from-bundle/${key}`);
                if (r.redirect) {
                    window.location.href = r.redirect;
                    return;
                }
                this.showTemplatesModal = false;
                this.notify('Template applied.', 'success');
            } catch (e) {
                this.notify(e.message || 'Failed to apply template.', 'error');
            }
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
                this.normalizeScreensOnLoad();
                this.hydrateImportedFieldMetadata();

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

        // ── Screens ───────────────────────────────────────────────────────────
        addScreen() {
            const count = this.screens.length + 1;
            const id = 'SCREEN_' + String.fromCharCode(64 + count);

            this.screens.push({
                id,
                title: 'Screen ' + count,
                fields: [],
            });

            this.setTerminalScreen(id);
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

            this.ensureTerminalScreen();
            this.isDirty = true;
        },

        selectScreen(id) {
            this.selectedScreenId = id;
            this.selectedFieldId = null;
            const screen = this.screens.find(s => s.id === id);
            if (screen) {
                this.repairDuplicateFieldIds(screen);
            }
        },

        normalizeScreensOnLoad() {
            for (const screen of this.screens) {
                if (! Array.isArray(screen.fields)) {
                    screen.fields = [];
                }

                this.repairDuplicateFieldIds(screen);
            }
        },

        repairDuplicateFieldIds(screen) {
            const fields = screen.fields || [];
            const seen = new Set();
            let hasDuplicates = false;

            for (const field of fields) {
                const key = String(field.id);
                if (seen.has(key)) {
                    hasDuplicates = true;
                    break;
                }
                seen.add(key);
            }

            if (! hasDuplicates) {
                return;
            }

            const ids = this.allocateFieldIds(fields.length);
            fields.forEach((field, index) => {
                field.id = ids[index];
            });
            this.isDirty = true;
        },

        updateScreenTitle(id, title) {
            const screen = this.screens.find(s => s.id === id);

            if (screen) {
                screen.title = title;
                this.isDirty = true;
            }
        },

        setScreenProp(id, prop, value) {
            if (prop === 'terminal') {
                if (value) {
                    this.setTerminalScreen(id);
                } else {
                    const screen = this.screens.find(s => s.id === id);
                    if (screen) {
                        screen.terminal = false;
                    }
                    this.ensureTerminalScreen();
                }
            } else {
                const screen = this.screens.find(s => s.id === id);
                if (screen) {
                    screen[prop] = value;
                }
            }
            this.isDirty = true;
        },

        /** Exactly one screen must be terminal — the screen where the user submits. */
        setTerminalScreen(id) {
            this.screens.forEach(s => { s.terminal = (s.id === id); });
        },

        ensureTerminalScreen() {
            if (!this.screens.length) {
                return;
            }

            const terminals = this.screens.filter(s => s.terminal);
            if (terminals.length === 1) {
                return;
            }

            const preferred = terminals.length > 1
                ? terminals[terminals.length - 1]
                : this.screens[this.screens.length - 1];

            this.setTerminalScreen(preferred.id);
        },

        addSummaryScreen() {
            const count = this.screens.length + 1;
            const id = 'SCREEN_SUMMARY_' + count;
            const [headingId, bodyId, footerId] = this.allocateFieldIds(3);
            this.screens.push({
                id,
                title: 'Review & confirm',
                fields: [
                    { id: headingId, type: 'heading', label: 'Review your details', placeholder: '', required: false },
                    { id: bodyId, type: 'body', label: '', placeholder: 'Please review your information before submitting.', required: false },
                    { id: footerId, type: 'footer', label: 'Confirm submission', placeholder: '', required: false },
                ],
            });
            this.setTerminalScreen(id);
            this.selectedScreenId = id;
            this.isDirty = true;
        },

        addStepScreen() {
            const count = this.screens.length + 1;
            const id = 'SCREEN_' + String.fromCharCode(64 + count);
            const [headingId, footerId] = this.allocateFieldIds(2);
            this.screens.push({
                id,
                title: 'Step ' + count + ' of ' + (count + 1),
                fields: [
                    { id: headingId, type: 'heading', label: 'Step ' + count, placeholder: '', required: false },
                    { id: footerId, type: 'footer', label: 'Continue to next step', placeholder: '', required: false },
                ],
            });
            this.selectedScreenId = id;
            this.isDirty = true;
        },

        addDynamicDataEntry(screenId) {
            const screen = this.screens.find(s => s.id === screenId);
            if (!screen) return;
            if (!screen.dynamic_data) screen.dynamic_data = [];
            screen.dynamic_data.push({ key: 'my_key', type: 'string', example: '' });
            this.isDirty = true;
        },

        removeDynamicDataEntry(screenId, idx) {
            const screen = this.screens.find(s => s.id === screenId);
            if (!screen?.dynamic_data) return;
            screen.dynamic_data.splice(idx, 1);
            this.isDirty = true;
        },

        updateDynamicDataEntry(screenId, idx, prop, value) {
            const screen = this.screens.find(s => s.id === screenId);
            if (!screen?.dynamic_data?.[idx]) return;
            screen.dynamic_data[idx][prop] = value;
            if (prop === 'type' && value === 'option_list' && !screen.dynamic_data[idx].example_items) {
                screen.dynamic_data[idx].example_items = [];
            }
            this.isDirty = true;
        },

        metaDataToEntries(metaData) {
            if (!metaData || typeof metaData !== 'object' || Array.isArray(metaData)) return [];
            const entries = [];
            for (const [key, def] of Object.entries(metaData)) {
                if (!def || typeof def !== 'object') continue;
                if (key.match(/^(text_|textarea_|radio_|checkbox_|select_|date_|calendar_|chips_|optin_|photo_|document_)/)) continue;
                if (def.type === 'boolean') {
                    entries.push({ key, type: 'boolean', example: !!def.__example__ });
                } else if (def.type === 'array' && this.isMetaOptionListSchema(def)) {
                    entries.push({
                        key,
                        type: 'option_list',
                        example_items: this.normalizeMetaExampleItems(def.__example__ || []),
                    });
                } else if (def.type === 'array') {
                    entries.push({
                        key,
                        type: 'object_array',
                        example: Array.isArray(def.__example__) ? def.__example__ : [],
                        item_properties: def.items?.properties || null,
                    });
                } else if (def.type === 'number') {
                    entries.push({ key, type: 'number', example: def.__example__ ?? 0 });
                } else {
                    const example = def.__example__;
                    entries.push({
                        key,
                        type: Array.isArray(example) ? 'object_array' : 'string',
                        example: Array.isArray(example) ? example : (example || ''),
                    });
                }
            }
            return entries;
        },

        isMetaOptionListSchema(def) {
            const props = def?.items?.properties;
            if (!props || typeof props !== 'object') return false;
            const keys = Object.keys(props).sort();
            return keys.length === 2 && keys[0] === 'id' && keys[1] === 'title';
        },

        normalizeMetaExampleItems(items) {
            if (!Array.isArray(items)) return [];
            return items.map((item, i) => ({
                id: String(item?.id ?? item?.value ?? `option_${i + 1}`),
                title: String(item?.title ?? item?.label ?? item?.description ?? item?.id ?? `Option ${i + 1}`),
            }));
        },

        metaOptionItemsToBuilderOptions(items) {
            if (!Array.isArray(items) || !items.length) return [];
            return items.map((item, i) => {
                const value = String(item?.id ?? item?.value ?? `option_${i + 1}`);
                return {
                    id: this.uuid(),
                    label: String(item?.title ?? item?.label ?? item?.description ?? value),
                    value,
                };
            });
        },

        extractDynamicDataKey(dataSource) {
            if (typeof dataSource !== 'string') return null;
            const match = dataSource.match(/^\$\{data\.([^}]+)\}$/);
            return match ? match[1] : null;
        },

        exampleOptionsFromScreenData(screenData, key) {
            if (!screenData || !key) return [];
            const def = screenData[key];
            if (!def || typeof def !== 'object') return [];
            return this.metaOptionItemsToBuilderOptions(this.normalizeMetaExampleItems(def.__example__ || []));
        },

        applyImportedDataSource(field, dataSource, screenData) {
            const dynamicKey = this.extractDynamicDataKey(dataSource);
            if (dynamicKey) {
                field.dynamic_data_source = true;
                field.data_source_key = dynamicKey;
                field.options = this.exampleOptionsFromScreenData(screenData, dynamicKey);
                field.imported_dynamic_source = !(field.options || []).length;
                return;
            }

            if (Array.isArray(dataSource)) {
                field.dynamic_data_source = false;
                field.options = this.metaOptionItemsToBuilderOptions(dataSource);
                field.imported_dynamic_source = !(field.options || []).length;
                return;
            }

            field.dynamic_data_source = false;
            field.options = [];
            field.imported_dynamic_source = true;
        },

        findScreenForField(fieldId) {
            return this.screens.find(s => (s.fields || []).some(f => f.id === fieldId)) || null;
        },

        syncDynamicDataExampleFromField(field, screen = null) {
            if (!field?.dynamic_data_source || !field.data_source_key) return;
            screen = screen || this.findScreenForField(field.id);
            if (!screen) return;
            if (!screen.dynamic_data) screen.dynamic_data = [];
            let entry = screen.dynamic_data.find(e => e.key === field.data_source_key);
            if (!entry) {
                entry = { key: field.data_source_key, type: 'option_list', example_items: [] };
                screen.dynamic_data.push(entry);
            }
            entry.type = 'option_list';
            entry.example_items = (field.options || []).map(o => ({
                id: o.value || o.id,
                title: o.label || o.value,
            }));
        },

        hydrateImportedFieldMetadata() {
            for (const screen of this.screens) {
                for (const field of screen.fields || []) {
                    if (!field.meta_name && field.data_source_key) {
                        field.meta_name = field.data_source_key;
                    }
                    if (field.on_select_payload && typeof field.on_select_payload === 'object') {
                        for (const value of Object.values(field.on_select_payload)) {
                            const match = String(value).match(/^\$\{form\.([^}]+)\}$/);
                            if (match && !field.meta_name) {
                                field.meta_name = match[1];
                            }
                        }
                    }
                }
            }
        },

        hydrateImportedScreenFields(screen) {
            const screenData = screen._imported_meta_data || {};
            const walk = (fields) => {
                (fields || []).forEach(field => {
                    if (['radio', 'checkbox', 'select', 'chips'].includes(field.type)) {
                        if (field.dynamic_data_source && field.data_source_key && !(field.options || []).length) {
                            const entry = (screen.dynamic_data || []).find(e => e.key === field.data_source_key);
                            if (entry?.example_items?.length) {
                                field.options = this.metaOptionItemsToBuilderOptions(entry.example_items);
                            } else {
                                field.options = this.exampleOptionsFromScreenData(screenData, field.data_source_key);
                            }
                        }
                        if (field.dynamic_data_source && (field.options || []).length) {
                            this.syncDynamicDataExampleFromField(field, screen);
                            field.imported_dynamic_source = false;
                        }
                    }
                    if (field.type === 'if_condition') {
                        walk(field.then_children);
                        walk(field.else_children);
                    }
                    if (field.type === 'switch') {
                        (field.cases || []).forEach(c => walk(c.children));
                    }
                });
            };
            walk(screen.fields);
            delete screen._imported_meta_data;
        },

        // ── Fields ────────────────────────────────────────────────────────────
        nextFieldId() {
            return this.allocateFieldIds(1)[0];
        },

        /** Reserve unique numeric field ids before pushing a new screen (nextFieldId() alone would repeat). */
        allocateFieldIds(count = 1) {
            let max = 0;
            this.screens.forEach(s => (s.fields || []).forEach(f => {
                const id = Number(f.id);
                if (! Number.isNaN(id) && id > max) {
                    max = id;
                }
            }));

            const start = max + 1;

            return Array.from({ length: count }, (_, i) => start + i);
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

            if (type === 'text')           { field.input_type = 'text'; field.helper_text = ''; field.sensitive = false; }
            if (type === 'textarea')       { field.max_length = 600; field.helper_text = ''; }
            if (type === 'image')          { field.image_url = ''; field.height = 300; field.scale_type = 'contain'; }
            if (type === 'image_carousel') { field.images = []; }
            if (type === 'embedded_link')  { field.url = ''; field.button_label = 'Open Link'; }
            if (type === 'optin')          { field.read_more_url = ''; }
            if (type === 'calendar')       { field.calendar_mode = 'single'; field.helper_text = ''; }
            if (type === 'photo_picker')   { field.min_uploaded = 0; field.max_uploaded = 1; field.photo_source = 'camera_gallery'; }
            if (type === 'document_picker'){ field.min_uploaded = 0; field.max_uploaded = 1; }
            if (type === 'navigation_list'){ field.list_items = [{ id: 'item_1', title: 'Option 1', next_screen_id: '' }]; }
            if (type === 'if_condition')   { field.condition = '${true}'; field.then_children = []; field.else_children = []; }
            if (type === 'switch')         { field.switch_value = '${data.value}'; field.cases = [{ key: 'default', children: [] }]; }
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
            this.syncDynamicDataExampleFromField(field);
            if ((field.options || []).length) field.imported_dynamic_source = false;
            this.isDirty = true;
        },

        removeOption(fieldId, optId) {
            const field = this.findField(fieldId);
            if (!field) return;
            field.options = field.options.filter(o => o.id !== optId);
            this.syncDynamicDataExampleFromField(field);
            this.isDirty = true;
        },

        updateOption(fieldId, optId, prop, value) {
            const field = this.findField(fieldId);
            if (!field) return;
            const opt = (field.options || []).find(o => o.id === optId);
            if (opt) {
                opt[prop] = value;
                if (prop === 'label' && !field.dynamic_data_source) {
                    opt.value = value.toLowerCase().replace(/\s+/g, '_') || optId;
                }
                this.syncDynamicDataExampleFromField(field);
                if ((field.options || []).length) field.imported_dynamic_source = false;
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

        addNavListItem(fieldId) {
            const field = this.findField(fieldId);
            if (!field) return;
            if (!field.list_items) field.list_items = [];
            const n = field.list_items.length + 1;
            field.list_items.push({ id: 'item_' + n, title: 'Option ' + n, next_screen_id: '' });
            this.isDirty = true;
        },
        removeNavListItem(fieldId, idx) {
            const field = this.findField(fieldId);
            if (!field?.list_items) return;
            field.list_items.splice(idx, 1);
            this.isDirty = true;
        },
        updateNavListItem(fieldId, idx, prop, value) {
            const field = this.findField(fieldId);
            if (!field?.list_items?.[idx]) return;
            field.list_items[idx][prop] = value;
            if (prop === 'title') field.list_items[idx].id = value.toLowerCase().replace(/\s+/g, '_') || ('item_' + (idx + 1));
            this.isDirty = true;
        },

          // ── Palette helpers ───────────────────────────────────────────────────
          currentFieldTypes() {
            return (this.selectedScreen?.fields || []).map(f => f.type);
        },
        hasRichText()   { return this.currentFieldTypes().includes('richtext'); },
        hasFooter()     { return this.currentFieldTypes().some(t => ['footer','button','navigate'].includes(t)); },
        hasNavigationList() { return this.currentFieldTypes().includes('navigation_list'); },
        canAddRichText(){ return !this.hasRichText() && !this.currentFieldTypes().some(t => !['richtext','footer','button','navigate'].includes(t)); },
        canAddField(type) {
            if (this.hasRichText() && !['footer','button','navigate'].includes(type)) return false;
            if (type === 'richtext') return this.canAddRichText();
            if (['footer','button','navigate'].includes(type)) return !this.hasFooter();
            if (type === 'navigation_list') return !this.hasNavigationList() && (this.selectedScreen?.fields || []).length === 0;
            if (this.hasNavigationList()) return false;
            if (type === 'photo_picker' && this.currentFieldTypes().includes('document_picker')) return false;
            if (type === 'document_picker' && this.currentFieldTypes().includes('photo_picker')) return false;
            if (type === 'photo_picker' && this.currentFieldTypes().filter(t => t === 'photo_picker').length >= 1) return false;
            if (type === 'document_picker' && this.currentFieldTypes().filter(t => t === 'document_picker').length >= 1) return false;
            if (this.countScreenComponents(this.selectedScreen) >= 50) return false;
            return true;
        },
        countScreenComponents(screen) {
            if (!screen) return 0;
            let n = (screen.fields || []).length;
            (screen.fields || []).forEach(f => {
                if (f.type === 'if_condition') n += (f.then_children || []).length + (f.else_children || []).length;
                if (f.type === 'switch') (f.cases || []).forEach(c => { n += (c.children || []).length; });
            });
            return n;
        },

        // ── Validation helpers ────────────────────────────────────────────────
        fieldHasError(field) {
            if (field.type === 'image')          return !field.image_url;
            if (field.type === 'embedded_link')  return !field.url;
            if (field.type === 'image_carousel') return !(field.images||[]).length || (field.images||[]).some(i => !i.src);
            if (['radio','checkbox','select','chips'].includes(field.type) && !field.dynamic_data_source) return !(field.options||[]).length;
            if (field.type === 'navigation_list') return !(field.list_items||[]).length;
            if (field.type === 'chips' && (field.options||[]).length > 0 && (field.options||[]).length < 2) return true;
            return false;
        },

        async runValidation() {
            this.checking = true;
            try {
                const r = await this.api('POST', '/api/flow-builder/validate', {
                    name: this.flowName,
                    screens: this.screens,
                });
                this.validationResults = { errors: r.errors || [], warnings: r.warnings || [] };
                if (r.errors?.length) this.notify('Validation found ' + r.errors.length + ' error(s).', 'error');
                else if (r.warnings?.length) this.notify('Valid with ' + r.warnings.length + ' suggestion(s).', 'success');
                else this.notify('Flow passes all checks.', 'success');
            } catch (e) {
                this.notify(e.message || 'Validation failed.', 'error');
            } finally {
                this.checking = false;
            }
        },
        typeIcon(type) { return this.typeIcons[type] || '·'; },

        // ── Save / Publish ────────────────────────────────────────────────────
        async save(silent = false) {
            if (!this.flowName.trim()) { if (!silent) this.notify('Form name is required.', 'error'); return; }
            this.saving = true;
            try {
                const payload = {
                    name:        this.flowName,
                    description: this.flowDescription,
                    category:    this.flowCategory,
                    screens:     this.screens,
                    webhook_url: this.webhookUrl || null,
                    webhook_enabled: this.webhookEnabled,
                };

                if (this.flowId) {
                    await this.api('PUT', `/api/flow-builder/${this.flowId}`, payload);
                } else {
                    const r = await this.api('POST', '/api/flow-builder', payload);
                    this.flowId = r.flow_id;
                    window.history.replaceState({}, '', `/whatsapp-flows/${this.flowId}/edit`);
                }

                this.isDirty = false;
                this.lastAutosavedAt = new Date();
                if (!silent) this.notify('Form saved successfully.', 'success');
            } catch(e) {
                if (!silent) this.notify(e.message || 'Save failed.', 'error');
            } finally {
                this.saving = false;
            }
        },

        async publish() {
            if (!this.flowName.trim()) { this.notify('Flow name is required.', 'error'); return; }
            await this.runValidation();
            if (this.validationResults.errors.length) {
                this.notify('Fix validation errors before publishing.', 'error');
                return;
            }
            this.saving = true;
            try {
                const r = await this.api('POST', `/api/flow-builder/${this.flowId || ''}/publish`, {
                    name: this.flowName, description: this.flowDescription,
                    category: this.flowCategory, screens: this.screens,
                });
                this.metaFlowId = r.meta_flow_id;
                this.isDirty    = false;
                await this.loadReadiness();
                this.showReadiness = false;
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
                CalendarPicker: 'calendar', ChipsSelector: 'chips',
                PhotoPicker: 'photo_picker', DocumentPicker: 'document_picker',
                NavigationList: 'navigation_list', If: 'if_condition', Switch: 'switch',
                OptIn: 'optin', TextHeading: 'heading', TextSubheading: 'subheading',
                TextBody: 'body', TextCaption: 'caption', RichText: 'richtext',
                Image: 'image', ImageCarousel: 'image_carousel',
                EmbeddedLink: 'embedded_link', Footer: 'footer',
            };

            const parseComponent = (c, screenData = {}) => {
                const builderType = typeMap[c.type];
                if (!builderType) return null;
                const [mt, me] = this.metaTypeMap[builderType] || ['string', 'example'];
                const field = {
                    id: nextId++, type: builderType,
                    label: c.label || c.text || this.defaultLabels[builderType] || builderType,
                    placeholder: Array.isArray(c.text) ? c.text.join('\n') : (c.text || ''),
                    required: typeof c.required === 'boolean' ? c.required : false,
                    meta_type: mt, meta_example: me,
                };
                if (c.name) field.meta_name = c.name;
                if (typeof c.required === 'string') field.required_binding = c.required;
                if (c.visible) field.visible_binding = c.visible;
                if (c['on-select-action']) {
                    field.on_select_action = c['on-select-action'].name || null;
                    field.on_select_payload = c['on-select-action'].payload || {};
                }
                if (['radio','checkbox','select','chips'].includes(builderType)) {
                    this.applyImportedDataSource(field, c['data-source'], screenData);
                }
                if (builderType === 'text') {
                    field.input_type = c['input-type'] || 'text';
                    field.helper_text = c['helper-text'] || '';
                    field.sensitive = !!c.sensitive;
                }
                if (builderType === 'textarea') { field.max_length = c['max-length'] || 600; field.helper_text = c['helper-text'] || ''; }
                if (builderType === 'body' || builderType === 'caption') { field.markdown = !!c.markdown; field.placeholder = c.text || ''; }
                if (builderType === 'image') { field.image_url = c.src || ''; field.height = c.height || 300; field.scale_type = c['scale-type'] || 'contain'; }
                if (builderType === 'image_carousel') { field.images = (c.images||[]).map(i => ({ src: i.src || '', alt_text: i['alt-text'] || '' })); }
                if (builderType === 'embedded_link') {
                    const action = c['on-click-action'] || {};
                    field.url = action.url || action.payload?.url || '';
                    field.button_label = c.text || 'Open Link';
                }
                if (builderType === 'optin' && c['on-click-action']?.name === 'open_url') {
                    field.read_more_url = c['on-click-action'].url || '';
                }
                if (builderType === 'calendar') { field.calendar_mode = c.mode || 'single'; field.helper_text = c['helper-text'] || ''; }
                if (builderType === 'navigation_list') {
                    field.list_items = (c['list-items'] || []).map(i => ({
                        id: i.id, title: i['main-content']?.title || i.id,
                        description: i['main-content']?.description || '',
                        next_screen_id: i['on-click-action']?.next?.name || '',
                        on_click_action: i['on-click-action']?.name || 'navigate',
                        on_click_payload: i['on-click-action']?.payload || {},
                    }));
                }
                if (builderType === 'if_condition') {
                    field.condition = c.condition || '${true}';
                    field.then_children = (c.then || []).map(child => parseComponent(child, screenData)).filter(Boolean);
                    field.else_children = (c.else || []).map(child => parseComponent(child, screenData)).filter(Boolean);
                }
                if (builderType === 'switch') {
                    field.switch_value = c.value || '${data.value}';
                    field.cases = Object.entries(c.cases || {}).map(([key, children]) => ({
                        key, children: (children || []).map(child => parseComponent(child, screenData)).filter(Boolean),
                    }));
                }
                if (builderType === 'footer') {
                    const action = c['on-click-action'] || {};
                    field.type = 'footer';
                    field.label = c.label || 'Continue';
                    field.on_click_action = action.name || null;
                    field.on_click_payload = action.payload || {};
                    field.navigate_next = action.next?.name || '';
                }
                return field;
            };

            const flattenLayout = (children, screenData) => (children || []).flatMap(c => {
                if (c.type === 'Form') return (c.children || []).map(child => parseComponent(child, screenData)).filter(Boolean);
                if (c.type === 'If') return [parseComponent(c, screenData)].filter(Boolean);
                return [parseComponent(c, screenData)].filter(Boolean);
            });

            this.screens = decoded.screens.map(ms => {
                const screenData = ms.data && typeof ms.data === 'object' ? ms.data : {};
                const screen = {
                    id: ms.id || 'SCREEN_' + Math.random().toString(36).slice(2, 6).toUpperCase(),
                    title: ms.title || 'Imported Screen',
                    terminal: !!(ms.terminal ?? ms.is_terminal),
                    refresh_on_back: ms.refresh_on_back,
                    endpoint_template: ms.endpoint_template,
                    dynamic_data: ms.dynamic_data || this.metaDataToEntries(screenData),
                    fields: flattenLayout(ms.layout?.children || [], screenData),
                    _imported_meta_data: screenData,
                };
                this.hydrateImportedScreenFields(screen);
                return screen;
            });

            this.hydrateImportedFieldMetadata();
            this.ensureTerminalScreen();
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
            if (this.notificationTimeout) {
                clearTimeout(this.notificationTimeout);
            }

            this.notification = { visible: true, message, type };

            const duration = type === 'error' ? 15000 : 7000;
            this.notificationTimeout = setTimeout(() => {
                this.notification.visible = false;
                this.notificationTimeout = null;
            }, duration);
        },




    };
}
</script>

</body>
</html>