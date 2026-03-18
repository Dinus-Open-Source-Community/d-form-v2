<div class="border-base-300 grid h-fit w-full grid-cols-1 gap-4 rounded-lg md:border md:p-6">
    <h4 class="text-base-content flex items-center gap-1 font-bold">
        @svg('heroicon-o-calendar-days', 'size-5')
        {{ __('Schedules') }}
    </h4>

    <div class="flex flex-col gap-3">
        <p>{{ __('No schedules found.') }}</p>
    </div>

    <button class="btn btn-primary btn-soft w-full flex-1">
        @svg('heroicon-o-plus', 'size-[1em]')
        <span>
            {{ __('Create new schedule') }}
        </span>
    </button>
</div>
