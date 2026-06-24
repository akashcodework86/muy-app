<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Dedicated Field MIS approver (maker–checker for 3.3, 3.3.1, 12.2, 1.5)
    |--------------------------------------------------------------------------
    */
    'approver_email' => env('MIS_FIELD_APPROVER_EMAIL', 'aadil.ishrat@pwc.com'),

    'modules' => [
        'technical_training' => [
            'serial' => '3.3',
            'label' => 'Technical trainings to incubatees',
            'table' => 'technical_trainings',
            'model' => \App\Models\TechnicalTraining::class,
            'date_column' => 'event_date',
            'title_column' => 'session_name',
            'district_column' => 'district_id',
        ],
        'lakhpati_technical_training' => [
            'serial' => '3.3.1',
            'label' => 'Technical trainings (Lakhpati / SHG / CBO)',
            'table' => 'potential_lakhpati_technical_trainings',
            'model' => \App\Models\LakhpatiTechnicalTraining::class,
            'date_column' => 'session_date',
            'title_column' => 'session_title',
            'district_column' => 'district_id',
        ],
        'line_department_meeting' => [
            'serial' => '12.2',
            'label' => 'Line department meetings',
            'table' => 'line_department_meetings',
            'model' => \App\Models\LineDepartmentMeeting::class,
            'date_column' => 'meeting_date',
            'title_column' => 'department_name',
            'district_column' => 'district_id',
        ],
        'community_org_outreach' => [
            'serial' => '1.5',
            'label' => 'Community organization outreach',
            'table' => 'community_organization_outreach_visits',
            'model' => \App\Models\CommunityOrganizationOutreachVisit::class,
            'date_column' => 'visit_date',
            'title_column' => 'organization_name',
            'district_column' => 'district_id',
        ],
    ],

];
