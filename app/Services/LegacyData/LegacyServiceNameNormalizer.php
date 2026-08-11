<?php

namespace App\Services\LegacyData;

use App\Models\LegacyServiceAlias;
use App\Models\Service;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

final class LegacyServiceNameNormalizer
{
    public const UNMAPPED = 'Unmapped legacy service';

    /** @var Collection<string,Service>|null */
    private ?Collection $servicesByName = null;

    /** @var Collection<string,Service>|null */
    private ?Collection $servicesByCode = null;

    /** @var Collection<string,LegacyServiceAlias>|null */
    private ?Collection $manualAliases = null;

    /**
     * @return array{label:string,original_label:string,mapped:bool,mapping_source:string,service_id:?int}
     */
    public function resolve(string $originalName, string $phase): array
    {
        $originalName = trim($originalName);
        if ($phase === 'Phase 3') {
            $service = $this->serviceByName($originalName)
                ?? $this->suggestedService($this->normalizeKey($originalName));

            return $this->result(
                $service?->name ?? $originalName,
                $originalName,
                true,
                $service ? 'phase3_master' : 'phase3_source',
                $service,
            );
        }

        $sourcePhase = $this->sourcePhase($phase);
        $key = $this->normalizeKey($originalName);
        $manual = $this->manualAliases()->get($sourcePhase.'|'.$key);
        if ($manual?->service) {
            return $this->result($manual->service->name, $originalName, true, 'manual', $manual->service);
        }

        $exact = $this->servicesByName()->get($key);
        if ($exact) {
            return $this->result($exact->name, $originalName, true, 'exact', $exact);
        }

        $suggested = $this->suggestedService($key);
        if ($suggested) {
            return $this->result($suggested->name, $originalName, true, 'automatic', $suggested);
        }

        return $this->result(self::UNMAPPED, $originalName, false, 'unmapped', null);
    }

    public function normalizeKey(string $name): string
    {
        $name = Str::lower(trim($name));
        $name = str_replace(['&', '–', '—', '·', '/', '_'], [' and ', '-', '-', ' ', ' ', ' '], $name);
        $name = preg_replace('/[^a-z0-9]+/u', ' ', $name) ?: '';

        return trim(preg_replace('/\s+/', ' ', $name) ?: '');
    }

    public function sourcePhase(string $phase): string
    {
        return match ($phase) {
            'Phase 1' => 'phase1',
            'Phase 2' => 'phase2',
            default => 'phase3',
        };
    }

    public function clearRuntimeCache(): void
    {
        $this->servicesByName = null;
        $this->servicesByCode = null;
        $this->manualAliases = null;
    }

    /** @return Collection<int,Service> */
    public function availableServices(): Collection
    {
        return $this->servicesByCode()->values()->sortBy('name')->values();
    }

    /** @return Collection<string,Service> */
    private function servicesByName(): Collection
    {
        return $this->servicesByName ??= Service::query()
            ->orderBy('name')
            ->get()
            ->keyBy(fn (Service $service): string => $this->normalizeKey($service->name));
    }

    /** @return Collection<string,Service> */
    private function servicesByCode(): Collection
    {
        return $this->servicesByCode ??= Service::query()
            ->orderBy('name')
            ->get()
            ->keyBy('code');
    }

    /** @return Collection<string,LegacyServiceAlias> */
    private function manualAliases(): Collection
    {
        if ($this->manualAliases !== null) {
            return $this->manualAliases;
        }
        if (! Schema::hasTable('legacy_service_aliases')) {
            return $this->manualAliases = collect();
        }

        return $this->manualAliases = LegacyServiceAlias::query()
            ->where('is_active', true)
            ->with('service')
            ->get()
            ->keyBy(fn (LegacyServiceAlias $alias): string => $alias->source_phase.'|'.$alias->normalized_name);
    }

    private function serviceByName(string $name): ?Service
    {
        return $this->servicesByName()->get($this->normalizeKey($name));
    }

