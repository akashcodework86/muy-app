<?php

namespace App\Services;

use App\Models\CfaSubmission;
use App\Models\District;
use App\Models\DistrictBlock;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator as ValidatorInstance;

class CfaSubmissionValidator
{
    /**
     * @return array<string, mixed>
     */
    public function validate(Request $request, User $staff, ?CfaSubmission $ignorePhoneUniqueFor = null): array
    {
        $districtName = $staff->district?->name ?? '';
        $districtId = (int) $staff->district_id;
        $blocks = $districtId > 0
            ? DistrictBlock::orderedNamesForDistrict($districtId)
            : [];
        if ($blocks === []) {
            $blocks = config('cfa.blocks_by_district.'.$districtName, []);
        }
        if ($districtName === '' || $blocks === []) {
            abort(500, 'Referral district is missing block directory data.');
        }

        $categories = config('cfa.categories');
        $castes = config('cfa.castes');
        $genders = config('cfa.genders');
        $eduIndividual = config('cfa.education_individual');
        $bizCats = config('cfa.business_categories');
        $bizAge = config('cfa.business_age');
        $regTypes = config('cfa.registration_types');
        $idProof = array_values(array_filter(config('cfa.id_proof_types')));
        $infoSources = config('cfa.info_sources');
        $trainingModes = config('cfa.training_modes');
        $techuse = config('cfa.techuse_options');
        $locTypes = config('cfa.location_types');
        $challengeValues = config('cfa.challenge_values');
        $expectationValues = config('cfa.expectation_values');

        $rules = [
            'category' => ['required', Rule::in($categories)],
            'applicant_name' => [
                Rule::requiredIf(fn () => $request->input('category') === 'Individual'),
                'nullable', 'string', 'max:191', 'regex:/^[A-Za-z.\-\'\s]+$/u',
            ],
            'shg_cbo_name' => [
                Rule::requiredIf(fn () => in_array($request->input('category'), ['SHG', 'CBO'], true)),
                'nullable', 'string', 'max:191',
            ],
            'guardian_name' => [
                Rule::requiredIf(fn () => $request->input('category') === 'Individual'),
                'nullable', 'string', 'max:191', 'regex:/^[A-Za-z.\-\'\s]*$/u',
            ],
            'gender' => ['required', Rule::in($genders)],
            'dob' => [
                'required',
                'date',
                Rule::when(
                    fn () => in_array($request->input('category'), ['SHG', 'CBO'], true),
                    [
                        'before_or_equal:today',
                        'after_or_equal:'.now()->subYears(100)->toDateString(),
                    ],
                    [
                        'before_or_equal:'.now()->subYears(17)->toDateString(),
                        'after_or_equal:'.now()->subYears(120)->toDateString(),
                    ]
                ),
            ],
            'caste' => [
                Rule::requiredIf(fn () => $request->input('category') === 'Individual'),
                'nullable', Rule::in($castes),
            ],
            'phone' => array_filter([
                'required',
                'regex:/^[6-9]\d{9}$/',
                $ignorePhoneUniqueFor
                    ? Rule::unique('cfa_submissions', 'phone')->ignore($ignorePhoneUniqueFor->id)
                    : Rule::unique('cfa_submissions', 'phone'),
            ]),
            'alt_mobile' => ['nullable', 'regex:/^[6-9]\d{9}$/'],
            'email' => ['nullable', 'email', 'max:191'],
            'village' => ['nullable', 'string', 'max:191'],
            'district' => ['required', 'string', Rule::in([$districtName])],
            'block' => ['required', 'string', Rule::in($blocks)],
            'pincode' => ['required', 'regex:/^[1-9]\d{5}$/'],
            'education' => [
                'required',
                Rule::when(
                    fn () => $request->input('category') === 'Individual',
                    [Rule::in($eduIndividual)],
                    [Rule::in(['NA'])]
                ),
            ],
            'id_proof_type' => ['nullable', Rule::in($idProof)],
            'id_proof_number' => ['nullable', 'string', 'max:120'],
            'is_member' => [
                Rule::requiredIf(fn () => $request->input('category') === 'Individual'),
                'nullable', Rule::in(['Yes', 'No']),
            ],
            'shg_name' => [
                Rule::requiredIf(fn () => $request->input('category') === 'Individual' && $request->input('is_member') === 'Yes'),
                'nullable', 'string', 'max:191',
            ],
            'lakhpati' => [
                Rule::requiredIf(fn () => $request->input('category') === 'Individual' && $request->input('is_member') === 'Yes'),
                'nullable', Rule::in(['Yes', 'No']),
            ],
            'is_registered' => ['required', Rule::in(['Yes', 'No'])],
            'registration_type' => ['nullable', Rule::in($regTypes)],
            'registration_type_other' => ['nullable', 'string', 'max:191'],
            'registration_number' => ['nullable', 'string', 'max:191'],
            'registration_date' => \App\Support\TodayOnlyDate::rules(false),
            'training_received' => ['required', Rule::in(['Yes', 'No'])],
            'training_institute' => ['nullable', 'string', 'max:191'],
            'turnover_last_fy' => ['required', 'string', 'max:32'],
            'current_employment' => ['required', Rule::in(['Yes', 'No'])],
            'employed_count' => ['nullable', 'integer', 'min:1', 'max:999999'],
            'business_age' => ['required', Rule::in($bizAge)],
            'loan_taken' => ['required', Rule::in(['Yes', 'No'])],
            'bank_loan' => ['nullable', 'string', 'max:32'],
            'regular_buyer' => ['nullable', Rule::in(['Yes', 'No'])],
            'buyer_count' => ['nullable', 'integer', 'min:1', 'max:999999'],
            'business_category' => ['required', Rule::in($bizCats)],
            'product' => ['nullable', 'string', 'max:120'],
            'other_product' => ['nullable', 'string', 'max:191'],
            'financial_support' => ['nullable', Rule::in(['Yes', 'No'])],
            'financial_amount' => ['nullable', 'string', 'max:32'],
            'location_type' => ['nullable', Rule::in($locTypes)],
            'challenges' => ['required', 'array', 'min:1'],
            'challenges.*' => [Rule::in($challengeValues)],
            'migrated_for_employment' => ['required', Rule::in(['Yes', 'No'])],
            'business_vision' => ['nullable', 'string', 'max:5000'],
            'training_mode' => ['required', Rule::in($trainingModes)],
            'info_source' => ['required', Rule::in($infoSources)],
            'resource_name' => ['nullable', 'string', 'max:191'],
            'department_name' => ['nullable', 'string', 'max:191'],
            'techuse' => ['required', Rule::in($techuse)],
            'sustainability' => ['required', Rule::in(['Yes', 'No'])],
            'empwomen' => ['required', Rule::in(['Yes', 'No'])],
            'expectations' => ['required', 'array', 'min:1'],
            'expectations.*' => [Rule::in($expectationValues)],
            'expectation_other_text' => ['nullable', 'string', 'max:500'],
            'consent' => ['accepted'],
        ];

        $validator = Validator::make($request->all(), $rules, [
            'dob.before_or_equal' => 'Minimum age is 17 years (individual). For SHG/CBO, formation date cannot be in the future.',
            'dob.after_or_equal' => 'Please choose a date within the allowed range.',
            'phone.unique' => 'This mobile number is already registered for an application. / यह मोबाइल नंबर पहले से पंजीकृत है।',
        ], [
            'phone' => 'mobile number',
        ]);

        $validator->after(function (ValidatorInstance $v) use ($request, $bizCats): void {
            $this->afterValidate($v, $request, $bizCats);
        });

        return $validator->validate();
    }

