<x-filament-widgets::widget>
    <div class="flex flex-col gap-4">
        @foreach ($problems as $problem)
            <x-filament::section icon="heroicon-o-exclamation-triangle" icon-color="danger">
                <x-slot name="heading">{{ $problem['title'] }}</x-slot>

                <p class="text-sm">{{ $problem['message'] }}</p>

                @if ($problem['url'])
                    <p class="mt-2 text-sm">
                        <x-filament::link :href="$problem['url']">Open Calendar Connection</x-filament::link>
                    </p>
                @endif
            </x-filament::section>
        @endforeach
    </div>
</x-filament-widgets::widget>
