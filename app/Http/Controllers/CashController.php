<?php

namespace App\Http\Controllers;

use App\Models\CashClosing;
use App\Models\FinancialEntry;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class CashController extends Controller
{
    public function index(Request $request)
    {
        $date = Carbon::parse($request->input('data', now()->toDateString()))->toDateString();

        return view('cash.index', [
            'date' => $date,
            'entries' => FinancialEntry::with('user')->whereDate('date', $date)->latest()->get(),
            'summary' => $this->summarize($date),
            'closing' => CashClosing::whereDate('date', $date)->first(),
            'closings' => CashClosing::with('user')->latest('date')->limit(15)->get(),
        ]);
    }

    public function storeEntry(Request $request)
    {
        $data = $request->validate([
            'date' => 'required|date',
            'type' => ['required', Rule::in(['receita', 'despesa'])],
            'category' => 'required|string|max:40',
            'description' => 'nullable|string|max:255',
            'amount' => 'required',
            'payment_method' => ['required', Rule::in(array_keys(FinancialEntry::METHODS))],
        ]);
        $data['amount'] = money_in($data['amount']);
        abort_if($data['amount'] <= 0, 422, 'Informe um valor maior que zero.');

        if (CashClosing::whereDate('date', $data['date'])->exists() && ! $request->user()->hasRole()) {
            return back()->with('error', 'O caixa deste dia já foi fechado. Peça à gerência para reabrir.');
        }

        FinancialEntry::create([...$data, 'user_id' => $request->user()->id]);

        return back()->with('ok', 'Lançamento registrado.');
    }

    public function destroyEntry(Request $request, FinancialEntry $entry)
    {
        if ($entry->isAutomatic()) {
            return back()->with('error', 'Lançamento automático: altere pela encomenda, escola ou pedido de origem.');
        }
        if (CashClosing::whereDate('date', $entry->date)->exists() && ! $request->user()->hasRole()) {
            return back()->with('error', 'O caixa deste dia já foi fechado.');
        }
        $entry->delete();

        return back()->with('ok', 'Lançamento removido.');
    }

    /** Pré-visualização do fechamento: tudo é calculado automaticamente a partir dos lançamentos do dia. */
    public function create(Request $request)
    {
        $date = Carbon::parse($request->input('data', now()->toDateString()))->toDateString();

        if ($existing = CashClosing::whereDate('date', $date)->first()) {
            return redirect()->route('cash.closing.show', $existing);
        }

        return view('cash.closing', [
            'date' => $date,
            's' => $this->summarize($date),
            'entries' => FinancialEntry::whereDate('date', $date)->orderBy('type')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate(['date' => 'required|date', 'counted_cash' => 'nullable', 'opening_balance' => 'nullable', 'notes' => 'nullable|string|max:1000']);
        $date = Carbon::parse($data['date'])->toDateString();

        abort_if(CashClosing::whereDate('date', $date)->exists(), 422, 'Caixa já fechado neste dia.');

        $opening = filled($data['opening_balance'] ?? null) ? money_in($data['opening_balance']) : null;
        $s = $this->summarize($date, $opening);
        $counted = filled($data['counted_cash'] ?? null) ? money_in($data['counted_cash']) : null;

        $closing = CashClosing::create([
            'date' => $date,
            'user_id' => $request->user()->id,
            'opening_balance' => $s['opening'],
            'income_cash' => $s['in']['dinheiro'],
            'income_pix' => $s['in']['pix'],
            'income_card' => $s['in']['cartao'],
            'income_other' => $s['in']['boleto'] + $s['in']['outro'],
            'total_income' => $s['income'],
            'total_expense' => $s['expense'],
            'expected_balance' => $s['drawer'],
            'counted_cash' => $counted,
            'difference' => $counted === null ? 0 : round($counted - $s['drawer'], 2),
            'reserve_amount' => $s['reserve'],
            'available_balance' => $s['available'],
            'notes' => $data['notes'] ?? null,
        ]);

        return redirect()->route('cash.closing.show', $closing)->with('ok', 'Caixa fechado com sucesso.');
    }

    public function show(CashClosing $closing)
    {
        return view('cash.show', [
            'c' => $closing->load('user'),
            'entries' => FinancialEntry::whereDate('date', $closing->date)->orderBy('type')->get(),
        ]);
    }

    public function destroy(CashClosing $closing)
    {
        $date = $closing->date->toDateString();
        $closing->delete();

        return redirect()->route('cash.index', ['data' => $date])->with('ok', 'Fechamento reaberto. Ajuste os lançamentos e feche novamente.');
    }

    /**
     * Consolida o dia:
     *  resultado  = receitas − despesas
     *  gaveta     = troco inicial + receitas em dinheiro − despesas pagas em dinheiro
     *  reserva    = max(% do resultado, mínimo), limitada ao resultado → vai para o fluxo de caixa
     *  disponível = resultado − reserva
     */
    public function summarize(string $date, ?float $opening = null): array
    {
        $entries = FinancialEntry::whereDate('date', $date)->get();
        $in = array_fill_keys(array_keys(FinancialEntry::METHODS), 0.0);
        $out = $in;
        foreach ($entries as $e) {
            $e->type === 'receita' ? $in[$e->payment_method] += $e->amount : $out[$e->payment_method] += $e->amount;
        }

        $previous = CashClosing::whereDate('date', '<', $date)->latest('date')->first();
        $opening ??= $previous && $previous->counted_cash !== null
            ? min((float) $previous->counted_cash, (float) Setting::get('cash_float'))
            : (float) Setting::get('cash_float');

        $income = array_sum($in);
        $expense = array_sum($out);
        $result = round($income - $expense, 2);

        $percent = (float) Setting::get('reserve_percent');
        $minimum = (float) Setting::get('reserve_minimum');
        $reserve = $result > 0 ? round(min($result, max($result * $percent / 100, $minimum)), 2) : 0;

        return [
            'in' => $in, 'out' => $out,
            'income' => round($income, 2), 'expense' => round($expense, 2), 'result' => $result,
            'opening' => round($opening, 2),
            'drawer' => round($opening + $in['dinheiro'] - $out['dinheiro'], 2),
            'percent' => $percent, 'minimum' => $minimum,
            'reserve' => $reserve,
            'available' => round($result - $reserve, 2),
            'count' => $entries->count(),
            'auto' => $entries->whereNotNull('source_type')->count(),
        ];
    }
}