    /**
     * Validate a public walk-in CFA submission (no staff context).
     * District is self-selected by the applicant from any active district.
     *
     * @return array<string, mixed>
     */
    public function validatePublic(Request $request): array
    {
        $allDistrictNames = District::query()->orderBy('name')->pluck('name')->all();

        // Resolve blocks after district is known from request input
        $submittedDistrict = $request->input('district', '');
        $districtRow = District::query()->where('name', $submittedDistrict)->first();
        $blocks = $districtRow
            ? DistrictBlock::orderedNamesForDistrict((int) $districtRow->id)
            : [];
        if ($blocks === [] && $submittedDistrict !== '') {
            $blocks = config('cfa.blocks_by_district.'.$submittedDistrict, []);
        }

        $categories       = config('cfa.categories');
        $castes           = config('cfa.castes');
        $genders          = config('cfa.genders');
        $eduIndividual    = config('cfa.education_individual');
        $bizCats          = config('cfa.business_categories');
        $bizAge           = config('cfa.business_age');
        $regTypes         = config('cfa.registration_types');
        $idProof          = array_values(array_filter(config('cfa.id_proof_types')));
        $infoSources      = config('cfa.info_sources');
        $trainingModes    = config('cfa.training_modes');
        $techuse          = config('cfa.techuse_options');
        $locTypes         = config('cfa.location_types');
        $challengeValues  = config('cfa.challenge_values');
        $expectationValues = config('cfa.expectation_values');

        $rules = [
            'category'     => ['required', Rule::in($categories)],
            'applicant_name' => [
                Rule::requiredIf(fn () => $request->input('category') === 'Individual'),
                'nullable', 'string', 'max:191', 'regex:/^[A-Za-z.\-\'\s]+$/u',
            ],
            'shg_cbo_name' => [
                Rule::requiredIf(fn () => in_array($request->input('category'), ['SHG', 'CBO'], true)),
                'nullable', 'string', 'max:191',
            ],
            'guardian_name' => [
                Rule::requiredIf(fn () => $request->input('category') === 'Individual'),
                'nullable', 'string', 'max:191', 'regex:/^[A-Za-z.\-\'\s]*$/u',
            ],
            'gender'  => ['required', Rule::in($genders)],
            'dob'     => [
                'required', 'date',
                Rule::when(
                    fn () => in_array($request->input('category'), ['SHG', 'CBO'], true),
                    ['before_or_equal:today', 'after_or_equal:'.now()->subYears(100)->toDateString()],
                    ['before_or_equal:'.now()->subYears(17)->toDateString(), 'after_or_equal:'.now()->subYears(120)->toDateString()]
                ),
            ],
            'caste'   => [
                Rule::requiredIf(fn () => $request->input('category') === 'Individual'),
                'nullable', Rule::in($castes),
            ],
            'phone'   => [
                'required',
                'regex:/^[6-9]\d{9}$/',
                Rule::unique('cfa_submissions', 'phone'),
            ],
            'alt_mobile'     => ['nullable', 'regex:/^[6-9]\d{9}$/'],
            'email'          => ['nullable', 'email', 'max:191'],
            'village'        => ['nullable', 'string', 'max:191'],
            // Open district/block for public form
            'district'       => ['required', 'string', Rule::in($allDistrictNames)],
            'block'          => ['required', 'string', $blocks !== [] ? Rule::in($blocks) : 'string'],
            'pincode'        => ['required', 'regex:/^[1-9]\d{5}$/'],
            'education'      => [
                'required',
                Rule::when(
                    fn () => $request->input('category') === 'Individual',
                    [Rule::in($eduIndividual)],
                    [Rule::in(['NA'])]
                ),
            ],
            'id_proof_type'   => ['nullable', Rule::in($idProof)],
            'id_proof_number' => ['nullable', 'string', 'max:120'],
            'is_member'       => [
                Rule::requiredIf(fn () => $request->input('category') === 'Individual'),
                'nullable', Rule::in(['Yes', 'No']),
            ],
            'shg_name'    => [
                Rule::requiredIf(fn () => $request->input('category') === 'Individual' && $request->input('is_member') === 'Yes'),
                'nullable', 'string', 'max:191',
            ],
            'lakhpati'    => [
                Rule::requiredIf(fn () => $request->input('category') === 'Individual' && $request->input('is_member') === 'Yes'),
                'nullable', Rule::in(['Yes', 'No']),
            ],
            'is_registered'           => ['required', Rule::in(['Yes', 'No'])],
            'registration_type'       => ['nullable', Rule::in($regTypes)],
            'registration_type_other' => ['nullable', 'string', 'max:191'],
            'registration_number'     => ['nullable', 'string', 'max:191'],
            'registration_date'       => \App\Support\TodayOnlyDate::rules(false),
            'training_received'       => ['required', Rule::in(['Yes', 'No'])],
            'training_institute'      => ['nullable', 'string', 'max:191'],
            'turnover_last_fy'        => ['required', 'string', 'max:32'],
            'current_employment'      => ['required', Rule::in(['Yes', 'No'])],
            'employed_count'          => ['nullable', 'integer', 'min:1', 'max:999999'],
            'business_age'            => ['required', Rule::in($bizAge)],
            'loan_taken'              => ['required', Rule::in(['Yes', 'No'])],
            'bank_loan'               => ['nullable', 'string', 'max:32'],
            'regular_buyer'           => ['nullable', Rule::in(['Yes', 'No'])],
            'buyer_count'             => ['nullable', 'integer', 'min:1', 'max:999999'],
            'business_category'       => ['required', Rule::in($bizCats)],
            'product'                 => ['nullable', 'string', 'max:120'],
            'other_product'           => ['nullable', 'string', 'max:191'],
            'financial_support'       => ['nullable', Rule::in(['Yes', 'No'])],
            'financial_amount'        => ['nullable', 'string', 'max:32'],
            'location_type'           => ['nullable', Rule::in($locTypes)],
            'challenges'              => ['required', 'array', 'min:1'],
            'challenges.*'            => [Rule::in($challengeValues)],
            'migrated_for_employment' => ['required', Rule::in(['Yes', 'No'])],
            'business_vision'         => ['nullable', 'string', 'max:5000'],
            'training_mode'           => ['required', Rule::in($trainingModes)],
            'info_source'             => ['required', Rule::in($infoSources)],
            'resource_name'           => ['nullable', 'string', 'max:191'],
            'department_name'         => ['nullable', 'string', 'max:191'],
            'techuse'                 => ['required', Rule::in($techuse)],
            'sustainability'          => ['required', Rule::in(['Yes', 'No'])],
            'empwomen'                => ['required', Rule::in(['Yes', 'No'])],
            'expectations'            => ['required', 'array', 'min:1'],
            'expectations.*'          => [Rule::in($expectationValues)],
            'expectation_other_text'  => ['nullable', 'string', 'max:500'],
            'public_cfa_submit_mode'  => ['required', Rule::in(['self', 'gdc_team'])],
            'consent'                 => ['accepted'],
        ];

        $validator = Validator::make($request->all(), $rules, [
            'dob.before_or_equal' => 'Minimum age is 17 years (individual). For SHG/CBO, formation date cannot be in the future.',
            'dob.after_or_equal'  => 'Please choose a date within the allowed range.',
            'phone.unique'        => 'This mobile number is already registered for an application. / यह मोबाइल नंबर पहले से पंजीकृत है।',
        ], [
            'phone' => 'mobile number',
        ]);

        $validator->after(function (ValidatorInstance $v) use ($request, $bizCats): void {
            $this->afterValidate($v, $request, $bizCats);
        });

        return $validator->validate();
    }

