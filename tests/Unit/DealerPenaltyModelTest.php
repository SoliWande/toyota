<?php

namespace Tests\Unit;

use App\Models\DealerPenaltyEntry;
use App\Models\DealerPenaltyEvent;
use App\Models\DealerPenaltyEvidence;
use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Encryption\Encrypter;
use Illuminate\Events\Dispatcher;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class DealerPenaltyModelTest extends TestCase
{
    private mixed $previousEncrypter;

    private mixed $previousDispatcher;

    protected function setUp(): void
    {
        parent::setUp();
        $this->previousEncrypter = Model::$encrypter;
        $this->previousDispatcher = Model::getEventDispatcher();
        Model::encryptUsing(new Encrypter(str_repeat('p', 32), 'AES-256-CBC'));
        Model::setEventDispatcher(new Dispatcher(new Container));
        Model::clearBootedModels();
    }

    protected function tearDown(): void
    {
        Model::encryptUsing($this->previousEncrypter);
        $this->previousDispatcher ? Model::setEventDispatcher($this->previousDispatcher) : Model::unsetEventDispatcher();
        Model::clearBootedModels();
        parent::tearDown();
    }

    public function test_encrypted_arrays_round_trip_in_memory_and_all_private_metadata_is_hidden(): void
    {
        foreach ([
            [DealerPenaltyEntry::class, 'policy_snapshot_encrypted'], [DealerPenaltyEntry::class, 'dealer_attribution_encrypted'],
            [DealerPenaltyEvidence::class, 'evidence_snapshot_encrypted'],
            [DealerPenaltyEvent::class, 'intent_encrypted'], [DealerPenaltyEvent::class, 'receipt_encrypted'],
        ] as [$class, $field]) {
            $model = (new $class)->forceFill([$field => ['private' => 'PRIVATE-LEDGER-DATA']]);
            $cipher = $model->getAttributes()[$field];
            $this->assertIsString($cipher);
            $this->assertStringNotContainsString('PRIVATE-LEDGER-DATA', $cipher);
            $read = new $class;
            $read->setRawAttributes([$field => $cipher], true);
            $this->assertSame(['private' => 'PRIVATE-LEDGER-DATA'], $read->$field);
            $this->assertArrayNotHasKey($field, $read->toArray());
        }
        foreach ([DealerPenaltyEntry::class, DealerPenaltyEvidence::class, DealerPenaltyEvent::class] as $class) {
            $model = new $class;
            foreach ($model->getHidden() as $field) {
                $model->setRawAttributes([$field => 'PRIVATE-RAW'], true);
                $this->assertArrayNotHasKey($field, $model->toArray());
            }
            $this->assertSame(['*'], $model->getGuarded());
            $this->assertFalse($model->usesTimestamps());
        }
        $this->assertSame('dealer_penalty_evidence', (new DealerPenaltyEvidence)->getTable());
    }

    #[DataProvider('immutableEvents')]
    public function test_immutable_guards_are_registered_without_a_database_query(string $class, string $event): void
    {
        $model = (new $class)->forceFill(['id' => 1]);
        $this->expectException(LogicException::class);
        Model::getEventDispatcher()->dispatch('eloquent.'.$event.': '.$class, $model);
    }

    public static function immutableEvents(): array
    {
        $cases = [];
        foreach ([DealerPenaltyEntry::class, DealerPenaltyEvidence::class, DealerPenaltyEvent::class] as $class) {
            foreach (['updating', 'deleting'] as $event) {
                $cases[] = [$class, $event];
            }
        }

        return $cases;
    }

    public function test_versions_support_approved_dealer_only_floor_and_display_ranking_without_activating_projection(): void
    {
        $this->assertSame('dealer_minus_one_floor_zero_v1', DealerPenaltyEntry::POLICY);
        $this->assertSame('dealer_display_floor_zero_v1', DealerPenaltyEntry::PROJECTION_POLICY);
        $this->assertSame('display_desc_achieved_asc_id_asc_v1', DealerPenaltyEntry::RANKING_POLICY);
        $event = (new DealerPenaltyEvent)->forceFill(['before_version' => '0', 'after_version' => '1', 'evidence_count_before' => '0', 'evidence_count_after' => '2']);
        $this->assertSame([0, 1, 0, 2], [$event->before_version, $event->after_version, $event->evidence_count_before, $event->evidence_count_after]);
    }
}
