<?php

namespace Nexor\Cms\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;
use Nexor\Cms\Http\Resources\FeedbackSubmissionResource;
use Nexor\Cms\Models\FeedbackForm;
use Nexor\Cms\Models\FeedbackSubmission;
use Nexor\Cms\Support\ActivityLogger;
use Nexor\Cms\Support\FeedbackForms;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Вкладка «Записи» формы: что прислали посетители.
 */
class FeedbackSubmissionController extends ApiController
{
    public function index(Request $request, FeedbackForm $form): AnonymousResourceCollection
    {
        $search = $request->string('search')->trim()->value();

        $submissions = $form->submissions()
            ->with('agreement:id,name')
            ->when($search !== '', fn ($query) => $query->where('data', 'like', '%'.$search.'%'))
            ->when($request->boolean('unread'), fn ($query) => $query->where('is_read', false))
            ->latest('id')
            ->paginate($this->perPage($request));

        return FeedbackSubmissionResource::collection($submissions)->additional([
            'meta' => ['unread' => $form->submissions()->where('is_read', false)->count()],
        ]);
    }

    /**
     * Открытая запись считается прочитанной.
     */
    public function show(FeedbackForm $form, FeedbackSubmission $submission): FeedbackSubmissionResource
    {
        if (! $submission->is_read) {
            $submission->update(['is_read' => true]);
        }

        return FeedbackSubmissionResource::make($submission->load('agreement:id,name'));
    }

    public function destroy(FeedbackForm $form, FeedbackSubmission $submission): JsonResponse
    {
        ActivityLogger::deleted($submission, 'Запись формы «'.$form->name.'» #'.$submission->id);
        $submission->delete();

        return $this->ok('Запись удалена.');
    }

    /**
     * Файл из записи — отдаётся только через админку, диск закрытый.
     */
    public function file(FeedbackForm $form, FeedbackSubmission $submission, string $field): StreamedResponse
    {
        $file = $submission->files()[$field] ?? null;
        $disk = Storage::disk(FeedbackForms::disk());

        abort_if(! $file || ! $disk->exists($file['path']), 404);

        return $disk->download($file['path'], $file['name']);
    }
}
