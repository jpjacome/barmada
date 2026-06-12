<?php

namespace App\Actions\Staff;

use App\Models\User;
use Illuminate\Support\Facades\Hash;

/**
 * Creates or updates a staff account for an editor's tenant — one code
 * path for the web staff screen and the API.
 *
 * Role flags are not mass-assignable by design: is_staff is set
 * explicitly via forceFill, exactly like the other trusted account
 * factories. Validation (email uniqueness, password rules) belongs to
 * the calling boundary; authorization (createStaff / manageStaff
 * policies) does too.
 *
 * @see \App\Policies\UserPolicy
 */
class SaveStaffMember
{
    /**
     * @param  array{name: string, email: string, password?: ?string}  $data
     */
    public function handle(User $editor, array $data, ?User $staff = null): User
    {
        if ($staff) {
            $staff->name = $data['name'];
            $staff->email = $data['email'];
            if (! empty($data['password'])) {
                $staff->password = Hash::make($data['password']);
            }
            $staff->save();

            return $staff->refresh();
        }

        $created = User::create([
            'username' => strtolower(preg_replace('/\s+/', '', $data['name'])).rand(1000, 9999),
            'first_name' => $data['name'],
            'last_name' => 'Staff',
            'name' => $data['name'].' Staff',
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'editor_id' => $editor->id,
        ]);

        // Role flag is not mass-assignable; set it explicitly.
        $created->forceFill(['is_staff' => true])->save();

        return $created->refresh();
    }
}
