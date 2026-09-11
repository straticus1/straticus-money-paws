# Next-generation paws.money

Status: proposed product and implementation contract, 2026-09-11. This document
does not represent shipped functionality. User requirements: a next-generation
pet game, monetization, backend-controlled anti-cheat, frontend anti-cheat, and
anti-theft systems. Presentation and monetization choices below are working
recommendations pending product feedback.

Implemented locally so far: [the interactive home](PET-HOME.md) and
[companion identity, bond, daily adventures and memories](COMPANION-MEMORIES.md).
These playable slices do not complete the full monetization/security roadmap.
Deployment is deferred while infrastructure is being provisioned.

## Product direction

Build a polished, responsive 2D pet world around lasting attachment to individual
pets. Keep the existing six puzzle games as activities inside that world.
The everyday loop is care → play → bond → personalize → discover.

- An interactive home: pets walk, sleep, eat, react to touch, and use toys.
  Players place owned furniture on a grid. Keyboard controls and a semantic
  interface must offer equivalents to pointer and canvas interactions.
- Persistent identity: appearance, personality, expressions, bond progression,
  favorite activities, and a journal of shared milestones.
- AI portraits: controlled art direction, persistent character references, and
  player-selected traits. Portrait generation is separate from animation;
  reusable rigs or sprite sets provide reliable gameplay animation.
- Breeding returns after identity and ownership are established: compatible
  parents, explicit owner consent, server-created inherited traits, lineage,
  cooldowns, and one offspring per completed breeding job. No paid random
  rarity rolls in the proposed economy.
- Seasonal quests and cooperative goals follow the single-player home. Public
  chat, player trading, and a shared real-time world are later scope requiring
  their own moderation, concurrency, and abuse controls.

## Architecture

Retain Fastify, PostgreSQL/Drizzle, the existing ledger, auth, and game rules.
Use Preact for account, inventory, shop, and accessible controls. Evaluate Phaser
for the 2D home with a small integration prototype before pinning a version.
Do not rewrite all six games to introduce the home renderer.

The renderer displays state and sends intents. It cannot grant ownership,
currency, experience, breeding outcomes, or purchases. Cosmetic motion may run
locally; the server validates every persistent interaction. A full physics
server is unnecessary for the initial private home.

New backend responsibilities:

| Module | Responsibility |
|---|---|
| Pet state | Versioned appearance, traits, bond, care events, and lineage |
| Home | Owner-scoped rooms, valid furniture placement, owned-item checks |
| Action processing | Strict commands, state versions, replay handling, server time |
| Abuse controls | Shared limits, authoritative event analysis, review decisions |
| Commerce | Catalog, orders, payment events, entitlements, refunds, reconciliation |
| Media workers | Generation jobs, budgets, validated outputs, private originals |
| Operations | Recovery, asset history, moderation, incident controls, audit trail |

Use a durable job queue for media and payment reconciliation, with bounded
retries and a dead-letter review path. Keep economic writes inside database
transactions. Add Redis or an equivalent shared service for distributed limits;
do not rely on a process-local counter once multiple API instances run.

## Monetization

Proposed launch model: free core play, directly purchased cosmetic collections,
optional membership, and bounded AI portrait generation credits.

- Cosmetics: outfits, furniture, house themes, and visual effects with a clear
  preview and fixed price. Owned cosmetics have no effect on ranked rewards.
- Membership: a defined recurring collection of cosmetic benefits and generation
  credits, with clear renewal, cancellation, and entitlement expiry behavior.
- AI credits: sold only once actual generation cost, retries, storage, and support
  costs are measured. Failed jobs restore reservations exactly once. Show the
  generation price before submitting a job.
- Keep earned PAWS separate from paid entitlements and generation credits.
  Preserve the existing 125 PAWS daily cap and non-convertible currency model.
- Existing wallet records remain intact. New purchases should use hosted
  checkout and server-verified fulfillment; provider selection is an open choice.
  Do not treat the existing crypto withdrawal system as the new shop economy.

Backend selects the catalog price and beneficiary from authenticated records.
Create an order before checkout; persist the provider reference. Verify webhook
signatures, provider account/environment, amount, currency, order association,
and paid state. Deduplicate events and enforce a unique order fulfillment key.
Browser redirects never unlock purchases. Handle duplicate and out-of-order
events, reconciliation after downtime, refunds, disputes, and subscription changes.
Grant or revoke entitlements through audited transitions. Corrections to financial
records use compensating ledger postings, never history edits.

No prices or revenue projections are approved by this document. Track purchase
conversion, retention, net revenue after refunds, and per-user generation cost
before expanding paid features.

## Backend anti-cheat: required

1. Every action requires authentication, ownership, a strict command schema,
   a unique action ID, and the expected state version. Server time determines
   cooldowns and progression. No client-provided result or elapsed time is trusted.
2. Persist a payload hash with the action key. An exact retry returns its stored
   response; reuse with different content fails. Lock or compare-and-swap state
   so concurrent requests cannot duplicate inventory, offspring, or rewards.
3. Keep hidden answers and unrevealed state private. Send only the state needed
   for the current view. For a future shared world, use visibility-based delivery.
4. Calculate rules, randomness, score, ownership, and rewards on the server.
   Store rule versions and sufficient validated events to explain disputed results.
5. Enforce shared limits by account, session, route, and coarse network signals.
   Add separate signup, generation, reward, and checkout budgets. Network identity
   is supporting evidence, not proof that accounts belong to one person.
6. Detect suspicious legal play using server-observed cadence, repeated paths,
   impossible action sequences, and aggregate reward behavior. Fast or optimal
   play alone must not trigger a ban. Browser telemetry is forgeable evidence.
