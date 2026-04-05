# State admin — kaun se links (nirnay)

## Principle
- **Sirf wahi nav link dalo** jiska **asli page / route** app mein ho.  
- Legacy 2024–25 sidebar ke **30+ items** ko ab topbar mein copy mat karo — zyada tar modules abhi **build nahi**, dead links UX kharab karenge.

## Workflow (agreed)
- Baaki pages **jab implement ho jayein**, tab unke liye **top bar mein link add karna** — pehle route + view ready, phir `partials/admin-topbar.blade.php` mein entry + `$activeNav` rule.

---

## Tier 1 — Ab topbar par (core MIS, already)

| Link | Route | Kyon |
|------|--------|------|
| Dashboard | `dashboard` | Overview + charts |
| State targets | `admin.targets.state` | State-level deliverable totals |
| District targets | `admin.targets.district` | District split |
| Staff | `admin.staff.index` | District staff, referral, CFA monthly targets |
| Designations | `admin.designations.index` | Staff titles |

---

## Tier 2 — Add kiya gaya (legacy “Applications” se match)

| Link | Route | Kyon |
|------|--------|------|
| **CFA applications** | `admin.cfa.index` | `cfa_submissions` ki list — legacy *Applications & Data → Applications* / *CFA Dashboard* ka practical hissa |

---

## Tier 3 — Abhi nav mein mat dalo (backlog / dev-only)

| Legacy-style item | Kyon nahi |
|-------------------|-----------|
| Onboard / Marketing / Tracker / 24 tiles alag | Alag product modules; routes nahi |
| Products, Repository, Weekly tasks… | Not built |
| Team / Add state team alag se | Abhi `Staff` ke andar district staff focus |
| Events, Udyam, Gullak… | Not built |
| `/status`, `/status/catalog`, `/status/targets` | **JSON / ops checks** — state admin MIS user ke liye topbar clutter; **state dashboard → Quick actions** mein “API validation” already hai |

Jab naya module aaye tab uska **ek clear route + screen** banao, phir nav mein ek link add karo.

---

## Summary (ek line)

**6** top-level MIS links: pehle wale **5** + **CFA applications**; baaki legacy items **docs + backlog** tak, jab tak implement na ho.

---

*See also: [`MIS_2024_25_STATE_ADMIN_SIDEBAR_REFERENCE.md`](./MIS_2024_25_STATE_ADMIN_SIDEBAR_REFERENCE.md)*
