# Changelog

All notable changes to this project are documented here.
The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project aims to follow [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

> Add every user-facing or notable change under **[Unreleased]** as it lands. On a release, rename the section to a version + date and start a fresh **[Unreleased]**.
> Categories: `Added` · `Changed` · `Deprecated` · `Removed` · `Fixed` · `Security`.

---

## [Unreleased]

### Added
- Project documentation set: `11-day-roadmap.md`, `progress.md`, `backlog.md`, `decisions.md`, `known-issues.md`, `changelog.md`.

### Changed
- Database standardized on **MySQL** for development, testing, and production (previously SQLite for dev/tests). See [ADR-0001](decisions.md).

---

## Baseline — Livewire starter kit (2026-07-09)

### Added
- Laravel 13 · Livewire 4 · Flux UI · Fortify scaffold.
- Authentication: email/password, 2FA (TOTP), email verification, passkeys (WebAuthn).
- Settings pages: profile, appearance, security.
- Infrastructure migrations: users, cache, jobs, passkeys, two-factor.

_No salon domain features implemented yet — see [`backlog.md`](backlog.md) and [`progress.md`](progress.md)._

---

## How to update this file
1. During development, append entries under **[Unreleased]** in the right category.
2. Keep entries concise and user-facing; link to ADRs or issues where useful.
3. On release: change `## [Unreleased]` to `## [X.Y.Z] — YYYY-MM-DD`, then add a new empty **[Unreleased]** block at the top.
