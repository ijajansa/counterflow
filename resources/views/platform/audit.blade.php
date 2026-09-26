<x-layouts.app title="Audit log">
    <div class="page-heading"><div><p class="eyebrow">ACCOUNTABILITY</p><h1>Audit log</h1><p class="muted mt-2">Administrative changes across the platform and its businesses.</p></div><span class="badge">{{ $logs->total() }} records</span></div>
    <section class="card"><form method="GET" class="mb-6 flex max-w-lg gap-3"><label class="sr-only" for="q">Search actions</label><input class="input" id="q" name="q" value="{{ request('q') }}" placeholder="Search actions, e.g. subscription"><button class="btn btn-secondary" type="submit">Search</button></form>
        <div class="table-wrap"><table class="data-table"><thead><tr><th>Action</th><th>Business</th><th>Changed by</th><th>When ({{ config('app.timezone') }})</th><th>Details</th></tr></thead><tbody>
            @forelse($logs as $log)<tr><td class="font-medium">{{ $log->action }}</td><td>{{ $log->business?->name ?? 'Platform' }}</td><td>{{ $log->user?->name ?? 'System' }}</td><td class="whitespace-nowrap">{{ $log->created_at->format('d M Y, H:i') }}</td><td><details><summary class="cursor-pointer text-teal-700">View record</summary><pre class="mt-3 max-w-sm overflow-auto whitespace-pre-wrap text-xs">{{ json_encode($log->metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre></details></td></tr>
            @empty<tr><td colspan="5" class="py-12 text-center muted">No administrative changes match this search.</td></tr>@endforelse
        </tbody></table></div><div class="mt-5">{{ $logs->links() }}</div>
    </section>
</x-layouts.app>
