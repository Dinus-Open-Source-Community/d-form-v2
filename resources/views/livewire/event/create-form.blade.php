<div>
    <form enctype="multipart/form-data" wire:submit="$wire.save(true)">
        {{ $this->createSchema }}

        <div class="my-4 flex flex-col justify-end gap-3 md:flex-row">
            <a
                href="{{ url()->previous() === url()->current() ? route('dashboard.events.index') : url()->previous() }}"
                class="btn btn-soft btn-error"
            >
                Cancel
            </a>
            <button class="btn btn-ghost" type="button" x-on:click="$wire.save(false)">Save as Draft</button>
            <button class="btn btn-primary" type="submit">Save and Publish</button>
        </div>
    </form>
</div>
