<?php

namespace App\Support;

use App\Models\ServiceCase;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

final class MisFieldActivityApproval
{
    /**
     * @return array<string, array<string, mixed>>
     */
    public static function modules(): array
    {
        return config('mis_field_activity_approval.modules', []);
    }

    /**
     * @return array<string, mixed>
     */
    public static function module(string $key): array
    {
        $modules = self::modules();
        if (! isset($modules[$key])) {
            throw new InvalidArgumentException('Unknown MIS field activity module: '.$key);
        }

        return $modules[$key];
    }

    public static function approverEmail(): string
    {
        return strtolower(trim((string) config('mis_field_activity_approval.approver_email', '')));
    }

    public static function resolveApproverUserId(): ?int
    {
        $email = self::approverEmail();
        if ($email === '') {
            return null;
        }

        $id = User::query()
            ->whereRaw('LOWER(email) = ?', [$email])
            ->where('role', 'state_staff')
            ->where('is_active', true)
            ->value('id');

        return $id ? (int) $id : null;
    }

    public static function approverUser(): ?User
    {
        $id = self::resolveApproverUserId();

        return $id ? User::query()->find($id) : null;
    }

    public static function isDedicatedApprover(?User $user): bool
    {
        if (! $user || $user->role !== 'state_staff') {
            return false;
        }

        $email = self::approverEmail();

        return $email !== '' && strtolower(trim((string) $user->email)) === $email;
    }

    public static function moduleKeyForModel(Model $model): ?string
    {
        foreach (self::modules() as $key => $meta) {
            $class = (string) ($meta['model'] ?? '');
            if ($class !== '' && $model instanceof $class) {
                return $key;
            }
        }

        return null;
    }

    public static function supportsWorkflowOnTable(string $table): bool
    {
        return Schema::hasTable($table) && Schema::hasColumn($table, 'status');
    }

    /**
     * @return class-string<Model>
     */
    public static function modelClass(string $moduleKey): string
    {
        return (string) self::module($moduleKey)['model'];
    }

    public static function findRecord(string $moduleKey, int $id): Model
    {
        $class = self::modelClass($moduleKey);

        return $class::query()->findOrFail($id);
    }

    public static function submitterCanEdit(?User $user, Model $record): bool
    {
        if (! $user || (int) $record->submitted_by_user_id !== (int) $user->id) {
            return false;
        }

        return method_exists($record, 'canBeEditedByMisFieldSubmitter')
            && $record->canBeEditedByMisFieldSubmitter();
    }

    public static function submitterCanWithdraw(?User $user, Model $record): bool
    {
        if (! $user || (int) $record->submitted_by_user_id !== (int) $user->id) {
            return false;
        }

        return method_exists($record, 'canBeWithdrawnByMisFieldSubmitter')
            && $record->canBeWithdrawnByMisFieldSubmitter();
    }

    /**
     * @param  Builder  $query
     */
    public static function applyApprovedOnlyFilter($query, string $table, ?string $alias = null): void
    {
        if (! self::supportsWorkflowOnTable($table)) {
            return;
        }

        $prefix = $alias ? $alias.'.' : '';

        $query->where($prefix.'status', ServiceCase::STATUS_APPROVED);
    }

    public static function remarkForRecord(Model $record): string
    {
        $status = method_exists($record, 'misFieldStatus') ? $record->misFieldStatus() : '';

        if ($status === ServiceCase::STATUS_SENT_BACK) {
            return trim((string) ($record->sent_back_note ?? ''));
        }

        if ($status === ServiceCase::STATUS_REJECTED) {
            return trim((string) ($record->rejected_note ?? ''));
        }

        $moduleKey = self::moduleKeyForModel($record);
        $summary = match ($moduleKey) {
            'technical_training' => trim((string) ($record->session_brief ?? '')),
            'lakhpati_technical_training' => trim((string) ($record->session_brief ?? '')),
            'line_department_meeting' => trim((string) ($record->agenda_remark_outcome ?? $record->agenda_summary ?? '')),
            'community_org_outreach' => trim((string) ($record->remarks ?? '')),
            default => '',
        };

        return $summary;
    }
}
