<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use Illuminate\Http\Request;

/**
 * Global dashboard appearance — lets the platform admin recolour EVERY theme
 * token (Tailwind v4 @theme vars) across BOTH the user + admin dashboards.
 * Values save to SystemSetting `theme.color.*` and are injected live by
 * theme_css() into each layout's <head> (no rebuild). Empty = shipped default.
 */
class AppearanceController extends Controller
{
    public function index()
    {
        $palette = theme_palette();
        $values  = [];
        foreach (array_keys($palette) as $k) {
            $values[$k] = theme_color($k);
        }
        return view('admin.settings.appearance', compact('palette', 'values'));
    }

    public function update(Request $request)
    {
        $colors = (array) $request->input('colors', []);
        foreach (array_keys(theme_palette()) as $k) {
            $val = trim((string) ($colors[$k] ?? ''));
            $ok  = $val !== '' && preg_match('/^#[0-9A-Fa-f]{3,8}$/', $val);
            SystemSetting::set('theme.color.' . $k, $ok ? $val : '', 'string', 'Dashboard theme colour override');
        }
        return back()->with('status', __('Theme colours saved — the whole dashboard has been recoloured.'));
    }

    public function reset(Request $request)
    {
        foreach (array_keys(theme_palette()) as $k) {
            SystemSetting::set('theme.color.' . $k, '', 'string', 'Dashboard theme colour override (reset)');
        }
        return back()->with('status', __('Theme colours reset to the shipped defaults.'));
    }
}
