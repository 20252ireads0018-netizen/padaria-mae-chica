<label class="f"><span>Nome da escola *</span><input type="text" name="name" value="{{ $s->name }}" required></label>
<div class="row">
    <label class="f"><span>Responsável</span><input type="text" name="contact_name" value="{{ $s->contact_name }}"></label>
    <label class="f"><span>Telefone</span><input type="tel" name="phone" value="{{ $s->phone }}"></label>
</div>
<label class="f"><span>Endereço de entrega</span><input type="text" name="address" value="{{ $s->address }}"></label>
<label class="f"><span>Faturamento</span><select name="billing">@foreach (\App\Models\School::BILLING as $k => $l)<option value="{{ $k }}" @selected($s->billing === $k)>{{ $l }}</option>@endforeach</select></label>
