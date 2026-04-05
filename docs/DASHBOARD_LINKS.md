# Dashboard navigation — MIS phase (reference)

Yeh table **`resources/views`** ke hisaab se hai: kaun se role par kahan kaun se links dikhte hain.  
**Source:** `partials/admin-topbar.blade.php`, `dashboards/state-admin.blade.php`, `dashboards/hub-admin.blade.php`, `dashboards/staff.blade.php`.

**Legacy FY 2024–25 state-admin sidebar (poori list + gap mapping):** [`MIS_2024_25_STATE_ADMIN_SIDEBAR_REFERENCE.md`](./MIS_2024_25_STATE_ADMIN_SIDEBAR_REFERENCE.md)

---

## Summary by role

| Role (`users.role`) | Top navigation (MIS admin links) | Dashboard body links |
|---------------------|----------------------------------|----------------------|
| `state_admin` | Haan — **6** MIS links + brand + logout | Haan — Quick actions (6) + CFA banner mein 1 link |
| `hub_admin` | Nahi — sirf brand → dashboard + user + logout | Nahi — sirf text note (state admin se contact) |
| `district_staff` | Nahi — sirf brand + user + logout | Nahi — card mein duplicate logout |

---

## Detailed table (har link alag row)

| # | Dashboard / view | Role | Location | Label (UI) | Route name / URL | Notes |
|---|------------------|------|----------|------------|------------------|--------|
| 1 | Shared topbar | `state_admin` | Header — brand | App name + “State admin” | `route('dashboard')` | Logo pe click = home dashboard |
| 2 | Shared topbar | `state_admin` | Header — nav | Dashboard | `route('dashboard')` | Active jab route name `dashboard` |
| 2b | Shared topbar | `state_admin` | Header — nav | CFA applications | `route('admin.cfa.index')` | Paginated `cfa_submissions` list |
| 3 | Shared topbar | `state_admin` | Header — nav | State targets | `route('admin.targets.state')` | MIS state-level targets |
| 4 | Shared topbar | `state_admin` | Header — nav | District targets | `route('admin.targets.district')` | MIS district split |
| 5 | Shared topbar | `state_admin` | Header — nav | Staff | `route('admin.staff.index')` | Staff CRUD, referral, CFA monthly targets |
| 6 | Shared topbar | `state_admin` | Header — nav | Designations | `route('admin.designations.index')` | Designation CRUD |
| 7 | Shared topbar | `state_admin` | Header — right | Log out | `POST route('logout')` | Form button |
| 8 | Shared topbar | `hub_admin` | Header — brand | App name + “Hub admin” | `route('dashboard')` | Nav links **nahi** |
| 9 | Shared topbar | `hub_admin` | Header — right | Log out | `POST route('logout')` | |
| 10 | Shared topbar | `district_staff` | Header — brand | App name + “District staff” | `route('dashboard')` | Nav links **nahi** |
| 11 | Shared topbar | `district_staff` | Header — right | Log out | `POST route('logout')` | |
| 12 | `state-admin.blade.php` | `state_admin` | CFA target banner | “District targets” (inline) | `route('admin.targets.district', ['fiscal_year_id' => …, 'deliverable_id' => …])` | Sirf jab active FY + CFA deliverable maujood ho |
| 12b | `state-admin.blade.php` | `state_admin` | Quick actions | CFA applications | `route('admin.cfa.index')` | |
| 13 | `state-admin.blade.php` | `state_admin` | Quick actions | State targets | `route('admin.targets.state')` | |
| 14 | `state-admin.blade.php` | `state_admin` | Quick actions | District targets | `route('admin.targets.district')` | |
| 15 | `state-admin.blade.php` | `state_admin` | Quick actions | Staff & links | `route('admin.staff.index')` | |
| 16 | `state-admin.blade.php` | `state_admin` | Quick actions | Designations | `route('admin.designations.index')` | |
| 17 | `state-admin.blade.php` | `state_admin` | Quick actions | API validation | `url('/status/targets')` | Naya tab (`target="_blank"`) — JSON validation |
| 18 | `hub-admin.blade.php` | `hub_admin` | Page bottom | — | *(koi `<a href>` nahi)* | Sirf text: state admin se contact |
| 19 | `staff.blade.php` | `district_staff` | Info card | Log out | `POST route('logout')` | Topbar mein bhi logout |

---

## Implementation note

- Topbar logic: `partials/admin-topbar.blade.php` — `$showAdminNav` sirf `state_admin` ke liye `true`.
- `hub_admin` / `district_staff` **admin** routes (`/admin/*`) UI se link nahi karte; seedha URL se jane par `state_admin` middleware **403** de sakta hai.

---

*Last updated from codebase review (MIS dashboards phase).*
