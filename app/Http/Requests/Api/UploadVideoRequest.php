<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class UploadVideoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'video' => [
                'required',
                'file',
                'mimetypes:video/mp4,video/mpeg,video/quicktime,video/x-msvideo,video/x-matroska,video/webm',
                'max:' . config('transcoder.max_upload_size', 2097152), // 2GB default in KB
            ],
            'metadata' => ['sometimes', 'array'],
            'metadata.*' => ['string'],
        ];
    }

    public function messages(): array
    {
        return [
            'video.required' => 'Please upload a video file.',
            'video.file' => 'The uploaded file must be a valid file.',
            'video.mimetypes' => 'The video must be in one of the following formats: MP4, MPEG, MOV, AVI, MKV, or WebM.',
            'video.max' => 'The video file size cannot exceed ' . (config('transcoder.max_upload_size', 2097152) / 1024) . 'MB.',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422)
        );
    }
}

