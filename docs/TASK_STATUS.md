# Phase 3 / MUY — task tracker

| # | Task | Status | Notes |
|---|------|--------|--------|
| 1 | Laravel में hub batch (onboarding batches, CDO PDF, hub/state flows) | Done | `HubBatchController`, compliance, migrations |
| 2 | State → District → Staff M1–M12 targets (सभी deliverables) | Done | `StaffDeliverableMonthlyTargetController`, partial save |
| 3 | District targets partial save (state total से पहले भी save) | Done | `TargetController` |
| 4 | Staff CFA / सभी MIS monthly partial save | Done | `StaffDeliverableMonthlyTargetController` |
| 5 | Live deploy `ukrbi.in/phase3` | Done | तुम्हारे हिसाब से live |
| 6 | Route `deliverable_code` (500 / binding fix shared host) | Done | `web.php` + controller |
| 7 | FY **2024-25** + **2026-27** तारीखें DB में (2 Apr → 1 Apr अगला साल) | Done | migration + `FiscalYearSeeder` |
| 8 | Legacy DB connection (`rbiphase2`) `.env` से | Done | `config/database.php` → `legacy` |
| 9 | `monthly_activity_targets` → `staff_monthly_targets` import command | Done | `import:legacy-monthly-targets` |
| 10 | Legacy `activity_type` → `deliverables.code` मैप | In progress | `config/legacy_phase2.php` — नए type मिलें तो जोड़ते रहो |
| 11 | CFA: `rbi_applications` (+ details) → `cfa_submissions` import | Pending | अगला बड़ा कदम; `fiscal_year_id` तारीख से |
| 12 | Workshops / `block_workshop_entries` → achievement या legacy archive | Pending | डिज़ाइन: गिनती vs अलग टेबल |
| 13 | `manual_activity_counts` state MIS → Laravel / report | Pending | optional archive table |
| 14 | Multi-FY “overall achievement” report (कई FY चुनकर total) | Pending | Admin UI + queries |
| 15 | FY **2025-26** row अगर उस साल का legacy डेटा चाहिए | Pending | अभी सिर्फ 24-25 + 26-27 fix |
| 16 | `public/` से SQL dump हटाना / डाउनलोड ब्लॉक | Pending | सुरक्षा |
| 17 | Staging पर import `--dry-run` फिर real run | Pending | हमेशा पहले dry-run |

**Legend:** Done = implemented / merged in repo · Pending = not done · In progress = चल रहा या config बढ़ता रहता है।

**Commands (याद रखने लायक):**
```bash
php artisan migrate --force
php artisan import:legacy-monthly-targets --dry-run
php artisan import:legacy-monthly-targets
```

Manager / team को यही फाइल share कर सकते हो; जब कोई task पूरा हो तो **Status** कॉलम अपडेट करते रहो।
