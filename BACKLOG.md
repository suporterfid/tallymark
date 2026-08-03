# Backlog and open questions

## Open questions

- **PR5 / issue #11 — ingest commit destination:** §7.5 requires deleting a buffer only after its aggregates commit, while aggregation tables and services arrive in PR7. The spec defines neither an interim durable event destination nor the schema/purpose of `ingest_stats`, and §5.4 requires an application-side `EventLine` contract without defining its shape. Please choose whether PR5 should (a) retain closed buffers until PR7, (b) introduce a named interim durable event store with retention, or (c) use a specifically-defined `ingest_stats` commit record as the PR5 completion boundary.

The owner chose a UTC-midnight rotation boundary for PR4. A missed rotation writes and retains an `alarm` state for `analytics:maintenance` in `system_heartbeats`, which is the durable operator-facing operational state for the future authenticated health endpoint.

Until PR4 introduces the salts table and rotation, the PR2 site map omits the `salt` key by owner decision; section 7.3 records the sequencing rule.
