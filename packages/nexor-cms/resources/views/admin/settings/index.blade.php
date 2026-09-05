@extends('nexor::admin.layouts.app')

@section('title', 'Настройки')

@section('content')
    <x-nexor::admin.page-header title="Настройки сайта"
                         description="Общие параметры проекта, контакты и SEO." />

    <form method="POST" action="{{ route('admin.settings.update') }}" enctype="multipart/form-data"
          class="space-y-6">
        @csrf
        @method('PUT')

        @foreach ($groups as $group => $settings)
            <x-nexor::admin.card :title="$labels[$group] ?? $group">
                <div class="grid gap-5 sm:grid-cols-2">
                    @foreach ($settings as $setting)
                        @php $input = str_replace('.', '__', $setting->key); @endphp

                        <x-nexor::admin.field :label="$setting->name ?? $setting->key"
                                       :name="$setting->type === 'image' ? 'file_'.$input : 'settings.'.$input"
                                       :hint="$setting->hint"
                                       class="{{ in_array($setting->type, ['text', 'image'], true) ? 'sm:col-span-2' : '' }}">
                            @switch ($setting->type)
                                @case ('boolean')
                                    <x-nexor::admin.toggle :name="'settings['.$input.']'"
                                                    :checked="filter_var($setting->value, FILTER_VALIDATE_BOOLEAN)"
                                                    label="Включено" />
                                    @break

                                @case ('text')
                                    <x-nexor::admin.textarea :name="'settings['.$input.']'"
                                                      :value="old('settings.'.$input, $setting->value)" rows="5"
                                                      :class="Str::contains($setting->key, ['robots', 'counters']) ? 'font-mono text-xs' : ''" />
                                    @break

                                @case ('image')
                                    <x-nexor::admin.file-input :name="'file_'.$input" :value="$setting->value" accept="image/*" />
                                    @break

                                @case ('integer')
                                    <x-nexor::admin.input :name="'settings['.$input.']'" type="number"
                                                   :value="old('settings.'.$input, $setting->value)" />
                                    @break

                                @default
                                    <x-nexor::admin.input :name="'settings['.$input.']'"
                                                   :value="old('settings.'.$input, $setting->value)" />
                            @endswitch
                        </x-nexor::admin.field>
                    @endforeach
                </div>
            </x-nexor::admin.card>
        @endforeach

        <div class="flex items-center gap-2">
            <x-nexor::admin.button type="submit" size="lg">Сохранить настройки</x-nexor::admin.button>
        </div>
    </form>
@endsection