    private function suggestedService(string $key): ?Service
    {
        $candidates = match (true) {
            preg_match('/\b(bmc|business model canvas)\b/', $key) === 1 => ['Business Model Canvas (BMC)', 'Business Model Canvas', 'business_model_canvas_b_m_c', 'bmc_canvas'],
            str_contains($key, 'fssai') => ['FSSAI Registration/Renewal', 'FSSAI', 'f_s_s_a_i_registration_renewal', 'fssai'],
            preg_match('/\budyam\b/', $key) === 1 => ['Udyam Registration', 'udyam_registration'],
            str_contains($key, 'shop establishment') => ['Shop establishment', 'Shop & Establishment', 'shop_establishment'],
            str_contains($key, 'utdb registration') => ['UTDB Registration', 'u_t_d_b_registration', 'utdb_registration_bf'],
            str_contains($key, 'company registration') => ['Company Registration', 'company_registration'],
            str_contains($key, 'uk firm registration') => ['UK Firm Registration', 'u_k_firm_registration', 'uk_firm_registration'],
            str_contains($key, 'cooperative') => ['Cooperative registration', 'Cooperative', 'cooperative_registration', 'cooperative'],
            str_contains($key, 'gi seller') => ['GI Seller Registration', 'g_i_seller_registration', 'gi_seller_registration'],
            str_contains($key, 'trademark') => ['Trademark Application filling', 'Trademark', 'trademark_application_filling', 'trademark'],
            str_contains($key, 'artisan card') => ['Artisan Card', 'artisan_card'],
            str_contains($key, 'msy nano') || preg_match('/\bmsy( 2 0)?\b/', $key) === 1 => ['MSY 2.0', 'MSY', 'm_s_y_2_0', 'msy'],
            str_contains($key, 'pmegp') => ['PMEGP', 'p_m_e_g_p', 'pmegp'],
            str_contains($key, 'pmfme') => ['PMFME', 'p_m_f_m_e', 'pmfme'],
            str_contains($key, 'homestay') || str_contains($key, 'grah awas') => ['Deen Dayal Upadhyay Grah Awas Vikas Yojana (DDUGAVY)- Homestay', 'DDU Grah Awas Yojana (Homestay)', 'deen_dayal_upadhyay_grah_awas_vikas_yojana_d_d_u_g_a_v_y_homestay', 'ddu_homestay'],
            str_contains($key, 'veer chandra singh garhwali') => ['Veer Chandra Singh Garhwali Self Employment Scheme', 'Veer Chandra Singh Garhwali Self Empl.', 'veer_chandra_singh_garhwali_self_employment_scheme', 'vcsg'],
            str_contains($key, 'lab testing') || str_contains($key, 'product testing') => ['Lab testing', 'Product Testing', 'lab_testing', 'product_testing'],
            str_contains($key, 'packaging') || str_contains($key, 'labelling') || str_contains($key, 'labeling') || str_contains($key, 'logo design') => ['Other Support Services - Labelling, Packaging, Logo Designing etc.', 'other_support_services_labelling_packaging_logo_designing_etc'],
            str_contains($key, 'market link') || str_contains($key, 'offline connect') => ['Incubatees linked to online/offline Market', 'incubatees_linked_to_online_offline_market', 'Market Link', 'market_link'],
            str_contains($key, 'pitch deck') => ['Pitch Decks', 'Pitch Deck', 'pitch_decks', 'pitch_deck'],
            str_contains($key, 'buyer seller') || preg_match('/\bbsm\b/', $key) === 1 => ['Buyer-Seller Meet (BSM)', 'buyer_seller_meet_b_s_m'],
            str_contains($key, 'eap') || str_contains($key, 'edp') => ['EAP/EDP Sessions', 'e_a_p_e_d_p_sessions'],
            str_contains($key, 'events seminars workshops') => ['Events/ Seminars/ Workshops', 'events_seminars_workshops'],
            str_contains($key, 'mentorship') => ['Specialized Mentorship Support', 'specialized_mentorship_support'],
            str_contains($key, 'other support service') => ['Other Support Services - Labelling, Packaging, Logo Designing etc.', 'other_support_services_labelling_packaging_logo_designing_etc'],
            default => [],
        };

        foreach ($candidates as $candidate) {
            $service = $this->servicesByName()->get($this->normalizeKey($candidate))
                ?? $this->servicesByCode()->get($candidate);
            if ($service) {
                return $service;
            }
        }

        return null;
    }

    /**
     * @return array{label:string,original_label:string,mapped:bool,mapping_source:string,service_id:?int}
     */
    private function result(string $label, string $original, bool $mapped, string $source, ?Service $service): array
    {
        return [
            'label' => $label,
            'original_label' => $original,
            'mapped' => $mapped,
            'mapping_source' => $source,
            'service_id' => $service?->id,
        ];
    }
}
