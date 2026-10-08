<?php

namespace App\Policies;

use App\Models\Commission;
use App\Models\CommissionApplication;
use App\Models\CommissionMember;
use App\Models\User;
use Illuminate\Support\Facades\Schema;

class CommissionPolicy
{
    public function viewAny(User $user): bool
    {
        if ($user->status !== 'active') {
            return false;
        }

        if ($user->can('commissions.manage')) {
            return true;
        }

        return Schema::hasTable('commission_members')
            && CommissionMember::query()->where('user_id', $user->id)->exists();
    }

    public function view(User $user, Commission $commission): bool
    {
        if ($user->status !== 'active') {
            return false;
        }

        if ($user->can('commissions.manage')) {
            return true;
        }

        return Schema::hasTable('commission_members')
            && $commission->members()->where('user_id', $user->id)->exists();
    }

    public function create(User $user): bool
    {
        return $this->canManage($user);
    }

    public function update(User $user, Commission $commission): bool
    {
        return $this->canManage($user);
    }

    public function manageMembers(User $user, Commission $commission): bool
    {
        return $this->canManage($user);
    }

    public function manageApplications(User $user, Commission $commission): bool
    {
        return $this->canManage($user);
    }

    public function manageAttendance(User $user, Commission $commission): bool
    {
        return $this->canManage($user);
    }

    public function vote(User $user, Commission $commission, CommissionApplication $item): bool
    {
        if ($user->status !== 'active' || $commission->status !== 'in_progress' || $item->commission_id !== $commission->id) {
            return false;
        }

        return $commission->members()
            ->where('user_id', $user->id)
            ->where('attendance_status', 'present')
            ->exists();
    }

    public function decide(User $user, Commission $commission): bool
    {
        if ($user->status !== 'active' || $commission->status !== 'in_progress') {
            return false;
        }

        return $this->canManage($user) || ($user->status === 'active' && $commission->members()
            ->where('user_id', $user->id)
            ->whereIn('role', ['president', 'secretary'])
            ->where('attendance_status', 'present')
            ->exists());
    }

    public function validateDecision(User $user, Commission $commission): bool
    {
        return $this->canManage($user) && $commission->status === 'in_progress';
    }

    public function manageMinutes(User $user, Commission $commission): bool
    {
        return $user->status === 'active' && ($user->can('commissions.manage') || $commission->members()
            ->where('user_id', $user->id)
            ->where('role', 'secretary')
            ->where('attendance_status', 'present')
            ->exists());
    }

    public function validateMinutes(User $user, Commission $commission): bool
    {
        return $this->canManage($user) && $commission->status === 'in_progress';
    }

    public function viewHistory(User $user, Commission $commission): bool
    {
        return $this->view($user, $commission);
    }

    private function canManage(User $user): bool
    {
        return $user->status === 'active' && $user->can('commissions.manage');
    }
}