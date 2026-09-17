<?php

namespace Nexor\Cms\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Nexor\Cms\Enums\FormFieldType;
use Nexor\Cms\Http\Requests\FeedbackFormRequest;
use Nexor\Cms\Http\Resources\FeedbackFormResource;
use Nexor\Cms\Models\Agreement;
use Nexor\Cms\Models\FeedbackForm;
use Nexor\Cms\Models\MailTemplate;
use Nexor\Cms\Support\ActivityLogger;
use Nexor\Cms\Support\Captcha\NexorCaptcha;
use Nexor\Cms\Support\FeedbackForms;
use Nexor\Cms\Support\FormCaptcha;
use Nexor\Cms\Support\FormTelegram;
use RuntimeException;

class FeedbackFormController extends ApiController
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $search = $request->string('search')->trim()->value();

        $forms = FeedbackForm::query()
            ->with(['mailTemplate', 'agreement'])
            ->withCount([
                'submissions',
                'submissions as unread_count' => fn ($query) => $query->where('is_read', false),
            ])
            ->when($search !== '', fn ($query) => $query->where(
                fn ($q) => $q->where('name', 'like', '%'.$search.'%')->orWhere('code', 'like', '%'.$search.'%'),
            ))
            ->ordered()
            ->paginate($this->perPage($request));

        return FeedbackFormResource::collection($forms);
    }

    /**
     * Справочники для экрана формы: шаблоны писем, соглашения, типы полей.
     */
    public function meta(): JsonResponse
    {
        return response()->json([
            'mail_templates' => MailTemplate::query()->ordered()->get(['id', 'code', 'name', 'to'])
                ->map(fn (MailTemplate $template) => [
                    'value' => $template->id,
                    'label' => $template->name.' ('.$template->code.')',
                    'to' => $template->to,
                ]),
            'agreements' => Agreement::query()->ordered()->get(['id', 'name', 'is_active'])
                ->map(fn (Agreement $agreement) => [
                    'value' => $agreement->id,
                    'label' => $agreement->name.($agreement->is_active ? '' : ' (выключено)'),
                ]),
            'field_types' => FormFieldType::options(),
            'file_extensions' => FormFieldType::FILE_EXTENSIONS,
            'base_placeholders' => FeedbackForms::placeholderNames(new FeedbackForm),
            'telegram_message' => FormTelegram::DEFAULT_MESSAGE,
            'captcha_gd' => NexorCaptcha::hasGd(),
        ]);
    }

    public function show(FeedbackForm $form): FeedbackFormResource
    {
        return FeedbackFormResource::make($this->fresh($form));
    }

    public function store(FeedbackFormRequest $request): JsonResponse
    {
        $form = DB::transaction(function () use ($request): FeedbackForm {
            $form = FeedbackForm::query()->create($this->attributes($request, null));
            $this->syncFields($form, $request->validated('fields', []));

            return $form;
        });

        ActivityLogger::created($form, 'Форма: '.$form->name);

        return FeedbackFormResource::make($this->fresh($form))
            ->additional(['message' => 'Форма «'.$form->name.'» создана.'])
            ->response()
            ->setStatusCode(201);
    }

    public function update(FeedbackFormRequest $request, FeedbackForm $form): JsonResponse
    {
        DB::transaction(function () use ($request, $form): void {
            $form->update($this->attributes($request, $form));
            $this->syncFields($form, $request->validated('fields', []));
        });

        ActivityLogger::updated($form, 'Форма: '.$form->name);

        return FeedbackFormResource::make($this->fresh($form))
            ->additional(['message' => 'Форма «'.$form->name.'» сохранена.'])
            ->response();
    }

    public function destroy(FeedbackForm $form): JsonResponse
    {
        ActivityLogger::deleted($form, 'Форма: '.$form->name);

        // По одной, чтобы вместе с записями ушли и их файлы.
        $form->submissions()->lazyById()->each->delete();
        $form->delete();

        return $this->ok('Форма удалена.');
    }

    /**
     * Проверочное сообщение в Telegram — теми данными, что сейчас в форме панели.
     */
    public function testTelegram(Request $request): JsonResponse
    {
        abort_unless($request->user()->hasPermission('forms.update') || $request->user()->hasPermission('forms.create'), 403);

        $data = $request->validate([
            'form_id' => ['nullable', 'integer'],
            'token' => ['nullable', 'string', 'max:100'],
            'chat_ids' => ['nullable', 'string', 'max:500'],
            'thread_id' => ['nullable', 'integer', 'min:1'],
        ]);

        $form = isset($data['form_id']) ? FeedbackForm::query()->find($data['form_id']) : null;

        try {
            FormTelegram::test($data, $form);
        } catch (RuntimeException $exception) {
            return $this->refuse($exception->getMessage());
        }

        return $this->ok('Сообщение отправлено — проверьте Telegram.');
    }

    /**
     * Колонки формы из запроса: настройки вкладок — с секретами, зашифрованными заново.
     *
     * @return array<string, mixed>
     */
    protected function attributes(FeedbackFormRequest $request, ?FeedbackForm $form): array
    {
        return [
            ...Arr::except($request->validated(), ['fields', 'telegram', 'protection']),
            'telegram' => FormTelegram::fromInput((array) $request->validated('telegram', []), $form?->telegram),
            'protection' => FormCaptcha::fromInput((array) $request->validated('protection', []), $form?->protection),
        ];
    }

    /**
     * Поля приходят списком целиком: порядок списка — сортировка, чего нет — удаляется.
     *
     * @param  array<int, array<string, mixed>>  $fields
     */
    protected function syncFields(FeedbackForm $form, array $fields): void
    {
        $submittedIds = array_filter(array_column($fields, 'id'));

        // Сначала убираем удалённые: новое поле может взять код удалённого.
        $form->fields()->whereKeyNot($submittedIds)->delete();

        $existing = $form->fields()->get()->keyBy('id');

        foreach (array_values($fields) as $index => $data) {
            $type = FormFieldType::from($data['type']);

            $attributes = [
                'code' => $data['code'],
                'label' => $data['label'],
                'type' => $type,
                'is_required' => (bool) ($data['is_required'] ?? false),
                'placeholder' => $type === FormFieldType::File ? null : ($data['placeholder'] ?? null),
                'settings' => $this->fieldSettings($type, $data['settings'] ?? []),
                'sort' => ($index + 1) * 10,
            ];

            $field = isset($data['id']) ? $existing->get($data['id']) : null;

            if ($field) {
                $field->update($attributes);
            } else {
                $form->fields()->create($attributes);
            }
        }
    }

    /**
     * Оставляет только настройки, которые имеют смысл для типа.
     *
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>|null
     */
    protected function fieldSettings(FormFieldType $type, array $settings): ?array
    {
        $allowed = match ($type) {
            FormFieldType::File => ['extensions', 'max_kb'],
            FormFieldType::Textarea => ['rows'],
            default => [],
        };

        $settings = array_filter(
            Arr::only($settings, $allowed),
            fn ($value) => $value !== null && $value !== '',
        );

        return $settings === [] ? null : $settings;
    }

    protected function fresh(FeedbackForm $form): FeedbackForm
    {
        return $form->fresh(['fields', 'mailTemplate', 'agreement'])->loadCount([
            'submissions',
            'submissions as unread_count' => fn ($query) => $query->where('is_read', false),
        ]);
    }
}
