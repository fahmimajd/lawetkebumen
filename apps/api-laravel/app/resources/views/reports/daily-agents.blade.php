@extends('layouts.app')

@section('content')
    <section class="panel panel--full report-panel" data-daily-agent-report data-report-url="{{ $dataUrl }}">
        <div class="panel__header panel__header--row">
            <div class="panel__title">Daily Agent Report</div>
            <div class="report-filters">
                <label class="auth-label" for="report-date">Date</label>
                <input
                    id="report-date"
                    type="date"
                    class="input report-filters__input"
                    data-report-date
                    value="{{ $defaultDate }}"
                >
            </div>
        </div>

        <div class="report__error" data-report-error></div>

        <div class="report-table">
            <div class="report-table__row report-table__row--head">
                <div>Agent</div>
                <div>Assigned</div>
                <div>Resolved</div>
                <div>Active</div>
                <div>Transfer Out</div>
                <div>Reopened</div>
                <div>Sent</div>
                <div>Received</div>
            </div>
            <div data-report-body>
                <div class="report-table__row report-table__row--empty">Loading…</div>
            </div>
        </div>

        <div class="report__note">Transfer out membutuhkan riwayat assignment.</div>
    </section>
@endsection
