<div class="relative z-0 hidden lg:block">
    <div
        class="absolute top-0 left-0 h-full w-full overflow-hidden"
        x-data="{
            currentImg: 1,
            totalImgs: 3,
            imageList: [
                '/images/banners/image-1.jpg',
                '/images/banners/image-2.jpg',
                '/images/banners/image-3.jpg',
            ],
            intervalId: null,
            setCurrentImg() {
                this.currentImg =
                    this.currentImg + 1 > this.totalImgs ? 1 : this.currentImg + 1
            },
        }"
        x-init="
            intervalId = setInterval(() => {
                setCurrentImg()
            }, 3000)
        "
    >
        <template x-for="(image, i) in imageList">
            <img
                x-bind:src="image"
                x-bind:alt="'banner image no ' + (i + 1)"
                class="absolute top-0 left-0 h-full w-full object-cover object-center"
                x-show="currentImg === i + 1"
                x-transition:enter="transition duration-500 ease-out"
                x-transition:enter-start="opacity-0"
                x-transition:enter-end="opacity-100"
                x-transition:leave="absolute top-0 left-0 h-full w-full transition duration-500 ease-in"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0"
            />
        </template>
    </div>

    <div
        class="absolute top-0 left-0 z-10 flex h-full w-full flex-col items-start justify-end bg-linear-to-b from-transparent to-neutral-800 px-8 pb-6"
    >
        {{-- <h1 class="mb-4 text-4xl font-bold">D-Form</h1> --}}
        <p class="text-slate-200">Created by Dinus Open Source Community</p>
    </div>
</div>
