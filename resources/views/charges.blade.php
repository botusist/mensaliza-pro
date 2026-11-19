@extends('layouts.app',['title'=>'Cobranças · Mensaliza'])
@section('content')
<div class="page-title"><div><p class="eyebrow">FINANCEIRO</p><h1>Cobranças</h1><p>Mensalidades emitidas e seus saldos atuais.</p></div><a class="button" href="{{ route('charges.create') }}">+ Nova cobrança</a></div>
<div class="tabs"><a class="{{ !request('status')?'active':'' }}" href="{{ route('charges.index') }}">Todas</a><a class="{{ request('status')==='overdue'?'active':'' }}" href="?status=overdue">Vencidas</a></div><section class="panel">@include('partials.charge-table',['charges'=>$charges])</section>{{ $charges->links() }}
@endsection
