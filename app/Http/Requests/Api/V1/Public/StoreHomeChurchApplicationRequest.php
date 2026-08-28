<?php

namespace App\Http\Requests\Api\V1\Public;

use App\Church\MeetingDay;
use App\Models\AdministrativeUnit;
use App\Models\Church;
use App\Models\Location;
use App\Support\Church\PublicHomeChurchApplicationData;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreHomeChurchApplicationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'church_id' => [
                'required',
                'string',
                'ulid',
                Rule::exists(Church::class, 'public_id')->where(
                    fn (QueryBuilder $query): QueryBuilder => $query
                        ->whereNotNull('published_at')
                        ->where('published_at', '<=', now()->utc()),
                ),
            ],
            'location_id' => ['required', 'string', 'ulid', Rule::exists(Location::class, 'public_id')],
            'administrative_unit_id' => [
                'required',
                'string',
                'ulid',
                Rule::exists(AdministrativeUnit::class, 'public_id'),
            ],
            'applicant' => ['required', 'array:given_name,middle_name,family_name,preferred_name'],
            'applicant.given_name' => ['required', 'string', 'max:100'],
            'applicant.middle_name' => ['nullable', 'string', 'max:100'],
            'applicant.family_name' => ['required', 'string', 'max:100'],
            'applicant.preferred_name' => ['nullable', 'string', 'max:100'],
            'proposed_name' => ['required', 'string', 'max:191'],
            'expected_participants' => ['required', 'integer', 'min:1', 'max:65535'],
            'meeting_day' => ['required', Rule::enum(MeetingDay::class)],
            'meeting_time' => ['required', 'date_format:H:i'],
            'contact_email' => ['required', 'string', 'email:rfc', 'max:254'],
            'contact_phone' => ['required', 'string', 'max:32', 'regex:/\A\+?[0-9][0-9 ()-]{4,29}[0-9]\z/'],
            'guidelines_agreed' => ['required', 'accepted'],
            'idempotency_key' => ['required', 'string', 'min:8', 'max:191'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $headerKey = $this->header('Idempotency-Key');

        if (is_string($headerKey) && ! $this->exists('idempotency_key')) {
            $this->merge(['idempotency_key' => trim($headerKey)]);
        }
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $allowed = [
                'church_id',
                'location_id',
                'administrative_unit_id',
                'applicant',
                'proposed_name',
                'expected_participants',
                'meeting_day',
                'meeting_time',
                'contact_email',
                'contact_phone',
                'guidelines_agreed',
                'idempotency_key',
            ];
            $unsupported = array_diff(array_keys($this->all()), $allowed);

            if ($unsupported !== []) {
                $validator->errors()->add('request', 'The request contains unsupported fields.');
            }

            $headerKey = $this->header('Idempotency-Key');
            if (
                is_string($headerKey)
                && $this->exists('idempotency_key')
                && ! hash_equals(trim($headerKey), trim((string) $this->input('idempotency_key')))
            ) {
                $validator->errors()->add(
                    'idempotency_key',
                    'The Idempotency-Key header and idempotency_key field must match when both are provided.',
                );
            }
        }];
    }

    public function toData(): PublicHomeChurchApplicationData
    {
        /** @var array{given_name: string, middle_name?: string|null, family_name: string, preferred_name?: string|null} $applicant */
        $applicant = $this->validated('applicant');

        return new PublicHomeChurchApplicationData(
            churchPublicId: (string) $this->validated('church_id'),
            locationPublicId: (string) $this->validated('location_id'),
            administrativeUnitPublicId: (string) $this->validated('administrative_unit_id'),
            givenName: $applicant['given_name'],
            middleName: $applicant['middle_name'] ?? null,
            familyName: $applicant['family_name'],
            preferredName: $applicant['preferred_name'] ?? null,
            proposedName: (string) $this->validated('proposed_name'),
            expectedParticipants: (int) $this->validated('expected_participants'),
            meetingDay: MeetingDay::from((string) $this->validated('meeting_day')),
            meetingTime: (string) $this->validated('meeting_time'),
            contactEmail: (string) $this->validated('contact_email'),
            contactPhone: (string) $this->validated('contact_phone'),
            idempotencyKey: (string) $this->validated('idempotency_key'),
        );
    }
}