7. Run detection in observation mode first. Separate ranked eligibility from
   casual access. Escalate proportionately through limits, accessible challenges,
   reward/rank review, and evidenced sanctions with an appeal path.
8. Fail closed for reward settlement and paid grants when required verification
   is unavailable. Preserve safe read access and unrewarded play where feasible.

Bots can still solve public puzzles. These controls bound and detect abuse; they
cannot prove that every legal action came from a human. Per-account caps alone
do not address account farms.

## Frontend anti-cheat and anti-theft: required

The browser is attacker-controlled. Frontend controls supplement backend
enforcement; removing every client check must never grant an advantage.

| Protection | Implementation target | Limit |
|---|---|---|
| Session theft | HttpOnly/Secure cookies, exact-origin mutations, session revocation, passkeys and step-up flows | Extensions or compromised devices may act as the user |
| Script injection | Strict CSP, safe rendering, controlled dependencies, no secrets in bundles | Same-origin compromise remains a threat |
| Action tampering | Typed commands, pending-action controls, state reconciliation, optional anomaly telemetry | Direct API clients can bypass UI checks |
| Build integrity | Reproducible builds, dependency review, private source maps, immutable versioned assets | Bundle hashes do not attest a trustworthy browser |
| Paid asset access | Entitlement checks before delivery, short-lived URLs, separate originals and display derivatives | Authorized viewers can save pixels or network responses |
| Artwork misuse | Provenance records, optional watermarks on shared exports, reporting and takedown workflow | Watermarks deter copying; they do not prevent it |

Do not use blocked right-click, DevTools traps, or obfuscation as a security gate.
Avoid invasive browser fingerprinting. Any challenge must have an accessible
fallback; missing telemetry is not sufficient evidence for punishment.

## Account, inventory, and artwork theft

- Add passkey enrollment and recovery with step-up authentication, one-time
  challenges, origin/RP validation, and protection against credential replacement.
  Recovery must not silently bypass stronger account protections.
- Provide session listing and revocation. Audit sensitive account changes and
  notify the account owner. Apply extra checks to recovery and future transfers.
- Check resource ownership on every pet, room, item, media, and purchase request.
  UUIDs and private frontend routes are not access control.
- Record item provenance and entitlement history. Keep trading disabled until
  atomic transfers, explicit confirmations, recovery holds, fraud review, and
  dispute procedures exist. Payment refund behavior must be defined before launch.
- Keep original generated media in private storage. Authorize generation and
  downloads, validate decoded media type/size, strip unnecessary metadata, and
  serve safe derivatives. Never fetch arbitrary user URLs from a privileged worker.
- Record generation inputs, model/job version, resulting asset hash, and owning
  account. Treat uploads and prompts as untrusted input. Moderate generated and
  publicly shared content, with reporting and review appropriate to the audience.

## Release sequence and acceptance gates

| Phase | Deliverable | Required evidence |
|---|---|---|
| 1: Protected foundation | Shared limiter, versioned action receipts, abuse events, ownership audit, recovery design | Cross-account requests denied; conflicting retries rejected; concurrent action grants exactly once; limiter works across two API instances |
| 2: Playable home | One animated pet, feeding/toy reactions, one editable room, existing game entrances | Touch/keyboard equivalents; persistence after reconnect; stale clients reconcile; placement cannot use unowned items; mobile performance measured |
| 3: Identity and AI | Persistent portrait and traits, durable generation jobs, quotas, private media | Failure/cancellation restores credit once; worker retries cannot double bill; cross-account asset requests denied; cost caps survive concurrency |
| 4: Monetization | One cosmetic collection, hosted checkout, entitlements, operator reconciliation | Forged/replayed/out-of-order webhooks tested; redirect alone grants nothing; refund and provider outage exercised end to end |
| 5: Breeding and depth | Inheritance, consent, lineage, cooldowns, quests | Duplicate/concurrent jobs create one offspring; parents belong to consenting owners; traits derive from stored server rules |
| 6: Community expansion | Cooperative activities and seasonal content | Abuse observation results reviewed; recovery/appeals operational; moderation and load tests completed before public social features |

All phases preserve existing financial invariants and game behavior. Use additive
migrations and feature flags. Runtime rollback may disable a feature but cannot
delete purchases, pets, or economic history. Test restores and reconciliation
before accepting payments. Release telemetry must omit secrets and minimize
personal data; establish retention periods before collecting abuse signals.

First implementation slice: one protected home interaction from authenticated
command through persisted event and accessible UI. Complete that loop before
expanding animation content or integrating paid checkout.

## Open product choices

- 2D versus 3D presentation; recommendation is 2D for the existing web stack.
- Audience and launch territories, which shape community and purchase flows.
- Monetization provider, pricing, membership benefits, and generation budgets.
- Whether artwork protection includes public watermarked exports.
- Initial art style, supported devices, and measurable frame-time/load targets.

## Evidence and references

Existing implementation and limitations: [platform overview](../README.md),
[game security](GAME-SECURITY.md), [platform security](SECURITY-HARDENING.md).
The repository graph was consulted but reported an older commit; current source
and documentation were used to assess the active application.

- [Phaser documentation](https://docs.phaser.io/): candidate 2D rendering framework.
- [OWASP authorization guidance](https://cheatsheetseries.owasp.org/cheatsheets/Authorization_Cheat_Sheet.html): enforce permissions for every request on the server.
- [OWASP transaction authorization](https://cheatsheetseries.owasp.org/cheatsheets/Transaction_Authorization_Cheat_Sheet.html): server-controlled transaction verification.
- [Stripe webhook documentation](https://docs.stripe.com/webhooks): duplicate-event handling and verified asynchronous payment events; cited as an implementation reference, not a provider commitment.
