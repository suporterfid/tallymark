# Backlog and open questions

## Open questions

- **PR4 / issue #9 — stale-salt alarm:** §8.1 requires a stale salt to raise an operator alarm, but does not specify the durable destination or operator surface for that alarm. `system_heartbeats` is listed only as a future table and the dashboard/health endpoint arrives later, so choosing a table, log, or notification channel would invent a design.
- **PR4 / issue #9 — daily rotation boundary:** §8.1 requires rotation every 24 hours and a one-hour default grace overlap, but does not define whether the active salt changes at a calendar boundary or 24 hours after creation. This changes the map's active salt and the rotation-boundary tests.

Until PR4 introduces the salts table and rotation, the PR2 site map omits the `salt` key by owner decision; section 7.3 records the sequencing rule.
