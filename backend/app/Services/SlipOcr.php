<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class SlipOcr
{
    /**
     * @return array{
     *   name: string|null,
     *   phone: string|null,
     *   gender: 'male'|'female'|null,
     *   style: string|null,
     *   note: string|null,
     *   template_slug: string,
     *   unit: 'cm'|'in',
     *   measurements: array<string, float>
     * }
     */
    public function read(UploadedFile $image): array
    {
        $driver = (string) config('services.slip_ocr.driver', 'openai');

        if ($driver === 'fake') {
            return $this->fakeResult();
        }

        $apiKey = config('services.slip_ocr.api_key');

        if (! is_string($apiKey) || trim($apiKey) === '') {
            throw new RuntimeException(
                'Slip scanning is not configured. Set SLIP_OCR_API_KEY (or OPENAI_API_KEY) on the server.',
            );
        }

        $mime = $image->getMimeType() ?: 'image/jpeg';
        $base64 = base64_encode((string) file_get_contents($image->getRealPath()));
        $dataUrl = 'data:'.$mime.';base64,'.$base64;

        $prompt = <<<'PROMPT'
Read this handwritten or printed tailor measurement slip from Nigeria.
Return JSON only with these keys:
- name: person name or null
- phone: mobile number digits as written or null
- gender: "male", "female", or null
- style: garment/style name or null
- note: other free text notes or null
- template_slug: "female", "male", or "child"
- unit: "in" or "cm" (prefer inches if unclear)
- measurements: object of field_key -> positive number

Use only these measurement keys when present:
bust, waist, hip, shoulder, sleeve_length, armhole, neck, blouse_length, gown_length, skirt_length, trouser_length, thigh, knee, ankle, chest, stomach, biceps, shirt_length, inseam, outseam, sleeve, length.

Map common labels (e.g. bust/chest, SL/sleeve, TL/trouser) to those keys.
Ignore money amounts. If a value is unclear, omit that measurement.
PROMPT;

        try {
            $response = Http::baseUrl((string) config('services.slip_ocr.base_url'))
                ->withToken(trim($apiKey))
                ->acceptJson()
                ->timeout((int) config('services.slip_ocr.timeout', 60))
                ->post('/chat/completions', [
                    'model' => (string) config('services.slip_ocr.model', 'gpt-4o-mini'),
                    'temperature' => 0,
                    'response_format' => ['type' => 'json_object'],
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => 'You extract structured tailor slip data. Reply with valid JSON only.',
                        ],
                        [
                            'role' => 'user',
                            'content' => [
                                ['type' => 'text', 'text' => $prompt],
                                [
                                    'type' => 'image_url',
                                    'image_url' => [
                                        'url' => $dataUrl,
                                    ],
                                ],
                            ],
                        ],
                    ],
                ]);
        } catch (Throwable $exception) {
            throw new RuntimeException('Could not reach the slip reader. Check your connection and try again.', 0, $exception);
        }

        if (! $response->successful()) {
            throw new RuntimeException('The slip reader could not read that photo. Try a clearer picture.');
        }

        $content = data_get($response->json(), 'choices.0.message.content');

        if (! is_string($content) || trim($content) === '') {
            throw new RuntimeException('The slip reader returned an empty result.');
        }

        $decoded = json_decode($content, true);

        if (! is_array($decoded)) {
            throw new RuntimeException('The slip reader returned invalid data.');
        }

        return $this->normalize($decoded);
    }

    /**
     * @param  array<string, mixed>  $raw
     * @return array{
     *   name: string|null,
     *   phone: string|null,
     *   gender: 'male'|'female'|null,
     *   style: string|null,
     *   note: string|null,
     *   template_slug: string,
     *   unit: 'cm'|'in',
     *   measurements: array<string, float>
     * }
     */
    public function normalize(array $raw): array
    {
        $allowed = [
            'bust', 'waist', 'hip', 'shoulder', 'sleeve_length', 'armhole', 'neck',
            'blouse_length', 'gown_length', 'skirt_length', 'trouser_length', 'thigh',
            'knee', 'ankle', 'chest', 'stomach', 'biceps', 'shirt_length', 'inseam',
            'outseam', 'sleeve', 'length',
        ];

        $aliases = [
            'bust_chest' => 'bust',
            'chest_bust' => 'chest',
            'sl' => 'sleeve_length',
            'sleeve' => 'sleeve_length',
            'tl' => 'trouser_length',
            'trouser' => 'trouser_length',
            'round_neck' => 'neck',
            'shoulder_width' => 'shoulder',
        ];

        $measurements = [];
        $rawMeasurements = $raw['measurements'] ?? [];

        if (is_array($rawMeasurements)) {
            foreach ($rawMeasurements as $key => $value) {
                $field = Str::snake(strtolower(trim((string) $key)));
                $field = $aliases[$field] ?? $field;

                if (! in_array($field, $allowed, true)) {
                    continue;
                }

                if (! is_numeric($value)) {
                    continue;
                }

                $number = (float) $value;

                if ($number <= 0 || $number > 500) {
                    continue;
                }

                $measurements[$field] = round($number, 2);
            }
        }

        $gender = $raw['gender'] ?? null;
        $gender = is_string($gender) ? strtolower(trim($gender)) : null;
        if (! in_array($gender, ['male', 'female'], true)) {
            $gender = null;
        }

        $template = $raw['template_slug'] ?? null;
        $template = is_string($template) ? strtolower(trim($template)) : 'female';
        if (! in_array($template, ['female', 'male', 'child'], true)) {
            $template = $gender === 'male' ? 'male' : 'female';
        }

        $unit = $raw['unit'] ?? 'in';
        $unit = is_string($unit) ? strtolower(trim($unit)) : 'in';
        if (! in_array($unit, ['cm', 'in'], true)) {
            $unit = 'in';
        }

        $phone = $this->digitsPhone($raw['phone'] ?? null);
        $name = $this->nullableText($raw['name'] ?? null, 120);
        $style = $this->nullableText($raw['style'] ?? null, 120);
        $note = $this->nullableText($raw['note'] ?? null, 500);

        return [
            'name' => $name,
            'phone' => $phone,
            'gender' => $gender,
            'style' => $style,
            'note' => $note,
            'template_slug' => $template,
            'unit' => $unit,
            'measurements' => $measurements,
        ];
    }

    /**
     * @return array{
     *   name: string|null,
     *   phone: string|null,
     *   gender: 'male'|'female'|null,
     *   style: string|null,
     *   note: string|null,
     *   template_slug: string,
     *   unit: 'cm'|'in',
     *   measurements: array<string, float>
     * }
     */
    private function fakeResult(): array
    {
        return [
            'name' => 'Ada Okafor',
            'phone' => '08031234567',
            'gender' => 'female',
            'style' => 'Ceremony dress',
            'note' => 'Needed for Saturday',
            'template_slug' => 'female',
            'unit' => 'in',
            'measurements' => [
                'bust' => 36.0,
                'waist' => 28.0,
                'hip' => 40.0,
                'shoulder' => 15.0,
            ],
        ];
    }

    private function nullableText(mixed $value, int $max): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $text = trim(preg_replace('/\s+/', ' ', $value) ?? '');

        if ($text === '') {
            return null;
        }

        return Str::limit($text, $max, '');
    }

    private function digitsPhone(mixed $value): ?string
    {
        if (! is_string($value) && ! is_numeric($value)) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', (string) $value) ?? '';

        if (strlen($digits) < 7 || strlen($digits) > 15) {
            return null;
        }

        return $digits;
    }
}
