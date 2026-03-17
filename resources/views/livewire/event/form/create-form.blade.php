<div>
    <form wire:submit="save">
        {{ $this->createSchema }}

        <div class="my-4 flex flex-col justify-end gap-3 md:flex-row">
            <a
                href="{{ url()->previous() === url()->current() ? route('dashboard.events.show', ['event' => $eventId]) : url()->previous() }}"
                class="btn btn-soft btn-error"
            >
                Cancel
            </a>
            <button class="btn btn-primary" type="submit">Save</button>
        </div>
    </form>
</div>
