{{-- One product on the customer menu; rendered by Alpine inside x-for="product in …" (single root element). --}}
<article class="overflow-hidden rounded-2xl border border-stone-200 bg-white" :class="isSoldOut(product) && 'opacity-60'">
    <div class="relative">
        <button type="button" x-on:click="openProduct(product)" class="flex h-44 w-full items-center justify-center bg-cream/70 text-latte" :aria-label="'Xem ' + product.name">
            <template x-if="product.image"><img :src="product.image" :alt="product.name" class="size-full object-cover" loading="lazy"></template>
            <template x-if="! product.image">
                <span class="flex flex-col items-center gap-2"><x-icon name="coffee" class="size-10" /><span class="text-sm text-stone-600">Ảnh sản phẩm</span></span>
            </template>
        </button>
        <button
            type="button"
            x-on:click="toggleFavorite(product.id)"
            class="absolute top-3 right-3 flex size-11 items-center justify-center rounded-full bg-white shadow"
            :class="isFavorite(product.id) ? 'text-red-500 [&_path]:fill-current' : 'text-ink'"
            :aria-pressed="isFavorite(product.id).toString()"
            :aria-label="'Yêu thích ' + product.name"
        >
            <x-icon name="heart" class="size-5" />
        </button>
        <span x-show="isSoldOut(product)" class="absolute bottom-3 left-3 rounded-full bg-ink/80 px-3 py-1 text-sm font-semibold text-white">Hết hàng</span>
    </div>
    <div class="p-4">
        <h3 class="text-lg font-bold" x-text="product.name"></h3>
        <p x-show="product.description" class="mt-1 line-clamp-2 text-stone-600" x-text="product.description"></p>
        <div class="mt-3 flex items-center justify-between gap-3">
            <p class="font-mono text-lg font-semibold text-latte-dark" x-text="money(product.price)"></p>
            <button
                type="button"
                x-on:click="quickAdd('product', product)"
                :disabled="isSoldOut(product)"
                class="flex h-11 items-center gap-1.5 rounded-xl bg-latte px-4 font-semibold text-white hover:bg-latte-dark disabled:bg-stone-300"
                :aria-label="'Thêm ' + product.name"
            >
                <x-icon name="plus" class="size-4.5" /> Thêm
            </button>
        </div>
    </div>
</article>
