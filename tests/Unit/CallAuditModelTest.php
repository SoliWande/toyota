<?php

namespace Tests\Unit;

use App\Models\CallAuditAttempt;
use App\Models\CallAuditBatch;
use App\Models\CallAuditEvent;
use App\Models\CallAuditItem;
use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Encryption\Encrypter;
use Illuminate\Events\Dispatcher;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CallAuditModelTest extends TestCase
{
    private mixed $previousEncrypter;

    private mixed $previousDispatcher;

    protected function setUp(): void
    {
        parent::setUp();
        $this->previousEncrypter = Model::currentEncrypter();
        $this->previousDispatcher = Model::getEventDispatcher();
        Model::encryptUsing(new Encrypter(str_repeat('t', 32), 'AES-256-CBC'));
        Model::setEventDispatcher(new Dispatcher(new Container));
        Model::clearBootedModels();
    }

    protected function tearDown(): void
    {
        Model::encryptUsing($this->previousEncrypter);
        if ($this->previousDispatcher) {
            Model::setEventDispatcher($this->previousDispatcher);
        } else {
            Model::unsetEventDispatcher();
        }
        Model::clearBootedModels();
        parent::tearDown();
    }

    public function test_private_snapshots_are_encrypted_at_rest_and_hidden_from_serialization(): void
    {
        foreach ([
            [new CallAuditBatch, 'manifest_encrypted', ['phone' => '0901234567']],
            [new CallAuditBatch, 'seed_encrypted', 'private-seed'],
            [new CallAuditItem, 'source_snapshot_encrypted', ['phone' => '0901234567']],
            [new CallAuditAttempt, 'note_encrypted', 'private-note'],
            [new CallAuditEvent, 'private_payload_encrypted', ['phone' => '0901234567']],
        ] as [$model, $attribute, $value]) {
            $model->forceFill([$attribute => $value]);
            $raw = $model->getAttributes()[$attribute];
            $this->assertIsString($raw);
            $this->assertStringNotContainsString(is_array($value) ? $value['phone'] : $value, $raw);
            $this->assertSame($value, $model->$attribute);
            $this->assertArrayNotHasKey($attribute, $model->toArray());
        }
    }

    #[DataProvider('immutableChanges')]
    public function test_frozen_content_and_completed_history_reject_updates_before_any_query(string $class, string $field, mixed $value): void
    {
        $model = new $class;
        $model->setRawAttributes(['id' => 1], true);
        $model->exists = true;
        $model->forceFill([$field => $value]);
        // save() resolves a connection before firing updating; invoke the registered event directly.
        $this->expectException(LogicException::class);
        Model::getEventDispatcher()->dispatch('eloquent.updating: '.$class, $model);
    }

    #[DataProvider('models')]
    public function test_deletion_is_rejected_before_any_query(string $class): void
    {
        $model = new $class;
        $this->expectException(LogicException::class);
        Model::getEventDispatcher()->dispatch('eloquent.deleting: '.$class, $model);
    }

    public function test_no_answer_can_preserve_unknown_answers(): void
    {
        $attempt = new CallAuditAttempt;
        $attempt->forceFill(['answer_1' => null, 'answer_2' => false, 'answer_3' => true]);
        $this->assertNull($attempt->answer_1);
        $this->assertFalse($attempt->answer_2);
        $this->assertTrue($attempt->answer_3);
    }

    public static function immutableChanges(): array
    {
        return [
            [CallAuditBatch::class, 'monthly_scope_key', '2026-12'],
            [CallAuditBatch::class, 'actual_count', 2],
            [CallAuditBatch::class, 'seed_encrypted', 'another-seed'],
            [CallAuditItem::class, 'submission_id', 99],
            [CallAuditItem::class, 'source_snapshot_encrypted', ['phone' => 'changed']],
            [CallAuditAttempt::class, 'outcome', 'changed'],
            [CallAuditEvent::class, 'actor_id', 99],
        ];
    }

    public static function models(): array
    {
        return array_map(fn ($class) => [$class], [CallAuditBatch::class, CallAuditItem::class, CallAuditAttempt::class, CallAuditEvent::class]);
    }
}
