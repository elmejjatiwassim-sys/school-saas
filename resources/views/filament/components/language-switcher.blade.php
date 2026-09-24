@php
    $currentLocale = app()->getLocale();
    $locales = [
        'ar' => [
            'label' => 'العربية',
            'short' => 'عربي',
            'code' => 'AR',
            'flag' => '🇲🇦',
            'dir' => 'rtl',
        ],
        'fr' => [
            'label' => 'Français',
            'short' => 'FR',
            'code' => 'FR',
            'flag' => '🇫🇷',
            'dir' => 'ltr',
        ],
        'en' => [
            'label' => 'English',
            'short' => 'EN',
            'code' => 'EN',
            'flag' => '🇬🇧',
            'dir' => 'ltr',
        ],
    ];
@endphp

<div class="flex items-center gap-1 me-2">
    <x-filament::dropdown placement="bottom-end">
        <x-slot name="trigger">
            <button
                type="button"
                class="flex items-center gap-1.5 px-2.5 py-1.5 text-xs font-semibold rounded-lg bg-gray-100 hover:bg-gray-200 dark:bg-gray-800 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-300 transition border border-gray-200 dark:border-gray-700"
                title="Switch Language / تغيير اللغة"
            >
                <span class="text-sm leading-none">{{ $locales[$currentLocale]['flag'] ?? '🌐' }}</span>
                <span class="font-medium">{{ $locales[$currentLocale]['short'] ?? strtoupper($currentLocale) }}</span>
                <x-filament::icon icon="heroicon-m-chevron-down" class="w-3.5 h-3.5 text-gray-400" />
            </button>
        </x-slot>

        <x-filament::dropdown.list>
            @foreach($locales as $code => $data)
                <x-filament::dropdown.list.item
                    :href="route('locale.switch', ['locale' => $code])"
                    tag="a"
                    :icon="$currentLocale === $code ? 'heroicon-m-check' : null"
                >
                    <div class="flex items-center gap-2 text-xs">
                        <span class="text-sm leading-none">{{ $data['flag'] }}</span>
                        <span class="{{ $currentLocale === $code ? 'font-bold text-primary-600 dark:text-primary-400' : 'text-gray-700 dark:text-gray-300' }}">
                            {{ $data['label'] }} ({{ $data['code'] }})
                        </span>
                    </div>
                </x-filament::dropdown.list.item>
            @endforeach
        </x-filament::dropdown.list>
    </x-filament::dropdown>
</div>
