{{--
    Поля формы заказа из админки: Магазин → Корзина → Поля заказа.

    Приходит: $fields.
--}}

<div class="space-y-4">
    @foreach ($fields as $field)
        @php($type = $field->type->value)

        <label class="block" wire:key="field-{{ $field->code }}">
            <span class="mb-1 block text-sm font-medium text-slate-700">
                {{ $field->name }}
                @if ($field->is_required)
                    <span class="text-red-500">*</span>
                @endif
            </span>

            @if ($type === 'textarea')
                <textarea wire:model="customer.{{ $field->code }}" rows="3"
                          class="w-full rounded-xl border border-slate-300 px-3 py-2 text-slate-900 focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 focus:outline-none"></textarea>
            @else
                <input wire:model="customer.{{ $field->code }}"
                       type="{{ ['email' => 'email', 'phone' => 'tel'][$type] ?? 'text' }}"
                       @if ($type === 'phone') autocomplete="tel" @elseif ($type === 'email') autocomplete="email" @endif
                       class="w-full rounded-xl border border-slate-300 px-3 py-2 text-slate-900 focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 focus:outline-none">
            @endif

            @error('customer.'.$field->code)
                <span class="mt-1 block text-sm text-red-600">{{ $message }}</span>
            @enderror
        </label>
    @endforeach
</div>
