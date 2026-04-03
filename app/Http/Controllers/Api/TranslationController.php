<?php

namespace App\Http\Controllers\Api;

use App\Actions\Translations\UpsertTranslationAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ExportTranslationsRequest;
use App\Http\Requests\Api\SearchTranslationsRequest;
use App\Http\Requests\Api\StoreTranslationRequest;
use App\Http\Requests\Api\UpdateTranslationRequest;
use App\Http\Resources\TranslationCollection;
use App\Http\Resources\TranslationResource;
use App\Models\Translation;
use App\Support\Translations\TranslationExport;
use App\Support\Translations\TranslationSearch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response as HttpResponse;
use Symfony\Component\HttpFoundation\Response;

class TranslationController extends Controller
{
    /**
     * Search translations.
     *
     * Return a paginated translation list filtered by locale, key, content,
     * and optional context tags.
     */
    public function index(
        SearchTranslationsRequest $request,
        TranslationSearch $translationSearch,
    ): TranslationCollection {
        return new TranslationCollection($translationSearch->handle([
            'locale' => $request->string('locale')->toString() ?: null,
            'key' => $request->string('key')->toString() ?: null,
            'content' => $request->string('content')->toString() ?: null,
            'tags' => $request->collect('tags')->filter()->values()->all(),
            'per_page' => $request->integer('per_page', 25),
        ]));
    }

    /**
     * Create a translation.
     *
     * Store a new translation entry for a locale and key combination and sync
     * any provided context tags.
     */
    public function store(
        StoreTranslationRequest $request,
        UpsertTranslationAction $upsertTranslation,
    ): JsonResponse {
        $translation = $upsertTranslation->handle($request->validated());

        return (new TranslationResource($translation))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Show a translation.
     *
     * Return a single translation resource with its locale, key, value, and
     * associated tags.
     */
    public function show(Translation $translation): TranslationResource
    {
        return new TranslationResource($translation->load('tags'));
    }

    /**
     * Update a translation.
     *
     * Modify an existing translation and resync its tags so the export output
     * reflects the latest content immediately.
     */
    public function update(
        UpdateTranslationRequest $request,
        Translation $translation,
        UpsertTranslationAction $upsertTranslation,
    ): TranslationResource {
        return new TranslationResource($upsertTranslation->handle($request->validated(), $translation));
    }

    /**
     * Export translations.
     *
     * Return frontend-ready nested JSON translation payloads for one locale or
     * all locales, with optional tag filtering.
     */
    public function export(
        ExportTranslationsRequest $request,
        TranslationExport $translationExport,
    ): HttpResponse {
        return response(
            $translationExport->toJson([
                'locale' => $request->string('locale')->toString() ?: null,
                'tags' => $request->collect('tags')->filter()->values()->all(),
            ]),
            headers: [
                'Content-Type' => 'application/json; charset=UTF-8',
            ]
        );
    }
}
