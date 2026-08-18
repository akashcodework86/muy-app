<?php

namespace App\Services\Reports;

class ProgressReportSectionCatalog
{
    /** @return list<array{number: string, title: string, auto: bool}> */
    public static function tableOfContents(): array
    {
        return [
            ['number' => '1.', 'title' => 'Progress in Mukhyamantri Udyamshala Yojana (MUY)', 'auto' => true],
            ['number' => '2.', 'title' => 'Quantitative Progress - Plan vs Achievements', 'auto' => true],
            ['number' => '3.', 'title' => 'Mobilization and Outreach', 'auto' => true],
            ['number' => '3.1', 'title' => 'Call for Applications', 'auto' => true],
            ['number' => '3.2', 'title' => 'District Level Workshop', 'auto' => true],
            ['number' => '3.3', 'title' => 'Awareness/Outreach Activities for SHG members/CBOs', 'auto' => true],
            ['number' => '3.4', 'title' => 'Entrepreneurship Awareness Program / Entrepreneurship Development Program', 'auto' => true],
            ['number' => '3.5', 'title' => 'Outreach through Community organizations', 'auto' => true],
            ['number' => '4.', 'title' => 'Screening & Onboarding', 'auto' => true],
            ['number' => '5.', 'title' => 'Training and Capacity Building', 'auto' => true],
            ['number' => '6.', 'title' => 'Incubation Support Service offered under MUY', 'auto' => true],
            ['number' => '7.', 'title' => 'Mentorship', 'auto' => true],
            ['number' => '8.', 'title' => 'Partnership and Forward Linkages', 'auto' => true],
            ['number' => '9.', 'title' => 'Business Acceleration Services', 'auto' => true],
            ['number' => '10.', 'title' => 'Funding and Schematic Convergence', 'auto' => true],
            ['number' => '11.', 'title' => 'Branding, Communication & Knowledge Management', 'auto' => true],
            ['number' => '12.', 'title' => 'Meeting with Line Departments', 'auto' => true],
            ['number' => '13.', 'title' => 'Important Field Visits', 'auto' => true],
            ['number' => '14.', 'title' => 'Bootcamps and Marketing Drive', 'auto' => false],
            ['number' => '15.', 'title' => 'Additional Activities', 'auto' => false],
            ['number' => '16.', 'title' => 'IT & MIS', 'auto' => false],
            ['number' => '17.', 'title' => 'Project Risk Assessment and Mitigation Measures', 'auto' => false],
            ['number' => '18.', 'title' => 'Media Coverages', 'auto' => true],
            ['number' => '19.', 'title' => 'MUY Team Structure', 'auto' => true],
            ['number' => '20.', 'title' => 'Report Basis and Data Notes', 'auto' => true],
        ];
    }

    /** @return list<string> */
    public static function breakdownSerials(): array
    {
        return [
            '1.1',
            '1.2',
            '1.3',
            '1.4',
            '1.5',
            '2.1',
            '2.1.1',
            '3.1',
            '3.2',
            '3.3',
            '3.3.1',
            '3.4',
            '12.2',
            '10.4',
        ];
    }

    /** @return array<string, string> */
    public static function yellowPrompts(): array
    {
        return [
            'executive_summary' => '[TEAM: Add 2–4 paragraphs summarising key programme highlights, convergence efforts, partnerships and forward actions for this reporting period.]',
            'mobilization_intro' => '[TEAM: Add a short narrative introduction for mobilization and outreach activities during this period.]',
            'mobilization_3_1' => '[TEAM: Add narrative on Call for Application momentum, district focus areas or follow-up actions.]',
            'mobilization_3_2' => '[TEAM: Add narrative on district workshop outcomes and key takeaways.]',
            'mobilization_3_3' => '[TEAM: Add narrative on awareness/outreach themes and participant engagement.]',
            'mobilization_3_4' => '[TEAM: Add narrative on EAP/EDP session impact and enterprise interest generated.]',
            'mobilization_3_5' => '[TEAM: Add narrative on community organization outreach and partner engagement.]',
            'onboarding' => '[TEAM: Add narrative on screening, onboarding ceremonies and enterprise profile highlights.]',
            'training' => '[TEAM: Add narrative on training themes, capacity gaps addressed and participant feedback.]',
            'incubation' => '[TEAM: Add narrative linking incubation support services to enterprise outcomes.]',
            'mentorship' => '[TEAM: Add narrative on mentorship sessions, mentor profiles and incubatee outcomes.]',
            'partnership' => '[TEAM: Add narrative on partnership outreach, MoUs/LoIs and market linkage stories.]',
            'acceleration' => '[TEAM: Add narrative on acceleration/co-incubation activities and partner engagement.]',
            'funding' => '[TEAM: Add narrative on schematic convergence cases, funding amounts and success stories.]',
            'branding' => '[TEAM: Add narrative on branding, communication campaigns and knowledge products.]',
            'line_departments' => '[TEAM: Add narrative on line department meeting outcomes and follow-up actions.]',
            'field_visits' => '[TEAM: Add captions or visit purpose notes for photographs where required.]',
            'bootcamps' => '[TEAM: Describe bootcamps and marketing drives conducted during this period. No dedicated MIS module exists yet.]',
            'additional' => '[TEAM: List additional activities not captured elsewhere in MIS.]',
            'it_mis' => '[TEAM: Add IT/MIS updates such as system enhancements, rollout notes or support metrics.]',
            'risk' => '[TEAM: Complete the risk assessment table below with risks, impact, mitigation and status.]',
            'media' => '[TEAM: Add press coverage, clippings or commentary beyond MIS-logged IEC campaigns.]',
            'team' => '[TEAM: Add notes on vacancies, role changes or staffing updates for this period.]',
        ];
    }

    /** @return list<string> */
    public static function pillarSections(): array
    {
        return [
            'Business Formalization' => '6. Incubation Support Service offered under MUY',
            'Mentorship' => '7. Mentorship',
            'Partnership & Forward Linkages' => '8. Partnership and Forward Linkages',
            'Business Acceleration Services' => '9. Business Acceleration Services',
            'Funding & Schematic Convergence' => '10. Funding and Schematic Convergence',
            'Branding, Communication & Knowledge Management' => '11. Branding, Communication & Knowledge Management',
        ];
    }
}
