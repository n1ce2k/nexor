{{--
    Блок «Таблица».

    Приходит: $rows — ряды ячеек одной ширины, $header — первый ряд из <th>,
    $showTitle, $title, $titleTag (h2–h5), $cssClass, $blockId, $element.
--}}

@php($titleTag = in_array($titleTag ?? 'h3', ['h2', 'h3', 'h4', 'h5'], true) ? $titleTag : 'h3')

@if (! empty($rows))
    <section class="pb-block pb-table {{ $cssClass }}">
        <div class="pb-container">

            @if ($showTitle && $title)
                <{{ $titleTag }} class="pb-table__title">
                    {{ $title }}
                </{{ $titleTag }}>
            @endif

            <div class="pb-table__responsive">
                <table>
                    <tbody>
                        @foreach ($rows as $rowIndex => $row)
                            <tr>
                                @foreach ($row as $cell)
                                    @if ($header && $rowIndex === 0)
                                        <th>{{ $cell }}</th>
                                    @else
                                        <td>{{ $cell }}</td>
                                    @endif
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </section>
@endif
