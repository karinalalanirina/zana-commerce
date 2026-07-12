<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Package;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Dedicated admin CRUD for ADD-ONS — a separate section (like credit
 * packages), distinct from plans. Add-ons are `packages` rows with
 * type='addon'; the create/edit FORM is shared with packages (it already
 * has the Type selector + every feature toggle/limit), so this controller
 * owns the list + toggle + delete, and create/edit reuse the packages form
 * pre-set to the add-on type. After saving, packagePersist() routes an
 * add-on back here.
 */
class AddonsController extends Controller
{
    public function index(): View
    {
        $addons = Package::query()->addons()
            ->orderBy('sort_order')->orderBy('plan_amount')->get();

        return view('admin.addons.index', ['addons' => $addons]);
    }

    public function toggle(int $id): RedirectResponse
    {
        $addon = Package::query()->addons()->findOrFail($id);
        $addon->update(['status' => !$addon->status]);

        return back()->with('success', 'Add-on ' . ($addon->status ? 'enabled' : 'disabled') . '.');
    }

    public function destroy(int $id): RedirectResponse
    {
        $addon = Package::query()->addons()->findOrFail($id);
        $addon->delete();

        return redirect()->route('admin.addons.index')->with('success', 'Add-on deleted.');
    }
}
