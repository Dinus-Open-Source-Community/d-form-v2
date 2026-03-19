<div>
    <form enctype="multipart/form-data" wire:submit="$wire.save(true)">
        {{ $this->createSchema }}

        <div class="my-4 flex flex-col justify-end gap-3 md:flex-row">
            <a
                href="{{
                    url()->previous() === route('dashboard.events.create')
                        ? route('dashboard.events.index')
                        : url()->previous()
                }}"
                class="btn btn-soft btn-error"
            >
                Cancel
            </a>

            <button class="btn btn-ghost" type="button" x-on:click="$wire.save(false)" wire:loading.attr="disabled">
                Save as Draft
            </button>

            <button class="btn btn-primary" type="submit" wire:loading.attr="disabled">Save and Publish</button>
        </div>
    </form>
</div>
