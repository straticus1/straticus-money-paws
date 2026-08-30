# Game security model

Paw Match is a free-to-play memory game with rewards in the internal PAWS
currency. It does not accept USD or cryptocurrency wagers.

## Trust boundary

The browser is untrusted. It may choose only a card position and a UUID action
key. The API owns and validates the board, revealed state, move count, outcome,
reward, daily cap, and ledger posting. The full board is stored in PostgreSQL
and is never returned by an API response.

```text
Untrusted browser -> authenticated move API -> row-locked game session
                                             -> capped reward calculation
                                             -> double-entry PAWS ledger
```

## Threats and mitigations

| Threat | Mitigation |
|---|---|
| Editing state or rewards in DevTools | Strict request schema rejects extra outcome, currency, and reward fields |
| Reading the board from JavaScript | Board exists only in the server-side session row; responses reveal legal cards only |
| Predicting a new board | Fisher-Yates shuffle uses Node's OS-backed `crypto.randomInt` |
| Replaying a successful move | UUID action keys store and return the exact first response |
| Double reward settlement | Ledger idempotency key is derived from the server-created game session ID |
| Concurrent moves or cap bypass | User and session rows are locked in a consistent order inside one DB transaction |
| Playing another user's session | Every session lookup is scoped by authenticated user ID |
| Automated reward farming | All six games share one row-locked 125 PAWS budget per user per UTC day; later games remain playable for zero reward |
| Overdraft or unbalanced posting | Existing ledger row locks, overdraft checks, and deferred balance trigger apply |

## Residual risks

Browser automation can still play legal moves faster than a person. The daily
cap bounds its economic impact but is not bot detection. PAWS must remain an
internal, non-convertible game currency unless legal review and a substantially
stronger anti-abuse system are completed. Real-money chance games from the PHP
prototype are intentionally not ported.

Trail Tails follows the same trust boundary with purpose-built session and
action tables. Its private terrain and objective locations stay in PostgreSQL;
the browser submits only a strict action union plus a UUID idempotency key. A
server-side route proof rejects any generated map that cannot achieve the
three-star objective within the starting energy budget.

Midnight Pantry stores its answer separately from the public clues and verifies
that every published puzzle has exactly one solution. The browser may place or
remove one known ingredient, serve one guest, or abandon a session; strict
schemas reject answer, score, reward, currency, and pet-state fields. Serving is
checked against the private server answer inside a row-locked transaction.
Incorrect attempts return generic feedback instead of revealing which part of
the bowl was right. Daily journal credit, bond XP, stars, and the idempotent PAWS
ledger posting are all derived from persisted session state by the API.

Lantern Lines, Pocket Post, and Parade Practice deliberately expose their full
puzzle boards because visible planning is the game. Their APIs still own every
orientation, position, simulation, counter, grade, and reward. A modified client
may calculate a solution, but it cannot persist illegal state or supply a score.
This is bounded by strict schemas, owner-scoped row locks, exact action replay,
one-time progress, idempotent ledger keys, and the same shared daily cap.
