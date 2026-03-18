@props([
    'forms',
    'event',
])

<div class="border-base-300 grid h-fit w-full grid-cols-1 gap-4 rounded-lg md:border md:p-6">
    <h4 class="text-base-content flex items-center gap-1 font-bold">
        @svg('heroicon-o-clipboard-document-list', 'size-5')
        {{ __('Forms') }}
    </h4>

    <div class="flex flex-col gap-3">
        @empty($forms)
            <p>{{ __('No forms found.') }}</p>
        @else
            @foreach ($forms as $form)
                <a
                    class="hover:bg-primary/30 dark:hover:text-primary-content flex items-center justify-between rounded-md px-2 py-1 transition-all"
                    href="#"
                >
                    <span>{{ str($form['title'])->limit(50) }}</span>

                    <span>
                        @svg('heroicon-o-chevron-right', 'size-4')
                    </span>
                </a>

                <hr class="border-base-300 last:hidden" />
            @endforeach
        @endempty
    </div>

    <a
        href="{{ route('dashboard.events.forms.create', ['event' => $event['id']]) }}"
        class="btn btn-primary btn-soft w-full flex-1"
    >
        @svg('heroicon-o-plus', 'size-[1em]')
        <span>
            {{ __('Create new form') }}
        </span>
    </a>
</div>
