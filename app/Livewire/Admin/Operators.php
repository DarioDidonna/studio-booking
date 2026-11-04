<?php

namespace App\Livewire\Admin;

use App\Enums\UserRole;
use App\Models\Operator;
use App\Models\User;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * @property-read Collection<int, Operator> $operators
 */
#[Title('Operators')]
class Operators extends Component
{
    public string $name = '';

    /**
     * Optional: creates a staff account for the operator to see their agenda.
     */
    public string $email = '';

    public function mount(): void
    {
        $this->authorize('viewAny', Operator::class);
    }

    /**
     * @return Collection<int, Operator>
     */
    #[Computed]
    public function operators(): Collection
    {
        return Operator::query()->with(['user', 'services'])->orderBy('name')->get();
    }

    public function create(): void
    {
        $this->authorize('create', Operator::class);

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:255', 'unique:users,email'],
        ]);

        $operator = DB::transaction(function () use ($validated): Operator {
            $user = null;

            if ($validated['email'] !== '' && $validated['email'] !== null) {
                $user = new User([
                    'name' => $validated['name'],
                    'email' => $validated['email'],
                    'password' => Str::password(32),
                ]);
                $user->role = UserRole::Staff;
                $user->email_verified_at = now();
                $user->save();
            }

            return Operator::create(['name' => $validated['name'], 'user_id' => $user?->id]);
        });

        if ($operator->user !== null) {
            Password::sendResetLink(['email' => $operator->user->email]);
        }

        $this->reset('name', 'email');
        $this->modal('operator-form')->close();

        $this->redirectRoute('admin.operators.edit', $operator, navigate: true);
    }

    public function toggleActive(int $operatorId): void
    {
        $operator = Operator::query()->findOrFail($operatorId);

        $this->authorize('update', $operator);

        $operator->update(['is_active' => ! $operator->is_active]);

        unset($this->operators);
        Flux::toast(text: $operator->is_active ? __('Operator activated.') : __('Operator deactivated. Existing bookings are kept.'));
    }
}