    private function afterValidate(ValidatorInstance $validator, Request $request, array $bizCats): void
    {
        if ($request->input('is_registered') === 'Yes') {
            $type = trim((string) $request->input('registration_type'));
            if ($type === '') {
                $validator->errors()->add('registration_type', 'Type of registration is required when enterprise is registered.');
            }
            if ($type === 'Other' && trim((string) $request->input('registration_type_other')) === '') {
                $validator->errors()->add('registration_type_other', 'Please specify the registration type when Other is selected.');
            }
            if (trim((string) $request->input('registration_number')) === '') {
                $validator->errors()->add('registration_number', 'Registration number is required when enterprise is registered.');
            }
        }

        $turnoverRaw = str_replace(',', '', trim((string) $request->input('turnover_last_fy')));
        if ($turnoverRaw === '' || ! is_numeric($turnoverRaw)) {
            $validator->errors()->add('turnover_last_fy', 'Enter a valid turnover amount (numbers only).');
        }

        if ($request->input('current_employment') === 'Yes') {
            $c = $request->input('employed_count');
            if ($c === null || $c === '' || (int) $c < 1) {
                $validator->errors()->add('employed_count', 'Enter how many people are employed (at least 1).');
            }
        }

        if ($request->input('loan_taken') === 'Yes' && trim((string) $request->input('bank_loan')) !== '') {
            $raw = str_replace(',', '', trim((string) $request->input('bank_loan')));
            if (! is_numeric($raw)) {
                $validator->errors()->add('bank_loan', 'Enter a valid bank loan amount.');
            }
        }

        if ($request->input('regular_buyer') === 'Yes') {
            $c = $request->input('buyer_count');
            if ($c === null || $c === '' || (int) $c < 1) {
                $validator->errors()->add('buyer_count', 'Enter number of marketing partners (at least 1).');
            }
        }

        $bc = $request->input('business_category');
        $product = trim((string) $request->input('product'));
        if ($bc && in_array($bc, $bizCats, true)) {
            $allowed = config('cfa.products_by_category.'.$bc, []);
            if ($product !== '' && ! in_array($product, $allowed, true)) {
                $validator->errors()->add('product', 'Selected product is not valid for this business category.');
            }
        }
        if ($product === 'Others' && trim((string) $request->input('other_product')) === '') {
            $validator->errors()->add('other_product', 'Please specify the product when Others is selected.');
        }

        if ($request->input('financial_support') === 'Yes') {
            $fa = str_replace(',', '', trim((string) $request->input('financial_amount')));
            if ($fa === '' || ! is_numeric($fa)) {
                $validator->errors()->add('financial_amount', 'Enter a valid financial support amount.');
            }
        }

        $exp = $request->input('expectations', []);
        if (is_array($exp) && in_array('Other', $exp, true) && trim((string) $request->input('expectation_other_text')) === '') {
            $validator->errors()->add('expectation_other_text', 'Please specify your expectation when Other is selected.');
        }

        if ($request->input('info_source') === 'Department') {
            $dn = trim((string) $request->input('department_name'));
            if ($dn === '' || ! in_array($dn, config('cfa.department_names'), true)) {
                $validator->errors()->add('department_name', 'Please select a department.');
            }
        }
    }
}
