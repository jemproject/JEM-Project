# Security Policy

This policy follows the [Joomla extension security disclosure standard](https://mysites.guru/blog/joomla-extension-security-disclosure-standard/)
(Joomla Manual, approved 2026-08-10). It is organized in the same four
phases as that standard; each item below is numbered to match it 1-20 so
gaps are easy to spot on review.

## Supported Versions

Security fixes are provided for the latest stable JEM release and the
release line currently in active development. Older release lines may
receive fixes at the maintainers' discretion, especially for critical/high
severity issues.

| Version        | Supported          |
| --------------- | ------------------ |
| 5.1.x (current dev) | ✅ |
| 5.0.x           | ✅ |
| 4.5.x and older | ⚠️ Best effort / critical fixes only |

## Scope

This policy covers the JEM component and its bundled modules and plugins in
this repository. Third-party extensions bundled for compatibility (e.g. in
`3rd/`) should be reported to their respective authors, though we're happy
to help coordinate if needed (see #8).

---

## Phase 1 — Before an incident

**#1 Reporting channel.** Report privately to **info@joomlaeventmanager.net**.
Do not use public GitHub issues, the forum, or any other public channel for
undisclosed vulnerabilities. We accept reports in English or Spanish.

Include, where possible:
- Affected component/file(s) and JEM version(s)
- Steps to reproduce, or a proof of concept
- Impact (what an attacker can do) and any prerequisites (authenticated user, specific permission level, server config, etc.)
- Whether the issue is already public or being actively exploited

**#2 Confidentiality until coordinated disclosure.** Until a disclosure date
is agreed (#9), we will not open public GitHub issues about the report,
publish a PoC, discuss the vulnerability in public (forum, chat, socials),
or reference vulnerability details in public commit messages. Commit
messages for an in-progress fix stay generic until the coordinated date.

## Phase 2 — During the report

**#3 Acknowledgement & pace.** See the timeline table below for
acknowledgement and initial-assessment targets. For investigations that run
long, we send status updates rather than going quiet.

**#4 Validate the vulnerability.** Before scoring or fixing, we establish:
affected component, affected versions, which supported Joomla versions are
in play, attack prerequisites, privileges required, whether authentication
is needed, and whether it's practically exploitable (not just theoretical).

**#5 Security bug vs. ordinary bug.** We classify by security impact — SQL
injection, XSS, CSRF, authentication bypass, privilege escalation, path
traversal, insecure deserialization, etc. — not by how severe the
underlying coding mistake looks. A one-line coding error can be a critical
security bug; a large refactor can be security-irrelevant.

**#6 Consistent severity scoring.** We use **CVSS 4.0**, not ad hoc labels
like "critical"/"minor", and document both the base score and the practical
prerequisites behind it (see the advisory template's CVSS field).

**#7 Private fix development.** Fixes are developed on a private branch (a
private repository if needed), with a security test case and a regression
test that fails before the fix and passes after, reviewed by a second
developer before merge.

**#8 Downstream dependencies.** Before disclosure we check whether the
issue also affects: Joomla core itself, other extensions, PHP libraries
(`vendor/`), JS dependencies, external services JEM talks to, or
third-party code bundled for compatibility (`3rd/`). If so, we coordinate
with those maintainers/security@joomla.org before publishing.

## Phase 3 — Public disclosure

**#9 Coordinate the disclosure date.** We agree a reasonable timeline with
the reporter rather than an indefinite embargo (see timeline table).
Vulnerabilities already public or actively exploited are accelerated
substantially regardless of severity.

**#10 CVEs identify the vulnerability, not a release.** One CVE per
vulnerability. We do not mint a separate CVE for each release line the fix
ships in (e.g. one fix backported to both 5.0.1 and 5.1.0 is one CVE).

**#11 Request the CVE before announcing.** We request a CVE ID from
**security@joomla.org** (Joomla's CNA) before the public announcement, so
it's included in the initial advisory — for Medium severity and above.

**#12 Credit the researcher.** We always offer credit and ask exactly how
the reporter wants to be credited (name, handle, or anonymous). We never
publish a name without explicit consent.

**#13 Dedicated advisory, not just a changelog line.** Critical/High issues
get a full advisory (see template below) with product, vulnerability, CVE,
affected versions, fixed versions, severity, CVSS, impact, description,
fix, and credits — not just a changelog entry.

**#14 Tell users what to do.** Every advisory answers "am I affected?" and
"what do I do?" explicitly: affected versions, fixed versions, recommended
action, and any tested mitigation/workaround.

**#15 Explain without maximizing exploitability.** We give enough detail
for informed risk assessment — what the issue is, who can exploit it,
whether auth is required, what an attacker gains, affected/fixed versions —
without publishing a working exploit or the kind of detail that mainly
helps attackers rather than defenders, before the coordinated date.

## Phase 4 — After disclosure

**#16 Notify through channels users actually watch.** Fixes are announced
via: the JEM update mechanism (Joomla's extension update system), the
[joomlaeventmanager.net](https://www.joomlaeventmanager.net) changelog/news
page, the support forum, and a GitHub Security Advisory on this repo. (RSS
and a mailing list are not currently in place — worth revisiting if this
keeps mattering.)

**#17 No vague changelog language.** Never "various bug fixes and
improvements" for a security fix. Every changelog entry — any severity —
uses this format:

```
SECURITY: [SEVERITY] [affected versions] Short description of the fix (commit link)
```

Example:
```
SECURITY: [HIGH] [4.5.0, 5.0.0] Enforce event-level authorization across attendee views and exports (commit b4ca3f75c)
```

**#18 Keep an internal disclosure timeline.** For every security issue we
keep a dated timeline — report received, acknowledged, confirmed, CVE
requested, fixed, released, advisory published — which becomes the
"Timeline" section of the published advisory (see template).

**#19 Handling reports that are already public.** An already-public report
is not automatically treated as hostile or bad-faith. We acknowledge it,
assess it (#4), check if a CVE already exists (#10/#11), coordinate with
the reporter if known, and move to fix and advisory on an accelerated
timeline (#9).

**#20 No retaliation against good-faith researchers.** Responsible security
research is welcome. Good-faith researchers acting under this policy will
not face legal action from the JEM project for their research or reporting.

---

## Timelines

JEM is maintained by volunteers in their spare time. The timelines below
are best-effort targets, not contractual SLAs — but we do commit to keeping
reporters updated even if a fix takes longer than planned.

| Severity (CVSS 4.0) | Acknowledgement | Initial assessment | Fix target | Release |
| --- | --- | --- | --- | --- |
| **Critical** (9.0–10.0) | 2 business days | 5 days | ≤ 14 days | Out-of-cycle patch release |
| **High** (7.0–8.9) | 2 business days | 5–7 days | ≤ 30 days | Out-of-cycle or next scheduled release, whichever is sooner |
| **Medium** (4.0–6.9) | 3 business days | 7 days | Next scheduled release | ≤ 60 days |
| **Low** (0.1–3.9) | 3 business days | Best effort | Next scheduled release | No fixed SLA |

## Advisory Template

Used for Critical/High issues (#13); Medium/Low get the changelog line
(#17) unless the reporter or maintainers judge a fuller writeup warranted.

```markdown
## [SEVERITY] Short title

- **ID:** JEM-YYYY-NNN (+ CVE-YYYY-NNNNN once assigned, #11)
- **Date published:** YYYY-MM-DD
- **Affected versions:** x.y.z – x.y.z
- **Fixed in:** x.y.z
- **CVSS 4.0:** score (vector string) — #6
- **Reported by:** name / handle / anonymous — per reporter's wishes, #12

### Description
What the vulnerability is and where it lives (component, endpoint, class).

### Impact
What an attacker can actually do, and any prerequisites (auth level, config, etc.). — #14

### Mitigation
Update to the fixed version. If a temporary workaround exists (e.g. disable a
feature, adjust a permission), state it here. — #14

### Timeline
- YYYY-MM-DD — Report received
- YYYY-MM-DD — Acknowledged
- YYYY-MM-DD — Confirmed / CVE requested
- YYYY-MM-DD — Fix developed
- YYYY-MM-DD — Patched release published
- YYYY-MM-DD — Advisory published
```
