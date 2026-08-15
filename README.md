# paws.money

The active application is the TypeScript v5 platform in [`platform/`](platform/).
It is a pnpm monorepo with a Fastify API, Preact web client, PostgreSQL/Drizzle
database layer, authentication, double-entry ledger, and Coinbase payment
integration.

## Active development

```sh
cd platform
pnpm install
pnpm -r typecheck
pnpm -r test
pnpm -r build
```

See [`platform/README.md`](platform/README.md) for setup, architecture,
deployment status, and the financial invariants that all changes must preserve.

## Legacy application

The original PHP application and all of its supporting assets are archived in
[`legacy/`](legacy/). It is retained as a feature reference while functionality
is migrated to v5. New product work should not add dependencies on the legacy
application.
