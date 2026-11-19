@extends('layouts.app',['title'=>'Nova cobrança · Mensaliza'])
@section('content')
<a class="back" href="{{ route('charges.index') }}">‹ Voltar às cobranças</a>
<div class="page-title"><div><p class="eyebrow">FINANCEIRO</p><h1>Nova cobrança</h1><p>Emita uma cobrança avulsa vinculada a um contrato ativo.</p></div></div>
<form class="form-layout" method="post" action="{{ route('charges.store') }}">@csrf
<section class="panel form-card"><div class="section-heading"><span>1</span><div><h3>Origem</h3><p>O pagador e o plano são obtidos do contrato selecionado.</p></div></div>
<label>Contrato<select name="contract_id" id="contract" required><option value="">Selecione um contrato...</option>@foreach($contracts as $contract)<option value="{{ $contract->id }}" data-amount="{{ number_format($contract->plan->amount_cents/100,2,'.','') }}" @selected(old('contract_id')==$contract->id)>#{{ str_pad($contract->id,4,'0',STR_PAD_LEFT) }} · {{ $contract->customer->name }} · {{ $contract->plan->name }}</option>@endforeach</select></label>
<div class="section-heading"><span>2</span><div><h3>Detalhes financeiros</h3><p>A competência identifica o ciclo; o vencimento é a data exigível.</p></div></div>
<div class="form-grid"><label>Competência<input type="month" name="competence" value="{{ old('competence',now()->format('Y-m')) }}" required></label><label>Vencimento opcional<input type="date" name="due_date" value="{{ old('due_date') }}"><small>Em branco, usa o dia definido no contrato.</small></label><label>Descrição<input name="description" value="{{ old('description','Mensalidade · '.now()->translatedFormat('F/Y')) }}" required></label><label>Valor (R$)<input id="amount" name="amount" inputmode="decimal" value="{{ old('amount') }}" required></label></div>
</section><aside class="panel form-summary"><h3>Emissão segura</h3><p>Uma chave única impede duas cobranças do mesmo contrato na mesma competência.</p>@if($errors->any())<div class="error"><strong>Revise os dados:</strong><br>{{ $errors->first() }}</div>@endif<button class="button">Emitir cobrança</button><a href="{{ route('charges.index') }}">Cancelar</a></aside></form>
<script>document.getElementById('contract').addEventListener('change',function(){const o=this.selectedOptions[0];if(o?.dataset.amount&&!document.getElementById('amount').value)document.getElementById('amount').value=o.dataset.amount})</script>
@endsection
