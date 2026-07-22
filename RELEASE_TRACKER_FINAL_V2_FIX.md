# Release Tracker V2 final fix

## Cause

The project contained an older `fleet_releases` table whose columns did not match the current Release Tracker controller. Earlier repair migrations attempted to inspect or alter that legacy table. On MySQL/XAMPP, an existing request or migration could hold a metadata lock and leave all requests appearing to load indefinitely.

## Final implementation

- The three legacy/transitional migrations are safe no-ops.
- No migration reads, alters, imports from, or drops `fleet_releases`.
- The active model uses `fleet_release_tracker_records`.
- A new forward-only migration creates the complete dedicated table.
- Existing legacy data remains untouched.
- `scripts/recover_local_xampp.sh` stops stale local Laravel processes, restarts XAMPP MySQL, clears caches, and runs migrations.

## Required local command

```bash
chmod +x scripts/recover_local_xampp.sh
./scripts/recover_local_xampp.sh "$(pwd)"
```
