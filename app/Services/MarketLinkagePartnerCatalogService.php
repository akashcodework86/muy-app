<?php

namespace App\Services;

use App\Models\MarketLinkagePartnerName;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MarketLinkagePartnerCatalogService
{
    /**
     * Display label overrides keyed by normalized form.
     *
     * @var array<string, string>
     */
    private const CANONICAL_LABELS = [
        'whatsp business' => 'WhatsApp Business',
        'whatsapp business' => 'WhatsApp Business',
        'amazom' => 'Amazon',
        'amazon' => 'Amazon',
        'filipcart' => 'Flipkart',
        'flipkart' => 'Flipkart',
        'air bnb' => 'Airbnb',
        'airbnb' => 'Airbnb',
        'meesho com' => 'Meesho',
        'meesho' => 'Meesho',
        'indiamart' => 'India Mart',
        'india mart' => 'India Mart',
        'just dail' => 'Just Dial',
        'justdail' => 'Just Dial',
        'justdial' => 'Just Dial',
        'just dial' => 'Just Dial',
        'hotelizify' => 'Hotelzify',
        'hotelzfy' => 'Hotelzify',
        'hotelzify' => 'Hotelzify',
        'goibibo business' => 'Goibibo',
        'goibibo com' => 'Goibibo',
        'goibibo' => 'Goibibo',
        'agoda com' => 'Agoda',
        'agoda' => 'Agoda',
        'goggle business' => 'Google Business',
        'google busines' => 'Google Business',
        'google businesse' => 'Google Business',
        'google businesses' => 'Google Business',
        'google bussiness' => 'Google Business',
        'google business' => 'Google Business',
        'google map' => 'Google Business',
        'google search' => 'Google Business',
        'google com' => 'Google Business',
        'google' => 'Google Business',
        'face book page' => 'Facebook',
        'facebook' => 'Facebook',
        'facebook business' => 'Facebook Business',
        'mystore' => 'MyStore',
        'my store' => 'MyStore',
        'ncui haat' => 'NCUI Haat',
        'appied ncui haat' => 'NCUI Haat',
        'applied ncui haat' => 'NCUI Haat',
        'ncui linkage' => 'NCUI Haat',
        'ncui offline stall' => 'NCUI Haat',
        'nitya spices' => 'Nitya Spices',
        'nitya spices offline' => 'Nitya Spices',
        'ondc' => 'ONDC',
        'makemytrip' => 'MakeMyTrip',
        'mmt' => 'MakeMyTrip',
        'trip advisor' => 'TripAdvisor',
        'tripadvisor com' => 'TripAdvisor',
        'udyamwell' => 'Udyam Well',
        'udyam well' => 'Udyam Well',
        'uttarastay' => 'Uttarastays',
        'uttarastays' => 'Uttarastays',
        'uttra stay' => 'Uttarastays',
        'uttra stays' => 'Uttarastays',
        'uttrastay' => 'Uttarastays',
        'uttrastays com' => 'Uttarastays',
        'e nam' => 'e-NAM',
        'euttaranchal com' => 'Euttaranchal',
        'euttranchal' => 'Euttranchal',
        'insta' => 'Instagram',
        'insta business' => 'Instagram',
        'insta sale' => 'Instagram',
        'instagram' => 'Instagram',
        'you tube' => 'YouTube',
        'hilance backry' => 'Hilans Dairy',
        'hilans dairy' => 'Hilans Dairy',
        'hiimsaru' => 'Himsaru',
        'himsaru' => 'Himsaru',
    ];

    /**
     * @var array<string, string>
     */
    private const ALIASES = [
        'whatsp business' => 'whatsapp business',
        'whatsapp business' => 'whatsapp business',
        'amazom' => 'amazon',
        'filipcart' => 'flipkart',
        'mystore' => 'my store',
        'my store ondc' => 'my store',
        'my store and meesho' => 'my store',
        'in process with my store' => 'my store',
        'in process with mystore' => 'my store',
        'in process my store' => 'my store',
        'in process  with my store' => 'my store',
        'face book page' => 'facebook',
        'face book page and meesho my store' => 'facebook',
        'goggle business' => 'google business',
        'google busines' => 'google business',
        'google businesse' => 'google business',
        'google businesses' => 'google business',
        'google bussiness' => 'google business',
        'google' => 'google business',
        'google com' => 'google business',
        'google map' => 'google business',
        'google search' => 'google business',
        'air bnb' => 'airbnb',
        'meesho com' => 'meesho',
        'meesho and mystore' => 'meesho',
        'indiamart' => 'india mart',
        'just dail' => 'just dial',
        'justdail' => 'just dial',
        'justdial bussiness' => 'just dial',
        'justdial com' => 'just dial',
        'hotelizify' => 'hotelzify',
        'hotelzfy' => 'hotelzify',
        'goibibo business' => 'goibibo',
        'goibibo com' => 'goibibo',
        'agoda com' => 'agoda',
        'euttaranchal com' => 'euttaranchal',
        'uttra stay' => 'uttrastays',
        'uttra stays' => 'uttrastays',
        'uttarastay' => 'uttrastays',
        'uttrastay' => 'uttrastays',
        'uttrastays com' => 'uttrastays',
        'udyam well' => 'udyamwell',
        'udhyamwell' => 'udyamwell',
        'ondc udyamwel amazon' => 'ondc',
        'mystore ondc' => 'my store',
        'appied ncui haat' => 'ncui haat',
        'applied ncui haat' => 'ncui haat',
        'ncui linkage' => 'ncui haat',
        'ncui offline stall' => 'ncui haat',
        'insta' => 'instagram',
        'insta business' => 'instagram',
        'insta sale' => 'instagram',
        'you tube' => 'youtube',
        'tripadvisor com' => 'trip advisor',
        'mmt' => 'makemytrip',
        'nitya spices offline' => 'nitya spices',
        'linked with nitya spices' => 'nitya spices',
        'connect with nitya spices and cresta ngo' => 'nitya spices',
        'offline linkage with saras haldwani nitya spices' => 'nitya spices',
        'fresh point distribution company haldwani and supreme mart haldwani store linkage' => 'fresh point distribution company haldwani',
    ];

    /** @var list<string> */
    private const COMPOUND_SUFFIXES = [
        ' store linkage',
        ' store linkages',
        ' market linkage',
        ' offline linkage',
        ' online linkage',
        ' offline connect',
        ' offline partner',
    ];

    /** @var list<string> */
    private const NOISE_KEYS = [
        'in process',
        'offline',
        'local',
        'shop',
        'store',
        'industry',
        'incubatees',
        'website',
        'offline partner',
        'offline connet',
        'offline market linkage',
    ];

    /**
     * @return list<string>
     */
    public function options(): array
    {
        $groups = [];

        foreach ($this->savedCatalogRows() as $row) {
            $this->accumulateGroup($groups, (string) $row['name'], 1);
        }

        foreach ($this->legacyPartnerRows() as $row) {
            $this->accumulateGroup($groups, (string) $row['name'], (int) $row['count']);
        }

        foreach ($this->phase3PartnerRows() as $row) {
            $this->accumulateGroup($groups, (string) $row['name'], (int) $row['count']);
        }

        $labels = [];
        foreach ($groups as $key => $group) {
            if ($key === '' || in_array($key, self::NOISE_KEYS, true)) {
                continue;
            }
            if (str_starts_with($key, 'https ') || str_starts_with($key, 'http ') || strlen($key) > 80) {
                continue;
            }

            $labels[] = self::CANONICAL_LABELS[$key] ?? $this->pickDisplayLabel($group);
        }

        $labels = array_values(array_unique(array_filter(array_map('trim', $labels))));
        natcasesort($labels);

        return array_values($labels);
    }

    /**
     * Normalize a raw partner name for deduplication (case/spelling/compound variants).
     */
    public function normalizePartnerKey(string $name): string
    {
        $key = $this->normalizeKey($name);
        if ($key === '') {
            return '';
        }

        foreach (self::COMPOUND_SUFFIXES as $suffix) {
            if (str_ends_with($key, $suffix)) {
                $key = trim(substr($key, 0, -strlen($suffix)));
            }
        }

        if (str_contains($key, ' and ')) {
            $primary = trim(explode(' and ', $key, 2)[0]);
            if ($primary !== '' && strlen($primary) >= 8) {
                $key = $this->normalizeKey($primary);
            }
        }

        return $key;
    }

    /**
     * @param  iterable<string>  $rawNames
     */
    public function countUniquePartnerKeys(iterable $rawNames): int
    {
        $keys = [];

        foreach ($rawNames as $rawName) {
            $key = $this->normalizePartnerKey((string) $rawName);
            if ($key === '' || in_array($key, self::NOISE_KEYS, true)) {
                continue;
            }
            $keys[$key] = true;
        }

        return count($keys);
    }

    /**
     * Preferred display label for a raw partner name after normalization.
     */
    public function displayLabelFor(string $rawName): string
    {
        $rawName = trim($rawName);
        if ($rawName === '') {
            return '';
        }

        $key = $this->normalizePartnerKey($rawName);

        return self::CANONICAL_LABELS[$key] ?? $rawName;
    }

    /**
     * Persist unique partner display names for future dropdowns.
     *
     * @param  list<string>  $names
     */
    public function registerNames(array $names, ?int $userId = null): void
    {
        if (! Schema::hasTable('market_linkage_partner_names')) {
            return;
        }

        foreach ($names as $rawName) {
            $name = trim((string) $rawName);
            if ($name === '') {
                continue;
            }

            $key = $this->normalizePartnerKey($name);
            if ($key === '' || in_array($key, self::NOISE_KEYS, true)) {
                continue;
            }

            $display = $this->displayLabelFor($name);

            MarketLinkagePartnerName::query()->updateOrCreate(
                ['normalized_key' => $key],
                [
                    'name' => $display,
                    'created_by_user_id' => $userId,
                ],
            );
        }
    }

    /**
     * @return list<array{name: string}>
     */
    private function savedCatalogRows(): array
    {
        if (! Schema::hasTable('market_linkage_partner_names')) {
            return [];
        }

        return MarketLinkagePartnerName::query()
            ->orderBy('name')
            ->get(['name'])
            ->map(fn ($row) => ['name' => (string) $row->name])
            ->all();
    }

    /**
     * @param  array<string, array{variants: array<string, int>, score: int}>  $groups
     */
    private function accumulateGroup(array &$groups, string $rawName, int $count): void
    {
        $rawName = trim($rawName);
        if ($rawName === '') {
            return;
        }

        $key = $this->normalizePartnerKey($rawName);
        if (! isset($groups[$key])) {
            $groups[$key] = ['variants' => [], 'score' => 0];
        }

        $groups[$key]['variants'][$rawName] = ($groups[$key]['variants'][$rawName] ?? 0) + $count;
        $groups[$key]['score'] += $count;
    }

    private function normalizeKey(string $name): string
    {
        $n = strtolower(trim(preg_replace('/\s+/', ' ', $name)));
        $n = rtrim($n, '.');

        if (isset(self::ALIASES[$n])) {
            $n = self::ALIASES[$n];
        }

        $n = preg_replace('/[^a-z0-9]+/', ' ', $n) ?? $n;

        return trim(preg_replace('/\s+/', ' ', $n));
    }

    /**
     * @param  array{variants: array<string, int>, score: int}  $group
     */
    private function pickDisplayLabel(array $group): string
    {
        $best = '';
        $bestCount = -1;

        foreach ($group['variants'] as $variant => $count) {
            if ($count > $bestCount) {
                $bestCount = $count;
                $best = $variant;
            } elseif ($count === $bestCount && strlen($variant) < strlen($best) && ! preg_match('/inr|bill no/i', $variant)) {
                $best = $variant;
            }
        }

        return trim($best);
    }

    /**
     * @return list<array{name: string, count: int}>
     */
    private function legacyPartnerRows(): array
    {
        if (! Schema::connection('legacy')->hasTable('rbi_service_partners')) {
            return [];
        }

        return DB::connection('legacy')
            ->table('rbi_service_partners')
            ->where('category', 'Forward Linkages')
            ->where('status', 'Active')
            ->whereRaw("TRIM(partner_name) <> ''")
            ->selectRaw('TRIM(partner_name) as name, COUNT(*) as count')
            ->groupByRaw('TRIM(partner_name)')
            ->get()
            ->map(fn ($row) => ['name' => (string) $row->name, 'count' => (int) $row->count])
            ->all();
    }

    /**
     * @return list<array{name: string, count: int}>
     */
    private function phase3PartnerRows(): array
    {
        if (! Schema::hasTable('market_linkage_partners')) {
            return [];
        }

        return DB::table('market_linkage_partners')
            ->whereRaw("TRIM(partner_name) <> ''")
            ->selectRaw('TRIM(partner_name) as name, COUNT(*) as count')
            ->groupByRaw('TRIM(partner_name)')
            ->get()
            ->map(fn ($row) => ['name' => (string) $row->name, 'count' => (int) $row->count])
            ->all();
    }
}
