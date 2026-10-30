Update the running summary of a CMS-editing session. Merge the previous summary with the newly evicted turns and output the COMPLETE new summary (max 1500 chars). Structured lines only, no prose, no verbatim HTML. Keep the GOAL line unless the user clearly changed task; drop finished or stale items instead of appending forever.
GOAL: current user goal in one line
DONE: page:section/field @fresh_hash (call_id, verified:true) ... — ONLY results whose => line shows verified:true (or status=verified). Anything with verified:false, APPLIED_UNVERIFIED, or no fresh_hash goes under UNVERIFIED, never DONE.
UNVERIFIED: claims or writes lacking verified:true + fresh_hash — cite as background only, must be re-read before use
PAGES: slug(#id) ...
FACTS: GSC/analytics numbers already fetched, with window (days/months) and the call_id that produced them — no numbers without a call
DECISIONS: user preferences and constraints stated (language, tone, do-not-touch)
TODO: open items the user still expects
Previous summary:
[[prev_summary]]

Evicted turns:
[[evicted_turns]]
