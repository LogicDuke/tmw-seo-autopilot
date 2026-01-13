# Slot machine diagnostics

This repository powers the TMW SEO Autopilot plugin. It does **not** register or render the `tmw_slot_machine` shortcode, but it is often deployed alongside the slot machine plugin. The log snippet below indicates the slot machine runtime is active while the per-post slot settings are unset:

```
_tmw_slot_enabled = *** EMPTY ***
_tmw_slot_mode = *** EMPTY ***
_tmw_slot_shortcode = *** EMPTY ***
```

## What the log means

- The slot machine plugin is loaded (`shortcode_exists=YES`).
- The post-level meta keys are empty, so the slot machine has no per-post configuration to act on.

## What to check next

1. Confirm the slot meta values are set on the affected model post. If the slot machine supports default options, ensure they are enabled.
2. If the slot machine expects a default shortcode when meta is missing, add that default via the slot plugin settings or by saving the post to regenerate meta.
3. Keep the existing diagnostic tags (`[TMW-*]`) in the slot plugin if you extend logging so that log filtering stays consistent.

> Note: Any actual slot machine fixes must be implemented in the slot machine plugin/theme codebase, not in this SEO Autopilot plugin.
