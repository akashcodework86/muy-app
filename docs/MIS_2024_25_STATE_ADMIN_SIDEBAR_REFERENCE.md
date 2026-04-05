# MIS 2024–25 — State Admin sidebar (legacy reference)

Yeh structure **pichle phase (FY 2024–25)** ke state-admin MIS sidebar se liya gaya hai — documentation / gap analysis ke liye.  
Bracket mein numbers (jaise `6106`, `233`) legacy system ke **live counters / badges** the, is codebase mein ab set nahi hain.

---

## Sidebar tree (as provided)

### Dashboards
| Item | Legacy note |
|------|-------------|
| CFA Dashboard | Badge e.g. `6106` (count) |
| Onboard Dashboard | |
| Onboarded (Batchwise) | |
| Onboarding (Districtwise) | |
| Tracker | |
| 24 Deliverables | |
| Marketing Dashboard | |

### Applications & Data
| Item |
|------|
| Applications |
| Products List |
| Repository |
| Weekly Task Manager |
| Plan & Update |
| Task Report |

### People & Access
| Item |
|------|
| Add State Team Staff |
| Team |
| Add Users |

### Targets
| Item |
|------|
| Staff Performance |
| Incubatees Report Card | `NEW` |
| *(section marker)* | `NEW` |

### Events
| Item | Legacy note |
|------|-------------|
| Events List | |
| Add Event | |
| Udyam Mahotsav | |
| Registrations | Badge e.g. `233` |

### Services & Linkages
| Item |
|------|
| Services |
| Target Vs Achi |
| Generate PPT |
| Forward Linkage |
| Market Linkages – Gullak |
| Gullak Dashboard |

---

## Mapping → current `muy-app` (2026 rebuild)

Naya app abhi **deliverable targets + CFA apply + staff/referral + designations** par focused hai. Neeche approximate mapping / gap:

| Legacy sidebar item | Current app (`muy-app`) | Route / notes |
|---------------------|-------------------------|---------------|
| CFA Dashboard | Partial | `route('dashboard')` — state admin dashboard (CFA KPIs/charts) |
| 24 Deliverables / Targets (concept) | Partial | `admin.targets.state`, `admin.targets.district` — MIS deliverable targets |
| Add Users / Team / Add State Team Staff | Partial | `admin.staff.index` — district staff + referral; **state team roster alag module nahi** |
| Applications | Partial | CFA submissions DB + charts on dashboard; **dedicated “Applications list” admin screen nahi** |
| Products List, Repository, Weekly Task Manager, Plan & Update, Task Report | — | **Not built** |
| Onboard Dashboard, Onboarded, Onboarding, Tracker, Marketing Dashboard | — | **Not built** |
| Staff Performance, Incubatees Report Card | — | **Not built** |
| Events List, Add Event, Udyam Mahotsav, Registrations | — | **Not built** |
| Services, Target Vs Achi, Generate PPT, Forward Linkage, Gullak… | — | **Not built** |

**Summary:** Legacy sidebar ~**40+ discrete nav targets**; is phase mein state admin ke paas top bar par **5 MIS links** + dashboard **quick actions** hain. Baaki items future modules ke liye backlog maane ja sakte hain.

---

## Related doc

- [`DASHBOARD_LINKS.md`](./DASHBOARD_LINKS.md) — ab jo UI mein actually linked hai (state / hub / staff).
- [`STATE_ADMIN_NAV_DECISION.md`](./STATE_ADMIN_NAV_DECISION.md) — **kaun se links rakhne / add karne** ka nirnay (dead link avoid).

---

*Reference captured from user-provided 2024–25 MIS sidebar list; not a commitment to implement all items.*
