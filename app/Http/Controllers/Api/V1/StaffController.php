<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Staff\SaveStaffMember;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;

/**
 * Staff account management — strictly editor-only (UserPolicy has no
 * admin bypass here by design: staff tooling never touches admin or
 * editor accounts). Lookups are constrained to the editor's own staff,
 * so cross-tenant ids 404 before any attribute is read.
 */
class StaffController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request)
    {
        $this->authorize('createStaff', User::class);

        return response()->json([
            'staff' => $this->ownStaff($request->user())
                ->orderBy('name')
                ->get()
                ->map(fn (User $staff) => $this->staffRow($staff)),
        ]);
    }

    public function store(Request $request, SaveStaffMember $saveStaff)
    {
        $this->authorize('createStaff', User::class);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email',
            'password' => 'required|min:6',
        ]);

        $staff = $saveStaff->handle($request->user(), $validated);

        return response()->json(['staff' => $this->staffRow($staff)], 201);
    }

    public function update(Request $request, int $id, SaveStaffMember $saveStaff)
    {
        $staff = $this->ownStaff($request->user())->findOrFail($id);
        $this->authorize('manageStaff', $staff);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email,'.$staff->id,
            'password' => 'nullable|min:6',
        ]);

        $saveStaff->handle($request->user(), $validated, $staff);

        return response()->json(['staff' => $this->staffRow($staff->refresh())]);
    }

    public function destroy(Request $request, int $id)
    {
        $staff = $this->ownStaff($request->user())->findOrFail($id);
        $this->authorize('manageStaff', $staff);

        $staff->delete();

        return response()->json(['message' => __('Staff member deleted.')]);
    }

    private function ownStaff(User $editor)
    {
        return User::where('is_staff', true)->where('editor_id', $editor->id);
    }

    private function staffRow(User $staff): array
    {
        return [
            'id' => $staff->id,
            'name' => $staff->first_name ?: $staff->name,
            'display_name' => $staff->name,
            'email' => $staff->email,
            'created_at' => $staff->created_at?->toIso8601String(),
        ];
    }
}
