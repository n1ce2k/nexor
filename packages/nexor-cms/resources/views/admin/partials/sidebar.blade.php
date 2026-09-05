@php
    $groups = \Nexor\Cms\Support\Navigation::forUser(auth()->user());
@endphp

{{-- Mobile backdrop --}}
<div x-show="$store.sidebar.mobileOpen"
     x-cloak
     x-transition.opacity
     @click="$store.sidebar.mobileOpen = false"
     class="fixed inset-0 z-40 bg-slate-900/50 backdrop-blur-sm lg:hidden"></div>

<aside class="sidebar fixed inset-y-0 left-0 z-50 flex flex-col border-r transition-all duration-200 lg:translate-x-0"
       :class="[
           $store.sidebar.collapsed ? 'w-[4.5rem]' : 'w-64',
           $store.sidebar.mobileOpen ? 'translate-x-0' : '-translate-x-full',
       ]">

    <div class="flex h-16 shrink-0 items-center gap-2.5 px-4">
        <a href="{{ route('admin.dashboard') }}" class="flex min-w-0 items-center gap-2.5">
            <span class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-brand-600 text-sm font-bold text-white">N</span>
            <span class="min-w-0 truncate text-sm font-semibold text-white" x-show="! $store.sidebar.collapsed" x-cloak>
                {{ \Nexor\Cms\Models\Setting::get('site.name', config('app.name')) }}
            </span>
        </a>
    </div>

    <nav class="flex-1 space-y-6 overflow-y-auto px-3 pb-4">
        @foreach ($groups as $group)
            <div>
                @if ($group['label'])
                    <p class="sidebar-label px-3 pb-2 text-[0.65rem] font-semibold tracking-wider uppercase"
                       x-show="! $store.sidebar.collapsed" x-cloak>
                        {{ $group['label'] }}
                    </p>
                @endif

                <ul class="space-y-0.5">
                    @foreach ($group['items'] as $item)
                        @if (isset($item['children']))
                            <li x-data="{ open: @js($item['active']) }">
                                <button type="button"
                                        @click="$store.sidebar.collapsed ? $store.sidebar.toggle() : (open = ! open)"
                                        class="sidebar-link w-full {{ $item['active'] ? 'is-active' : '' }}">
                                    <x-nexor::admin.icon :name="$item['icon']" class="size-5 shrink-0" />
                                    <span class="min-w-0 flex-1 truncate text-left" x-show="! $store.sidebar.collapsed" x-cloak>
                                        {{ $item['label'] }}
                                    </span>
                                    <x-nexor::admin.icon name="chevron-down" class="size-3.5 shrink-0 transition"
                                                  x-show="! $store.sidebar.collapsed" x-cloak
                                                  ::class="open ? 'rotate-180' : ''" />
                                </button>

                                <ul x-show="open && ! $store.sidebar.collapsed" x-collapse x-cloak
                                    class="sidebar-sublist mt-0.5 ml-5 space-y-0.5 border-l pl-3">
                                    @foreach ($item['children'] as $child)
                                        <li>
                                            <a href="{{ $child['url'] }}"
                                               class="sidebar-link block truncate py-1.5 {{ $child['active'] ? 'is-active' : '' }}">
                                                {{ $child['label'] }}
                                            </a>
                                        </li>
                                    @endforeach
                                </ul>
                            </li>
                        @else
                            <li>
                                <a href="{{ $item['url'] }}"
                                   class="sidebar-link {{ $item['active'] ? 'is-active' : '' }}"
                                   :title="$store.sidebar.collapsed ? @js($item['label']) : null">
                                    <x-nexor::admin.icon :name="$item['icon']" class="size-5 shrink-0" />
                                    <span class="min-w-0 truncate" x-show="! $store.sidebar.collapsed" x-cloak>
                                        {{ $item['label'] }}
                                    </span>
                                </a>
                            </li>
                        @endif
                    @endforeach
                </ul>
            </div>
        @endforeach
    </nav>

    <div class="sidebar-footer shrink-0 border-t p-3">
        <button type="button" @click="$store.sidebar.toggle()" class="sidebar-link hidden w-full lg:flex">
            <x-nexor::admin.icon name="chevron-left" class="size-5 shrink-0 transition"
                          ::class="$store.sidebar.collapsed ? 'rotate-180' : ''" />
            <span x-show="! $store.sidebar.collapsed" x-cloak>Свернуть меню</span>
        </button>
    </div>
</aside>

