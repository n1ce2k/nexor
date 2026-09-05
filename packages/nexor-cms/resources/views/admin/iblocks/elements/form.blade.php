@extends('nexor::admin.layouts.app')

@section('title', $element->exists ? $element->name : 'Новый элемент')

@section('content')
    <x-nexor::admin.page-header :title="$element->exists ? $element->name : 'Новый элемент'"
                         :back="route('admin.iblocks.elements.index', $iblock)"
                         :description="'Инфоблок «'.$iblock->name.'»'">
        <x-slot:breadcrumbs>
            <x-nexor::admin.breadcrumbs :items="[
                $iblock->name => route('admin.iblocks.elements.index', $iblock),
                ($element->exists ? $element->name : 'Новый элемент') => null,
            ]" />
        </x-slot:breadcrumbs>
    </x-nexor::admin.page-header>

    <form method="POST"
          action="{{ $element->exists
              ? route('admin.iblocks.elements.update', [$iblock, $element])
              : route('admin.iblocks.elements.store', $iblock) }}"
          enctype="multipart/form-data"
          x-data="slugField(@js(old('code', $element->code)))"
          class="grid gap-6 lg:grid-cols-3">
        @csrf
        @if ($element->exists)
            @method('PUT')
        @endif

        <div class="space-y-6 lg:col-span-2">
            <x-nexor::admin.card title="Основное">
                <div class="grid gap-5 sm:grid-cols-2">
                    <x-nexor::admin.field label="Название" name="name" required class="sm:col-span-2">
                        <x-nexor::admin.input name="name" :value="old('name', $element->name)" required @input="fromName($event)" />
                    </x-nexor::admin.field>

                    <x-nexor::admin.field label="Символьный код" name="code" hint="Используется в URL элемента.">
                        <x-nexor::admin.input name="code" x-model="code" @input="markTouched()" class="font-mono" />
                    </x-nexor::admin.field>

                    @if ($iblock->has_sections)
                        <x-nexor::admin.field label="Основной раздел" name="section_id">
                            <x-nexor::admin.select name="section_id" :selected="old('section_id', $element->section_id)"
                                            placeholder="— без раздела —"
                                            :options="$sections->pluck('indented_name', 'id')->all()" />
                        </x-nexor::admin.field>
                    @endif
                </div>
            </x-nexor::admin.card>

            @if ($properties->isNotEmpty())
                <x-nexor::admin.card title="Свойства"
                              :description="'Поля, настроенные для этого инфоблока ('.$properties->count().' шт.)'">
                    <div class="grid gap-5 sm:grid-cols-2">
                        @foreach ($properties as $property)
                            <x-nexor::admin.property-field :property="$property"
                                                    :value="$values[$property->code] ?? null"
                                                    class="{{ in_array($property->type->value, ['text', 'html', 'json'], true) || $property->is_multiple ? 'sm:col-span-2' : '' }}" />
                        @endforeach
                    </div>
                </x-nexor::admin.card>
            @elseif (auth()->user()->hasPermission('iblocks.update'))
                <x-nexor::admin.card title="Свойства">
                    <p class="text-sm text-[var(--text-muted)]">
                        У инфоблока пока нет собственных свойств.
                        <a href="{{ route('admin.iblocks.properties.create', $iblock) }}"
                           class="text-brand-600 hover:underline dark:text-brand-400">Добавить свойство</a>.
                    </p>
                </x-nexor::admin.card>
            @endif

            <x-nexor::admin.card title="Анонс">
                <div class="space-y-5">
                    <x-nexor::admin.field name="preview_picture" label="Картинка анонса">
                        <x-nexor::admin.file-input name="preview_picture" :value="$element->preview_picture" accept="image/*" />
                    </x-nexor::admin.field>

                    <x-nexor::admin.field name="preview_text" label="Текст анонса">
                        <x-nexor::admin.textarea name="preview_text" :value="old('preview_text', $element->preview_text)" rows="4" />
                    </x-nexor::admin.field>

                    <x-nexor::admin.field name="preview_text_type" label="Формат анонса">
                        <x-nexor::admin.select name="preview_text_type"
                                        :selected="old('preview_text_type', $element->preview_text_type ?? 'text')"
                                        :options="['text' => 'Обычный текст', 'html' => 'HTML']" />
                    </x-nexor::admin.field>
                </div>
            </x-nexor::admin.card>

            <x-nexor::admin.card title="Подробное описание">
                <div class="space-y-5">
                    <x-nexor::admin.field name="detail_picture" label="Детальная картинка">
                        <x-nexor::admin.file-input name="detail_picture" :value="$element->detail_picture" accept="image/*" />
                    </x-nexor::admin.field>

                    <x-nexor::admin.field name="detail_text" label="Текст">
                        <x-nexor::admin.textarea name="detail_text" :value="old('detail_text', $element->detail_text)"
                                          rows="12" class="font-mono text-xs" />
                    </x-nexor::admin.field>

                    <x-nexor::admin.field name="detail_text_type" label="Формат текста">
                        <x-nexor::admin.select name="detail_text_type"
                                        :selected="old('detail_text_type', $element->detail_text_type ?? 'html')"
                                        :options="['html' => 'HTML', 'text' => 'Обычный текст']" />
                    </x-nexor::admin.field>
                </div>
            </x-nexor::admin.card>

            <x-nexor::admin.card title="SEO">
                <div class="space-y-5">
                    <x-nexor::admin.field label="Заголовок страницы (title)" name="meta_title">
                        <x-nexor::admin.input name="meta_title" :value="old('meta_title', $element->meta_title)" />
                    </x-nexor::admin.field>

                    <x-nexor::admin.field label="Описание (description)" name="meta_description">
                        <x-nexor::admin.textarea name="meta_description" :value="old('meta_description', $element->meta_description)" rows="2" />
                    </x-nexor::admin.field>

                    <x-nexor::admin.field label="Ключевые слова" name="meta_keywords">
                        <x-nexor::admin.input name="meta_keywords" :value="old('meta_keywords', $element->meta_keywords)" />
                    </x-nexor::admin.field>
                </div>
            </x-nexor::admin.card>
        </div>

        <div class="space-y-6">
            <x-nexor::admin.card title="Публикация">
                <div class="space-y-5">
                    <x-nexor::admin.toggle name="is_active" label="Активен"
                                    :checked="old('is_active', $element->is_active ?? true)" />

                    <x-nexor::admin.field label="Начало активности" name="active_from">
                        <x-nexor::admin.input name="active_from" type="datetime-local"
                                       :value="old('active_from', $element->active_from?->format('Y-m-d\TH:i'))" />
                    </x-nexor::admin.field>

                    <x-nexor::admin.field label="Окончание активности" name="active_to">
                        <x-nexor::admin.input name="active_to" type="datetime-local"
                                       :value="old('active_to', $element->active_to?->format('Y-m-d\TH:i'))" />
                    </x-nexor::admin.field>

                    <x-nexor::admin.field label="Сортировка" name="sort">
                        <x-nexor::admin.input name="sort" type="number" min="0" :value="old('sort', $element->sort ?? 500)" />
                    </x-nexor::admin.field>
                </div>
            </x-nexor::admin.card>

            @if ($iblock->has_sections && $sections->isNotEmpty())
                <x-nexor::admin.card title="Дополнительные разделы"
                              description="Элемент будет показан и в этих разделах тоже.">
                    <div class="max-h-64 space-y-2.5 overflow-y-auto">
                        @foreach ($sections as $section)
                            <x-nexor::admin.checkbox name="sections[]" :value="$section->id" :hidden="false"
                                              :checked="in_array($section->id, old('sections', $selectedSections), false)"
                                              :label="$section->indented_name" />
                        @endforeach
                    </div>
                </x-nexor::admin.card>
            @endif

            @if ($element->exists)
                <x-nexor::admin.card title="Сведения">
                    <dl class="space-y-2.5 text-sm">
                        <div class="flex justify-between gap-3">
                            <dt class="text-[var(--text-muted)]">Создан</dt>
                            <dd class="text-[var(--text-strong)]">{{ $element->created_at?->format('d.m.Y H:i') }}</dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt class="text-[var(--text-muted)]">Автор</dt>
                            <dd class="text-[var(--text-strong)]">{{ $element->creator?->name ?? '—' }}</dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt class="text-[var(--text-muted)]">Изменён</dt>
                            <dd class="text-[var(--text-strong)]">{{ $element->updated_at?->format('d.m.Y H:i') }}</dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt class="text-[var(--text-muted)]">Кем</dt>
                            <dd class="text-[var(--text-strong)]">{{ $element->editor?->name ?? '—' }}</dd>
                        </div>
                    </dl>
                </x-nexor::admin.card>
            @endif

            <div class="flex items-center gap-2">
                <x-nexor::admin.button type="submit" size="lg">Сохранить</x-nexor::admin.button>
                <x-nexor::admin.button :href="route('admin.iblocks.elements.index', $iblock)" variant="secondary" size="lg">
                    Отмена
                </x-nexor::admin.button>
            </div>
        </div>
    </form>
@endsection
