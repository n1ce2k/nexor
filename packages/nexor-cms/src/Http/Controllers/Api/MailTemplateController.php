<?php

namespace Nexor\Cms\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Mail;
use Nexor\Cms\Http\Requests\MailTemplateRequest;
use Nexor\Cms\Http\Resources\MailTemplateResource;
use Nexor\Cms\Models\MailTemplate;
use Nexor\Cms\Support\ActivityLogger;
use Nexor\Cms\Support\MailConfig;

class MailTemplateController extends ApiController
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $templates = MailTemplate::query()
            ->when($request->filled('search'), fn ($query) => $query->where(
                fn ($q) => $q->where('name', 'like', '%'.$request->string('search')->trim().'%')
                    ->orWhere('code', 'like', '%'.$request->string('search')->trim().'%'),
            ))
            ->ordered()
            ->paginate($this->perPage($request));

        return MailTemplateResource::collection($templates);
    }

    public function show(MailTemplate $template): MailTemplateResource
    {
        return MailTemplateResource::make($template);
    }

    public function store(MailTemplateRequest $request): JsonResponse
    {
        $template = MailTemplate::query()->create($request->validated());

        ActivityLogger::created($template, 'Почтовый шаблон: '.$template->name);

        return MailTemplateResource::make($template)
            ->additional(['message' => 'Шаблон «'.$template->name.'» создан.'])
            ->response()
            ->setStatusCode(201);
    }

    public function update(MailTemplateRequest $request, MailTemplate $template): JsonResponse
    {
        $template->update($request->validated());

        ActivityLogger::updated($template, 'Почтовый шаблон: '.$template->name);

        return MailTemplateResource::make($template)
            ->additional(['message' => 'Шаблон «'.$template->name.'» сохранён.'])
            ->response();
    }

    public function destroy(MailTemplate $template): JsonResponse
    {
        ActivityLogger::deleted($template, 'Почтовый шаблон: '.$template->name);
        $template->delete();

        return $this->ok('Шаблон удалён.');
    }

    /**
     * Sends the template to one address so the SMTP setup can be checked.
     *
     * Placeholders are filled with their own names, which makes an unfilled
     * token obvious in the received message.
     */
    public function send(Request $request, MailTemplate $template): JsonResponse
    {
        $validated = $request->validate(['to' => ['required', 'email']]);

        MailConfig::apply();

        $data = collect($template->placeholders())
            ->mapWithKeys(fn (string $token) => [$token => '['.$token.']'])
            ->all();

        try {
            Mail::html(
                $template->isHtml()
                    ? $template->render('body', $data)
                    : nl2br(e($template->render('body', $data))),
                function ($message) use ($template, $validated, $data): void {
                    $message->to($validated['to'])->subject($template->render('subject', $data));

                    if ($template->from) {
                        $message->from($template->from);
                    }

                    if ($template->reply_to) {
                        $message->replyTo($template->reply_to);
                    }
                },
            );
        } catch (\Throwable $exception) {
            return $this->refuse('Письмо не отправлено: '.$exception->getMessage(), 502);
        }

        ActivityLogger::log('updated', $template, 'Тестовое письмо на '.$validated['to']);

        return $this->ok('Тестовое письмо отправлено на '.$validated['to'].'.');
    }
}
