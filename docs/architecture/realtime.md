# Near-real-time reporting

The dashboard can lag real time by up to about two minutes: the collector closes a one-minute buffer before ingest, and the following cron run aggregates it. This is intentional; the collector never writes to the database synchronously.

The “Last 30 minutes” screen reads five-minute UTC aggregates and renders them in the site's configured timezone. To avoid silently dropping events in a partially overlapping bucket, the window includes every bucket that overlaps the trailing 30 minutes. It can therefore cover up to 35 minutes and is labelled accordingly in the UI.

Five-minute rows are operational data and are pruned after 48 hours. Long-term reporting uses the hourly and daily aggregates.
