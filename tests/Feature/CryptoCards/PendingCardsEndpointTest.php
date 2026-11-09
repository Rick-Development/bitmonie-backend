<?php

namespace Tests\Feature\CryptoCards;

class PendingCardsEndpointTest extends CryptoCardTestCase
{
    public function test_requires_authentication(): void
    {
        $this->getJson('/api/user/crypto-cards/pending')->assertStatus(401);
    }

    public function test_lists_only_in_flight_creations_for_the_current_user(): void
    {
        $user = $this->makeUser();
        $other = $this->makeUser();

        [$queued] = $this->makePendingCard($user, 5.0, 'pending');
        [$converting] = $this->makePendingCard($user, 5.0, 'processing');
        [$creating] = $this->makePendingCard($user, 5.0, 'processing', ['quidax' => ['settled_at' => now()->toIso8601String()]]);
        [$failed] = $this->makePendingCard($user, 5.0, 'processing');
        $failed->update(['card_status' => 'failed']);
        [$failingTx, $tx] = $this->makePendingCard($user, 5.0, 'processing');
        $tx->update(['transaction_status' => 'failed']);
        [$active] = $this->makePendingCard($user, 5.0, 'processing');
        $active->update(['card_status' => 'active']);
        $this->makePendingCard($other, 5.0, 'pending');

        $res = $this->actingAs($user, 'api')->getJson('/api/user/crypto-cards/pending');

        $res->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('count', 3)
            ->assertJsonStructure(['success', 'count', 'data' => [[
                'id', 'provider', 'type', 'brand', 'currency', 'status', 'stage', 'stage_label',
                'transaction_id', 'transaction_status', 'charge_amount', 'charge_currency', 'created_at', 'updated_at',
            ]]]);

        $byId = collect($res->json('data'))->keyBy('id');
        $this->assertEqualsCanonicalizing([$queued->id, $converting->id, $creating->id], $byId->keys()->all());
        $this->assertSame('queued', $byId[$queued->id]['stage']);
        $this->assertSame('pending', $byId[$queued->id]['status']);
        $this->assertSame('converting_funds', $byId[$converting->id]['stage']);
        $this->assertSame('creating_card', $byId[$creating->id]['stage']);
        $this->assertSame('USDT', $byId[$queued->id]['charge_currency']);
    }

    public function test_empty_list(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user, 'api')->getJson('/api/user/crypto-cards/pending')
            ->assertOk()
            ->assertExactJson(['success' => true, 'count' => 0, 'data' => []]);
    }
}
