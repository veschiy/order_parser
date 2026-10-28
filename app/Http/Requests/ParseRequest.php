<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ParseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'mode' => ['required', 'in:rss,single'],
            'rss_url' => ['required_if:mode,rss', 'url'],
            'max_items' => ['nullable', 'integer', 'min:1', 'max:100'],
            'document_url' => ['required_if:mode,single', 'url'],
        ];
    }
}
