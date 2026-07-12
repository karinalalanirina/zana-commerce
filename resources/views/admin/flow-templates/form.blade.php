@php $editing = isset($template) && $template; @endphp
<x-layouts.admin :title="$editing ? __('Edit template') : __('New template')" admin-key="flow-templates" page="admin-flow-templates-form">

    <header class="h-16 bg-paper-0 hairline-b border-b border-paper-200 flex items-center px-4 sm:px-7 gap-4 sticky top-0 z-30">
        <div class="flex items-center gap-2 text-[12px] font-mono text-ink-500 shrink-0">
            <a href="{{ url('/admin') }}" class="uppercase tracking-[0.16em] hover:text-ink-900">{{ __('Admin') }}</a>
            <svg viewBox="0 0 12 12" class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M4 3l3 3-3 3" /></svg>
            <a href="{{ route('admin.flow-templates.index') }}" class="hover:text-ink-900">{{ __('Flow templates') }}</a>
            <svg viewBox="0 0 12 12" class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M4 3l3 3-3 3" /></svg>
            <span class="text-ink-900 normal-case tracking-normal">{{ $editing ? __('Edit') : __('New') }}</span>
        </div>
        <div class="ml-auto flex items-center gap-2" data-admin-header-right></div>
    </header>

    <main class="px-4 sm:px-7 py-7">
        <div class="max-w-3xl mx-auto">
            <div class="mb-5">
                <div class="font-mono text-[10px] uppercase tracking-[0.18em] text-ink-500 mb-2">{{ __('Admin · Automation · Templates') }}</div>
                <h1 class="font-serif font-normal tracking-[-0.01em] text-[28px] sm:text-[36px] leading-[1.0]">
                    {{ $editing ? __('Edit') : __('New') }} <span class="italic text-wa-deep">{{ __('template') }}</span>.</h1>
            </div>

            @if ($errors->any())
                <div class="mb-5 rounded-2xl border border-accent-coral/40 bg-accent-coral/10 px-4 py-3 text-[12.5px] text-[#A1431F]">
                    <ul class="list-disc pl-4 space-y-0.5">
                        @foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" enctype="multipart/form-data"
                action="{{ $editing ? route('admin.flow-templates.update', $template->id) : route('admin.flow-templates.store') }}"
                class="space-y-5">
                @csrf
                @if ($editing) @method('PUT') @endif

                {{-- Details --}}
                <div class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card p-5 space-y-4">
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Details') }}</div>

                    <div>
                        <label class="block text-[12px] font-medium text-ink-700 mb-1">{{ __('Name') }} <span class="text-accent-coral">*</span></label>
                        <input name="name" value="{{ old('name', $template->name ?? '') }}" required maxlength="160"
                            placeholder="{{ __('e.g. Restaurant — Welcome & Menu') }}"
                            class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-paper-0 text-[13px] focus:outline-none focus:border-wa-deep" />
                    </div>

                    <div>
                        <label class="block text-[12px] font-medium text-ink-700 mb-1">{{ __('Description') }}</label>
                        <textarea name="description" rows="2" maxlength="1000"
                            placeholder="{{ __('One line shown to tenants in the gallery.') }}"
                            class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-paper-0 text-[13px] resize-y focus:outline-none focus:border-wa-deep">{{ old('description', $template->description ?? '') }}</textarea>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-[12px] font-medium text-ink-700 mb-1">{{ __('Flow type') }}</label>
                            @php $ft = old('flow_type', $template->flow_type ?? 'chat'); @endphp
                            <select name="flow_type" class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-paper-0 text-[13px] focus:outline-none focus:border-wa-deep">
                                <option value="chat" @selected($ft === 'chat')>{{ __('Chat') }}</option>
                                <option value="call" @selected($ft === 'call')>{{ __('Call (voice)') }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-[12px] font-medium text-ink-700 mb-1">{{ __('Category') }}</label>
                            <input name="category" value="{{ old('category', $template->category ?? '') }}" maxlength="64"
                                placeholder="{{ __('welcome / lead / support') }}"
                                class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-paper-0 text-[13px] focus:outline-none focus:border-wa-deep" />
                        </div>
                        <div>
                            <label class="block text-[12px] font-medium text-ink-700 mb-1">{{ __('Sort order') }}</label>
                            <input name="sort_order" type="number" min="0" max="9999" value="{{ old('sort_order', $template->sort_order ?? 0) }}"
                                class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-paper-0 text-[13px] focus:outline-none focus:border-wa-deep" />
                        </div>
                    </div>

                    <label class="flex items-center gap-2 text-[12.5px] text-ink-700 cursor-pointer">
                        <input type="hidden" name="is_active" value="0" />
                        <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $template->is_active ?? true)) class="w-4 h-4 accent-wa-deep" />
                        {{ __('Visible to tenants (shows in the Flows "Start from a template" gallery)') }}
                    </label>
                </div>

                {{-- Flow source --}}
                <div class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card p-5 space-y-4">
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Flow content') }}</div>
                    @if ($editing)
                        <div class="rounded-lg border border-paper-200 bg-paper-50 px-3 py-2 text-[12px] text-ink-600">
                            {{ __('Currently :n steps. Leave all three fields below empty to keep the current flow — fill one to replace it.', ['n' => $template->node_count]) }}
                        </div>
                    @else
                        <p class="text-[12px] text-ink-600">{{ __('Provide the flow in ONE of these ways. The easiest: open the flow builder, build your flow, click Export, then upload that .json here.') }}</p>
                    @endif

                    <div>
                        <label class="block text-[12px] font-medium text-ink-700 mb-1">{{ __('1 · Upload exported flow (.json)') }}</label>
                        <input name="flow_file" type="file" accept="application/json,.json"
                            class="w-full text-[12.5px] file:mr-3 file:px-3 file:py-1.5 file:rounded-full file:border-0 file:bg-wa-deep file:text-paper-0 file:text-[12px] file:font-semibold file:cursor-pointer" />
                    </div>

                    <div>
                        <label class="block text-[12px] font-medium text-ink-700 mb-1">{{ __('2 · Or paste flow JSON') }}</label>
                        <textarea name="flow_json" rows="4" placeholder='{"flowNodes":[...],"flowEdges":[...]}'
                            class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-[#0F1720] text-[#D7E3DC] font-mono text-[11.5px] resize-y focus:outline-none focus:border-wa-deep">{{ old('flow_json') }}</textarea>
                    </div>

                    <div>
                        <label class="block text-[12px] font-medium text-ink-700 mb-1">{{ __('3 · Or clone an existing flow by ID') }}</label>
                        <input name="source_flow_id" type="number" min="1" value="{{ old('source_flow_id') }}"
                            placeholder="{{ __('flow id from /flows/builder/<id>') }}"
                            class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-paper-0 text-[13px] focus:outline-none focus:border-wa-deep" />
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2">
                    <a href="{{ route('admin.flow-templates.index') }}" class="px-4 py-2 border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium">{{ __('Cancel') }}</a>
                    <button type="submit" class="px-5 py-2 rounded-full bg-wa-deep text-paper-0 text-[12px] font-semibold hover:bg-wa-teal">{{ __('Save template') }}</button>
                </div>
            </form>
        </div>
    </main>
</x-layouts.admin>
