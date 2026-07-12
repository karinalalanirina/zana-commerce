<x-layouts.user :title="__('Flow Builder')" nav-key="flows" page="user-flows-builder" :hide-header="true">

    @php
        $flowId = isset($flow) && $flow ? $flow->id : null;
        $flowName = isset($flow) && $flow ? $flow->flow_name ?? 'New flow' : 'New flow';
        $flowJson = $flowJson ?? ['flowNodes' => [], 'flowEdges' => []];
        $isPublished = isset($flow) && $flow && $flow->is_published;
        $category = isset($flow) && $flow ? $flow->category ?? '' : '';
        $flowType = $flowType ?? (isset($flow) && $flow ? ($flow->flow_type ?: 'chat') : 'chat');
    @endphp

    <div id="root" data-flow-id="{{ $flowId }}" data-flow-name="{{ $flowName }}"
        data-flow-category="{{ $category }}" data-flow-published="{{ $isPublished ? '1' : '0' }}"
        data-flow-type="{{ $flowType }}"
        data-flow-json="{{ json_encode($flowJson, JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG) }}">
        <div class="h-screen w-screen grid place-items-center">
            <div class="text-center">
                <div class="font-serif text-[18px] text-ink-700">{{ __('Loading flow builder...') }}</div>
            </div>
        </div>
    </div>

</x-layouts.user>
