@extends('layouts.app')

@section('title', 'Деталі наказу')

@section('content')
    <section class="rounded-2xl border border-slate-800 bg-slate-900/60 p-6 shadow-2xl shadow-slate-950/30">
        <div class="mb-6">
            <a href="{{ route('orders.index') }}" class="text-sm text-indigo-300 hover:text-indigo-200">← Назад до списку</a>
            <h2 class="mt-2 text-xl font-semibold text-white">Наказ № {{ $order->number ?? $order->id }}</h2>
            <p class="mt-1 text-sm text-slate-400">{{ $order->title ?? 'Деталі будуть доступні після реалізації зберігання даних.' }}</p>
        </div>

        <div class="mb-6 grid gap-4 sm:grid-cols-3">
            <div class="rounded-xl border border-slate-800 bg-slate-950/60 p-4">
                <div class="text-xs uppercase tracking-wide text-slate-500">Посилання</div>
                <div class="mt-1 break-all text-sm text-slate-300">{{ $order->document_url ?? '—' }}</div>
            </div>
            <div class="rounded-xl border border-slate-800 bg-slate-950/60 p-4">
                <div class="text-xs uppercase tracking-wide text-slate-500">Дата розбору</div>
                <div class="mt-1 text-sm text-slate-300">{{ $order->parsed_at?->toDateTimeString() ?? '—' }}</div>
            </div>
            <div class="rounded-xl border border-slate-800 bg-slate-950/60 p-4">
                <div class="text-xs uppercase tracking-wide text-slate-500">Нагороджених</div>
                <div class="mt-1 text-sm text-slate-300">{{ $order->awardees->count() }}</div>
            </div>
        </div>

        <div class="mb-3 flex items-center justify-between gap-3">
            <h3 class="text-lg font-semibold text-white">Нагороджені особи</h3>
            <a href="{{ route('orders.export', $order->id) }}" class="rounded-lg bg-emerald-500 px-3 py-2 text-sm font-medium text-white hover:bg-emerald-400">Експорт у CSV</a>
        </div>

        @if ($order->awardees->isEmpty())
            <p class="text-sm text-slate-400">Список нагороджених ще не розібрано.</p>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-800 text-sm">
                    <thead class="bg-slate-900">
                        <tr>
                            <th class="px-4 py-3 text-left font-medium text-slate-300">ПІБ</th>
                            <th class="px-4 py-3 text-left font-medium text-slate-300">Ранг</th>
                            <th class="px-4 py-3 text-left font-medium text-slate-300">Нагорода</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800">
                        @foreach ($order->awardees as $awardee)
                            <tr class="hover:bg-slate-800/50">
                                <td class="px-4 py-3 text-slate-100">
                                    <span class="awardee-name" data-id="{{ $awardee->id }}">{{ $awardee->full_name }}</span>
                                    <button type="button" class="ml-2 rounded-md border border-slate-700 px-2 py-1 text-xs text-slate-300 hover:border-indigo-400 hover:text-indigo-300" onclick="editAwardeeName(this, {{ $awardee->id }})">Редагувати</button>
                                    <div class="hidden mt-2 flex gap-2" data-edit-form="{{ $awardee->id }}" style="display:none;">
                                        <input type="text" value="{{ $awardee->full_name }}" class="w-full rounded-md border border-slate-700 bg-slate-950 px-2 py-1 text-sm text-slate-100 focus:border-indigo-400 focus:outline-none">
                                        <button type="button" class="rounded-md bg-indigo-500 px-2 py-1 text-xs font-medium text-white hover:bg-indigo-400" onclick="saveAwardeeName({{ $awardee->id }}, this)">Зберегти</button>
                                        <button type="button" class="rounded-md border border-slate-700 px-2 py-1 text-xs text-slate-300 hover:bg-slate-800" onclick="cancelAwardeeEdit({{ $awardee->id }})">Скасувати</button>
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-slate-300">{{ $awardee->rank ?? '—' }}</td>
                                <td class="px-4 py-3 text-slate-300">{{ $awardee->award ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
    <script>
        function editAwardeeName(button, id) {
            const cell = button.closest('td');
            cell.querySelector('.awardee-name').classList.add('hidden');
            button.classList.add('hidden');
            const editForm = cell.querySelector('[data-edit-form="' + id + '"]');
            editForm.classList.remove('hidden');
            editForm.style.display = 'flex';
        }

        function cancelAwardeeEdit(id) {
            const form = document.querySelector('[data-edit-form="' + id + '"]');
            const cell = form.closest('td');
            form.classList.add('hidden');
            form.style.display = 'none';
            cell.querySelector('.awardee-name').classList.remove('hidden');
            form.previousElementSibling.classList.remove('hidden');
        }

        async function saveAwardeeName(id, button) {
            const form = button.closest('[data-edit-form]');
            const cell = form.closest('td');
            const input = form.querySelector('input');
            const name = input.value.trim();

            if (!name) { return; }

            const response = await fetch('/awardees/' + id, {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
                body: JSON.stringify({ full_name: name }),
            });

            if (!response.ok) { return; }

            const data = await response.json();
            cell.querySelector('.awardee-name').textContent = data.full_name;
            input.value = data.full_name;
            form.classList.add('hidden');
            form.style.display = 'none';
            cell.querySelector('.awardee-name').classList.remove('hidden');
            form.previousElementSibling.classList.remove('hidden');
        }
    </script>
@endsection
