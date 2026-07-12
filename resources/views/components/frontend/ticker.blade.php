{{--
 Top dark marquee — runs above the nav on every public page.
 Items here are static editorial copy. If you want them admin-driven
 (announcements bar already exists for the user shell), swap the
 hard-coded entries for an Announcement::active() query.
--}}
<div data-fc-section="ticker" class="bg-ink-950 text-paper-0 text-[11px] mono overflow-hidden">
    <div class="marquee whitespace-nowrap py-2.5">
        @foreach (range(0, 1) as $__loop)
            <div class="flex items-center gap-8 shrink-0 px-4">
                <span class="flex items-center gap-2">
                    <span class="w-1.5 h-1.5 rounded-full bg-wa-green pulse-dot"></span>
                    <span
                        data-fc="ticker.message1">{{ fc('ticker.message1', '240,184,209 messages delivered this month') }}</span>
                </span>
                <span class="text-paper-0/40">·</span>
                <span
                    data-fc="ticker.message2">{{ fc('ticker.message2', '4,218 active workspaces across 38 markets') }}</span>
                <span class="text-paper-0/40">·</span>
                <span data-fc="ticker.message3">{{ fc('ticker.message3', 'Median template approval · 18m') }}</span>
                <span class="text-paper-0/40">·</span>
                <span class="text-wa-green"
                    data-fc="ticker.message4">{{ fc('ticker.message4', '▲ +34% revenue lift · 90 days post-onboarding') }}</span>
                <span class="text-paper-0/40">·</span>
                <span data-fc="ticker.message5">{{ fc('ticker.message5', 'Now live: AI Flow Generation') }}</span>
            </div>
        @endforeach
    </div>
</div>
