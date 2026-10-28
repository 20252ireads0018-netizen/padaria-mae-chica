<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserController extends Controller
{
    public function index()
    {
        return view('users.index', [
            'users' => User::orderByDesc('active')->orderBy('role')->orderBy('name')->get(),
            'settings' => collect(Setting::DEFAULTS)->mapWithKeys(fn ($v, $k) => [$k => Setting::get($k)]),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'email' => 'required|email|unique:users,email',
            'role' => ['required', Rule::in(array_keys(User::ROLES))],
            'password' => ['required', Password::min(6)],
        ]);
        User::create($data);

        return back()->with('ok', 'Usuário criado.');
    }

    public function update(Request $request, User $user)
    {
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'email' => ['required', 'email', Rule::unique('users')->ignore($user->id)],
            'role' => ['required', Rule::in(array_keys(User::ROLES))],
            'password' => ['nullable', Password::min(6)],
        ]);
        if (empty($data['password'])) {
            unset($data['password']);
        }
        $data['active'] = $request->boolean('active');

        if ($user->is($request->user()) && ($data['role'] !== 'gerencia' || ! $data['active'])) {
            return back()->with('error', 'Você não pode remover o próprio acesso de gerência.');
        }

        $user->update($data);

        return back()->with('ok', 'Usuário atualizado.');
    }

    public function destroy(Request $request, User $user)
    {
        if ($user->is($request->user())) {
            return back()->with('error', 'Você não pode excluir a si mesmo.');
        }
        $user->update(['active' => false]);

        return back()->with('ok', 'Usuário desativado.');
    }

    public function settings(Request $request)
    {
        $data = $request->validate([
            'reserve_percent' => 'required|numeric|min:0|max:100',
            'reserve_minimum' => 'required',
            'cash_float' => 'required',
            'order_alert_hours' => 'required|integer|min:1|max:720',
        ]);
        $data['reserve_minimum'] = money_in($data['reserve_minimum']);
        $data['cash_float'] = money_in($data['cash_float']);

        foreach ($data as $k => $v) {
            Setting::put($k, $v);
        }

        return back()->with('ok', 'Configurações salvas.');
    }
}
