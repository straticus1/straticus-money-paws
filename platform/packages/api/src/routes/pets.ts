import type { FastifyInstance } from 'fastify';
import { and, eq, gte, sql } from 'drizzle-orm';
import { type Db, pets, inventory, storeItems } from '@paws/db';
import { z } from 'zod';

const SPECIES = ['dog', 'cat', 'bird', 'rabbit', 'horse'] as const;

const createPetBody = z.object({
  name: z.string().trim().min(1).max(50),
  species: z.enum(SPECIES),
});

const idParams = z.object({
  id: z.string().uuid(),
});

const feedBody = z.object({
  itemId: z.string().uuid(),
});

interface PetEffect {
  hungerRestore?: number;
  happinessBoost?: number;
}

function publicPet(pet: typeof pets.$inferSelect) {
  return {
    id: pet.id,
    name: pet.name,
    species: pet.species,
    hunger: pet.hunger,
    happiness: pet.happiness,
    health: pet.health,
    alive: pet.alive,
    createdAt: pet.createdAt,
  };
}

export function registerPetRoutes(app: FastifyInstance, db: Db): void {
  app.get('/pets', async (request, reply) => {
    const rows = await db.select().from(pets).where(eq(pets.userId, request.user!.id));
    return reply.send({ pets: rows.map(publicPet) });
  });

  app.post('/pets', async (request, reply) => {
    const parsed = createPetBody.safeParse(request.body);
    if (!parsed.success) {
      return reply.code(400).send({ error: 'invalid_request' });
    }
    const inserted = await db
      .insert(pets)
      .values({
        userId: request.user!.id,
        name: parsed.data.name,
        species: parsed.data.species,
      })
      .returning();
    return reply.code(201).send({ pet: publicPet(inserted[0]!) });
  });

  app.get('/pets/:id', async (request, reply) => {
    const parsed = idParams.safeParse(request.params);
    if (!parsed.success) {
      return reply.code(400).send({ error: 'invalid_request' });
    }
    const rows = await db
      .select()
      .from(pets)
      .where(and(eq(pets.id, parsed.data.id), eq(pets.userId, request.user!.id)));
    const pet = rows[0];
    if (!pet) {
      return reply.code(404).send({ error: 'not_found' });
    }
    return reply.send({ pet: publicPet(pet) });
  });

  app.post('/pets/:id/feed', async (request, reply) => {
    const params = idParams.safeParse(request.params);
    const body = feedBody.safeParse(request.body);
    if (!params.success || !body.success) {
      return reply.code(400).send({ error: 'invalid_request' });
    }
    const userId = request.user!.id;
    const petId = params.data.id;
    const itemId = body.data.itemId;

    const result = await db.transaction(async (tx) => {
      const petRows = await tx
        .select()
        .from(pets)
        .where(and(eq(pets.id, petId), eq(pets.userId, userId)));
      const pet = petRows[0];
      if (!pet) {
        return { status: 404 as const, error: 'not_found' };
      }
      if (!pet.alive) {
        return { status: 400 as const, error: 'pet_dead' };
      }

      const itemRows = await tx.select().from(storeItems).where(eq(storeItems.id, itemId));
      const item = itemRows[0];
      if (!item) {
        return { status: 404 as const, error: 'item_not_found' };
      }
      if (item.category !== 'food' && item.category !== 'treat') {
        return { status: 400 as const, error: 'item_not_edible' };
      }

      // Decrement one unit, guarded by quantity>=1 so we never underflow the
      // CHECK constraint. No row updated => the user does not own the item.
      const consumed = await tx
        .update(inventory)
        .set({ quantity: sql`${inventory.quantity} - 1` })
        .where(
          and(
            eq(inventory.userId, userId),
            eq(inventory.itemId, itemId),
            gte(inventory.quantity, 1),
          ),
        )
        .returning({ id: inventory.id });
      if (consumed.length === 0) {
        return { status: 400 as const, error: 'item_not_owned' };
      }

      const effect = (item.effect ?? {}) as PetEffect;
      const hunger = Math.min(100, pet.hunger + (effect.hungerRestore ?? 0));
      const happiness = Math.min(100, pet.happiness + (effect.happinessBoost ?? 0));

      const updated = await tx
        .update(pets)
        .set({ hunger, happiness })
        .where(eq(pets.id, petId))
        .returning();
      return { status: 200 as const, pet: updated[0]! };
    });

    if (result.status !== 200) {
      return reply.code(result.status).send({ error: result.error });
    }
    return reply.send({ pet: publicPet(result.pet) });
  });
}
