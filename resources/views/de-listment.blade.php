@extends('layouts.app')

@section('title', 'De-Listment')

@section('content')
<div class="delist-page">
    <span id="delist-config"
        data-entry-years="{{ $entryYearsRequirement }}"
        data-require-baptism-date="{{ $requireBaptismDate ? 'true' : 'false' }}"
        data-endorse-url="{{ route('de-listment.endorse', ['record' => '__RECORD__']) }}"
        data-delist-url="{{ route('de-listment.delist', ['record' => '__RECORD__']) }}"
        data-mk-create-url="{{ route('de-listment.mk.store') }}"
        data-export-url="{{ route('de-listment.export', ['category' => '__CATEGORY__']) }}"
        data-update-dates-url="{{ route('de-listment.update-dates') }}"
        data-csrf="{{ csrf_token() }}"
        hidden></span>
    <div class="delist-heading">
        <div>
            <p class="delist-eyebrow">Member overview</p>
            <h1>De-Listment</h1>
            <p class="delist-subtitle">Review R2-10 eligibility and report progress at a glance.</p>
        </div>
        <div class="delist-tools">
            <button type="button" class="btn btn-primary" id="add-mk-btn" aria-haspopup="dialog" aria-controls="add-mk-dialog">Add MK member</button>
            <button type="button" class="missing-btn" id="missing-btn" aria-haspopup="dialog" aria-controls="missing-dialog">
                <svg viewBox="0 0 16 16" aria-hidden="true"><path d="M8 2.2 14.3 13H1.7L8 2.2Z"/><path d="M8 6.6v3M8 11.6v.1"/></svg>
                See entries with missing data
                <span class="missing-count" id="missing-count">0</span>
            </button>
            <button type="button" class="history-btn" id="delisted-history-btn" aria-haspopup="dialog" aria-controls="delisted-history-dialog">
                View delistment history
            </button>
        </div>
    </div>

    <dialog class="modal modal-wide" id="add-mk-dialog" aria-labelledby="add-mk-title">
        <form class="modal-body" id="add-mk-form" novalidate>
            <div class="modal-head">
                <div>
                    <h3 id="add-mk-title">Add an MK member</h3>
                    <p class="modal-sub">This adds a record directly as MK. No monthly code is used.</p>
                </div>
                <button type="button" class="modal-close" data-close aria-label="Close">
                    <svg viewBox="0 0 16 16" aria-hidden="true"><path d="m4 4 8 8M12 4l-8 8"/></svg>
                </button>
            </div>
            <div class="mk-add-grid">
                <label class="field-label" for="mk-full-name">Full name</label>
                <input id="mk-full-name" name="full_name" maxlength="150" required autocomplete="name">
                <label class="field-label" for="mk-registry">Registry / RFID</label>
                <input id="mk-registry" name="registry" maxlength="100" required>
                <label class="check-row mk-rfid" for="mk-is-rfid">
                    <input id="mk-is-rfid" name="is_rfid" type="checkbox" value="1">
                    <span>This member has RFID instead of a 13-character registry number.</span>
                </label>
                <label class="field-label" for="mk-gender">Gender</label>
                <select id="mk-gender" name="gender" required>
                    <option value="">Select gender</option>
                    <option value="Male">Male</option>
                    <option value="Female">Female</option>
                </select>
                <label class="field-label" for="mk-purok">Purok</label>
                <select id="mk-purok" name="purok_id" required>
                    <option value="">Select Purok</option>
                    @foreach ($puroks as $purok)
                        <option value="{{ $purok->id }}" data-start="{{ $purok->grupo_start }}" data-end="{{ $purok->grupo_end }}">
                            {{ $purok->purok_name ?: 'Purok '.$purok->purok_number }} ({{ $purok->grupo_start }}–{{ $purok->grupo_end }})
                        </option>
                    @endforeach
                </select>
                <label class="field-label" for="mk-grupo">Grupo <span class="modal-sub">(optional)</span></label>
                <input id="mk-grupo" name="grupo" type="number" min="1" step="1">
                <label class="field-label" for="mk-kapisanan">Kapisanan</label>
                <select id="mk-kapisanan" name="kapisanan" required>
                    <option value="">Select Kapisanan</option>
                    <option value="Buklod">Buklod</option>
                    <option value="Kadiwa">Kadiwa</option>
                    <option value="Binhi">Binhi</option>
                    <option value="Suliranin">Suliranin</option>
                </select>
                <span class="modal-sub mk-kapisanan-hint" id="mk-kapisanan-hint" aria-live="polite">Enter a date of birth or select a Kapisanan.</span>
                <label class="field-label" for="mk-married">Marital status</label>
                <select id="mk-married" name="is_married">
                    <option value="0" selected>Not Married</option>
                    <option value="1">Married</option>
                </select>
                <label class="field-label" for="mk-birth">Date of birth <span class="modal-sub">(optional)</span></label>
                <input id="mk-birth" name="date_of_birth" type="date" max="{{ now()->toDateString() }}">
                <label class="field-label" for="mk-baptism">Date of baptism <span class="modal-sub">(optional)</span></label>
                <input id="mk-baptism" name="date_of_baptism" type="date" max="{{ now()->toDateString() }}">
                <label class="field-label" for="mk-evangelist">Evangelist <span class="modal-sub">(optional)</span></label>
                <input id="mk-evangelist" name="evangelist" maxlength="150">
            </div>
            <p class="modal-warn" id="add-mk-error" role="alert" hidden></p>
            <div class="modal-actions">
                <button type="button" class="btn" data-close>Cancel</button>
                <button type="submit" class="btn btn-primary" id="add-mk-submit">Add MK member</button>
            </div>
        </form>
    </dialog>

    <dialog class="modal modal-wide" id="delisted-history-dialog" aria-labelledby="delisted-records-heading">
        <div class="modal-body">
            <div class="modal-head">
                <div>
                    <h3 id="delisted-records-heading">Delistment history</h3>
                    <p class="modal-sub">Sensitive, read-only history of delisted members and reasons.</p>
                </div>
                <button type="button" class="modal-close" data-close aria-label="Close">
                    <svg viewBox="0 0 16 16" aria-hidden="true"><path d="m4 4 8 8M12 4l-8 8"/></svg>
                </button>
            </div>
            <div class="table-scroll history-scroll">
                <table class="member-table history-table">
                    <thead>
                        <tr>
                            <th scope="col">Name</th>
                            <th scope="col">Registry</th>
                            <th scope="col">Date of delistment</th>
                            <th scope="col">Reason</th>
                            <th scope="col">Delisted by</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($delistedRecords as $history)
                            <tr>
                                <td>{{ $history->record?->full_name ?: 'Deleted record' }}</td>
                                <td>{{ $history->record?->registry ?: '—' }}</td>
                                <td>{{ $history->delisted_at?->timezone('Asia/Manila')->format('M d, Y h:i A') ?: '—' }}</td>
                                <td>{{ $history->reason }}</td>
                                <td>{{ $history->delisted_by }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5">No delistment history has been recorded yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="modal-actions">
                <a class="btn" href="{{ route('de-listment.history.download') }}">Download history (DOCX)</a>
                <button type="button" class="btn" data-close>Close</button>
            </div>
        </div>
    </dialog>

    <section class="delist-stats" aria-label="Summary">
        <button type="button" class="delist-stat stat-button" id="total-card" aria-haspopup="dialog">
            <span class="stat-label">Total Entry (MS + UWP + MK)</span>
            <strong id="member-total">0</strong>
            <span class="stat-note">Click to see breakdown</span>
        </button>
        <article class="delist-stat">
            <span class="stat-label">Created &amp; reported</span>
            <strong id="reported-total">0</strong>
            <span class="stat-note" id="reported-note">0% of eligibles have been reported (R2-10)</span>
            <span class="stat-bar" aria-hidden="true"><span id="reported-bar"></span></span>
        </article>
        <button type="button" class="delist-stat stat-button stat-action" id="pending-card" aria-label="Show eligible members awaiting R2-10">
            <span class="stat-label">Eligible, awaiting R2-10</span>
            <strong id="pending-total">0</strong>
            <span class="stat-note">Click to review these members</span>
        </button>
    </section>

    <div class="chart-grid">
        <section class="delist-panel chart-panel" aria-labelledby="eligibility-heading">
            <div class="panel-heading">
                <div>
                    <h2 id="eligibility-heading">R2-10 eligibility</h2>
                    <p id="chart-description">Eligible and not eligible members. Click a chart slice or label to see names.</p>
                </div>
                <div class="segmented" role="group" aria-label="Chart detail">
                    <button type="button" class="seg-btn is-active" data-view="overview" aria-pressed="true">Overview</button>
                    <button type="button" class="seg-btn" data-view="detailed" aria-pressed="false">Detailed</button>
                </div>
            </div>
            <div class="chart-content">
                <div class="donut-wrap">
                    <canvas id="eligibility-chart" role="img" aria-label="Eligibility chart"></canvas>
                </div>
                <div class="chart-legend" id="chart-legend" aria-live="polite"></div>
            </div>
        </section>

        <section class="delist-panel chart-panel" aria-labelledby="progress-heading">
            <div class="panel-heading">
                <div>
                    <h2 id="progress-heading">Report progress by kalagayan</h2>
                    <p>Created &amp; reported versus not yet done. Select a bar segment to see names.</p>
                </div>
            </div>
            <div class="bar-wrap">
                <canvas id="progress-chart" role="img" aria-label="Report progress by kalagayan"></canvas>
            </div>
            <div class="mini-legend">
                <span class="mini-item"><i style="background: #17875b"></i>Created &amp; reported</span>
                <span class="mini-item"><i style="background: var(--pending)"></i>Not yet done</span>
            </div>
        </section>
    </div>

    <section class="delist-panel table-panel" aria-labelledby="members-heading">
        <div class="panel-heading table-heading">
            <div>
                <h2 id="members-heading">R2-10 report status</h2>
                <p>Only eligible members can be marked as created &amp; reported. This can't be undone.</p>
            </div>
            <span class="record-count" id="record-count">0 members</span>
        </div>

        <div class="table-toolbar">
            <label class="search-field">
                <svg viewBox="0 0 20 20" aria-hidden="true"><circle cx="9" cy="9" r="5.5"/><path d="m13.5 13.5 3.5 3.5"/></svg>
                <input type="search" id="member-search" placeholder="Search members" autocomplete="off" aria-label="Search members">
            </label>
            <div class="filter-group" role="group" aria-label="Filter by eligibility">
                <button type="button" class="chip is-active" data-filter="all">All</button>
                <button type="button" class="chip" data-filter="eligible">Eligible</button>
                <button type="button" class="chip" data-filter="not-eligible">Not eligible</button>
            </div>
            <div class="filter-group" role="group" aria-label="Filter by status">
                <button type="button" class="chip is-active" data-status="all">Any status</button>
                <button type="button" class="chip" data-status="pending">Not yet done</button>
                <button type="button" class="chip" data-status="done">Reported</button>
            </div>
            <select id="kalagayan-filter" class="select-field" aria-label="Filter by kalagayan">
                <option value="all">All kalagayan</option>
                <option value="MS">MS</option>
                <option value="UWP">UWP</option>
                <option value="MK">MK</option>
            </select>
            <div class="filter-group" role="group" aria-label="Filter by age in system">
                <button type="button" class="chip is-active" data-age="all">Any age</button>
                <button type="button" class="chip" data-age="under">Under 1 year</button>
                <button type="button" class="chip" data-age="over">1 year or more</button>
            </div>
            <select id="age-sort" class="select-field" aria-label="Sort by age in system">
                <option value="">Sort by age</option>
                <option value="newest">Newest first</option>
                <option value="longest">Longest first</option>
            </select>
        </div>

        <div class="table-scroll">
            <table class="member-table">
                <thead>
                    <tr>
                        <th scope="col" aria-sort="none"><button type="button" class="sort-btn" data-sort="name">Name <i></i></button></th>
                        <th scope="col" aria-sort="none"><button type="button" class="sort-btn" data-sort="kalagayan">Kalagayan <i></i></button></th>
                        <th scope="col" aria-sort="none"><button type="button" class="sort-btn" data-sort="eligible">R2-10 eligibility <i></i></button></th>
                        <th scope="col" aria-sort="none"><button type="button" class="sort-btn" data-sort="reported">Status <i></i></button></th>
                        <th scope="col" aria-sort="none"><button type="button" class="sort-btn" data-sort="age">Age in system <i></i></button></th>
                        <th scope="col">Delist MK</th>
                    </tr>
                </thead>
                <tbody id="member-rows"></tbody>
            </table>
            <div class="empty-state" id="empty-state" hidden>
                <strong>No members match</strong>
                <span>Try a different search or clear a filter.</span>
                <button type="button" class="chip" id="clear-filters">Clear filters</button>
            </div>
        </div>

        <div class="table-footer">
            <span class="page-info" id="page-info"></span>
            <div class="pager">
                <button type="button" class="pager-btn" id="prev-page">Previous</button>
                <span id="page-label" class="page-label"></span>
                <button type="button" class="pager-btn" id="next-page">Next</button>
            </div>
        </div>
    </section>

    <dialog class="modal" id="delist-dialog" aria-labelledby="delist-title">
        <form class="modal-body" id="delist-form">
            <div class="modal-head">
                <div>
                    <h3 id="delist-title">Delist MK member</h3>
                    <p class="modal-sub" id="delist-member"></p>
                </div>
                <button type="button" class="modal-close" data-close aria-label="Close">
                    <svg viewBox="0 0 16 16" aria-hidden="true"><path d="m4 4 8 8M12 4l-8 8"/></svg>
                </button>
            </div>
            <p class="modal-lead">This changes the member's status to INACTIVE and records who delisted them, the date, and the reason.</p>
            <label class="field-label" for="delist-reason">Reason for delistment</label>
            <textarea id="delist-reason" rows="4" maxlength="2000" required></textarea>
            <p class="modal-warn" id="delist-error" role="alert"></p>
            <div class="modal-actions">
                <button type="button" class="btn" data-close>Cancel</button>
                <button type="submit" class="btn btn-primary" id="delist-submit">Confirm delistment</button>
            </div>
        </form>
    </dialog>

    <!-- Entry breakdown popup -->
    <dialog class="modal" id="breakdown-dialog" aria-labelledby="breakdown-title">
        <div class="modal-body">
            <div class="modal-head">
                <h3 id="breakdown-title">Entry breakdown</h3>
                <button type="button" class="modal-close" data-close aria-label="Close">
                    <svg viewBox="0 0 16 16" aria-hidden="true"><path d="m4 4 8 8M12 4l-8 8"/></svg>
                </button>
            </div>
            <p class="modal-lead" id="breakdown-summary"></p>
            <div class="breakdown-rows" id="breakdown-rows"></div>
            <div class="modal-actions">
                <button type="button" class="btn" data-close>Close</button>
            </div>
        </div>
    </dialog>

    <!-- R2-10 report form -->
    <dialog class="modal" id="report-dialog" aria-labelledby="report-title">
        <form class="modal-body" id="report-form" novalidate>
            <div class="modal-head">
                <div>
                    <h3 id="report-title">Mark R2-10 as created &amp; reported</h3>
                    <p class="modal-sub" id="report-member"></p>
                </div>
                <button type="button" class="modal-close" data-close aria-label="Close">
                    <svg viewBox="0 0 16 16" aria-hidden="true"><path d="m4 4 8 8M12 4l-8 8"/></svg>
                </button>
            </div>

            <label class="field-label" for="report-note">Brief description of R2-10 endorsement</label>
            <textarea id="report-note" rows="4" maxlength="500" placeholder="Briefly describe what was endorsed in this R2-10."></textarea>
            <span class="field-count" id="report-count">0 / 500</span>

            <label class="check-row" id="one-year-row" for="one-year-confirmed" hidden>
                <input type="checkbox" id="one-year-confirmed">
                <span>I confirm this MS/UWP member has already been in this status for at least 1 year. This will be saved as the endorsement reason.</span>
            </label>
            <p class="modal-warn" id="mk-reason-note" hidden>MK is sensitive: a specific brief description is required; the one-year confirmation does not apply.</p>

            <label class="check-row" for="report-assure">
                <input type="checkbox" id="report-assure">
                <span>I, Secretary for LSP of the locale, assure that the sensitive information I have endorsed and submitted is true and correct, with no errors or omissions.</span>
            </label>

            <p class="modal-warn">Once confirmed, this can't be undone.</p>

            <div class="modal-actions">
                <button type="button" class="btn" data-close>Cancel</button>
                <button type="submit" class="btn btn-primary" id="report-submit" disabled>Confirm &amp; mark as reported</button>
            </div>
        </form>
    </dialog>

    <dialog class="modal modal-wide" id="chart-members-dialog" aria-labelledby="chart-members-title">
        <div class="modal-body">
            <div class="modal-head">
                <div>
                    <h3 id="chart-members-title">Matching records</h3>
                    <p class="modal-sub" id="chart-members-sub"></p>
                </div>
                <button type="button" class="modal-close" data-close aria-label="Close">
                    <svg viewBox="0 0 16 16" aria-hidden="true"><path d="m4 4 8 8M12 4l-8 8"/></svg>
                </button>
            </div>
            <div class="table-scroll">
                <table class="member-table">
                    <thead><tr><th scope="col">Name</th><th scope="col">Kalagayan</th><th scope="col">Eligibility</th><th scope="col">Report status</th></tr></thead>
                    <tbody id="chart-members-rows"></tbody>
                </table>
                <div class="empty-state" id="chart-members-empty" hidden>
                    <strong>No matching records</strong>
                    <span>There are no records in this chart category.</span>
                </div>
            </div>
            <div class="modal-actions">
                <a class="btn btn-primary" id="chart-members-export" href="#" download>Export DOCX</a>
                <button type="button" class="btn" data-close>Close</button>
            </div>
        </div>
    </dialog>

    <!-- Entries with missing data -->
    <dialog class="modal modal-wide" id="missing-dialog" aria-labelledby="missing-title">
        <div class="modal-body">
            <div class="modal-head">
                <div>
                    <h3 id="missing-title">Entries with missing data</h3>
                    <p class="modal-sub" id="missing-sub"></p>
                </div>
                <button type="button" class="modal-close" data-close aria-label="Close">
                    <svg viewBox="0 0 16 16" aria-hidden="true"><path d="m4 4 8 8M12 4l-8 8"/></svg>
                </button>
            </div>
            <p class="modal-lead">This list shows active MS, UWP, or MK records that have no baptism date recorded. Add the date here to update the member record.</p>

            <div class="table-scroll missing-scroll">
                <table class="member-table missing-table" id="missing-table">
                    <thead>
                        <tr>
                            <th scope="col">Name</th>
                            <th scope="col">Registry number</th>
                            <th scope="col">Date of baptism</th>
                        </tr>
                    </thead>
                    <tbody id="missing-rows"></tbody>
                </table>
                <div class="empty-state" id="missing-empty" hidden>
                    <strong>No baptism dates are missing</strong>
                    <span>Every applicable active record has a baptism date in the Records table.</span>
                </div>
            </div>

            <div class="modal-actions">
                <span class="save-hint" id="missing-hint"></span>
                <button type="button" class="btn" data-close>Close</button>
                <button type="button" class="btn btn-primary" id="missing-save" disabled>Save changes</button>
            </div>
        </div>
    </dialog>

    <div class="toast" id="toast" role="status" aria-live="polite"></div>
</div>
@endsection

@push('styles')
<style>
    .delist-page {
        --delist-ink: var(--text);
        --delist-muted: var(--text-muted);
        --delist-border: var(--border);
        --delist-surface: var(--surface);
        --ok: #17875b;
        --ok-soft: #e9f7ef;
        --bad: #a54835;
        --bad-soft: #fff0ed;
        --warn: #9a6a0a;
        --warn-soft: #fff5dc;
        --k-ms: #17875b;
        --k-uwp: #e8781a;
        --k-mk: #e2ae00;
        --pending: #dc4c4c;
        max-width: 1180px;
        margin: 0 auto;
    }
    .delist-heading {
        position: relative; padding: clamp(1.3rem, 3vw, 2rem); overflow: hidden;
        border: 1px solid var(--delist-border);
        border-radius: 14px; background: var(--delist-surface);
        box-shadow: 0 12px 32px rgba(16, 24, 40, .06);
    }
    .delist-heading > * { position: relative; z-index: 1; }
    .delist-eyebrow { text-transform: uppercase; letter-spacing: .12em; }
    .delist-heading h1 { font-size: clamp(2rem, 4vw, 2.8rem); }
    html[data-theme="dark"] .delist-page {
        --ok: #75d5a2; --ok-soft: #16372a;
        --bad: #ff9b88; --bad-soft: #3a2424;
        --warn: #f2c66d;
        --warn-soft: #3a2f16;
    }

    .delist-heading,
    .panel-heading {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 1.25rem;
    }
    .delist-heading { margin-bottom: 1.5rem; }
    .delist-eyebrow { margin: 0 0 .45rem; color: var(--accent); font-size: .82rem; font-weight: 700; }
    .delist-heading h1 { margin: 0; color: var(--delist-ink); font-size: clamp(1.8rem, 4vw, 2.8rem); letter-spacing: -.04em; }
    .delist-subtitle,
    .panel-heading p { margin: .45rem 0 0; color: var(--delist-muted); font-size: .92rem; line-height: 1.55; }

    /* Stats */
    .delist-stats { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 1rem; margin-bottom: 1rem; }
    .delist-stat,
    .delist-panel {
        border: 1px solid var(--delist-border);
        border-radius: 14px;
        background: var(--delist-surface);
        box-shadow: 0 1px 3px rgba(16, 24, 40, .04);
        transition: border-color .18s ease;
    }
    .delist-stat:hover, .delist-panel:hover { border-color: color-mix(in srgb, var(--accent) 24%, var(--delist-border)); }
    .delist-stat { display: flex; flex-direction: column; padding: 1.1rem 1.25rem; }
    .stat-label { color: var(--delist-muted); font-size: .82rem; font-weight: 600; }
    .delist-stat strong { margin: .35rem 0 .2rem; color: var(--delist-ink); font-size: 1.8rem; letter-spacing: -.04em; font-variant-numeric: tabular-nums; }
    .delist-stat:not(.stat-button) #reported-total { color: var(--ok); }
    .stat-note { color: var(--delist-muted); font-size: .78rem; }
    .stat-bar { display: block; height: 5px; margin-top: .65rem; border-radius: 99px; background: var(--delist-border); overflow: hidden; }
    .stat-bar span { display: block; height: 100%; width: 0; border-radius: inherit; background: var(--ok); transition: width .7s cubic-bezier(.2,.8,.2,1); }

    .stat-button { width: 100%; font: inherit; text-align: left; color: inherit; cursor: pointer; transition: border-color .15s ease, transform .1s ease; }
    .stat-button:hover { border-color: var(--accent); }
    .stat-button:active { transform: scale(.99); }
    .stat-button .stat-note { color: var(--accent); font-weight: 600; }
    .stat-action { border-color: color-mix(in srgb, var(--warn) 35%, var(--delist-border)); }
    .stat-action strong, .stat-action .stat-note { color: var(--warn); }

    /* Panels */
    .delist-panel { position: relative; padding: 1.35rem; margin-bottom: 1rem; overflow: hidden; }
    .panel-heading { align-items: center; }
    .panel-heading h2 { margin: 0; color: var(--delist-ink); font-size: 1.08rem; letter-spacing: -.02em; }
    .panel-heading p { font-size: .83rem; }

    .chart-grid { display: grid; grid-template-columns: minmax(0, 1.1fr) minmax(0, 1fr); gap: 1rem; margin-bottom: 1rem; }
    .chart-grid .delist-panel { margin-bottom: 0; }

    .segmented { display: inline-flex; padding: 3px; border: 1px solid var(--delist-border); border-radius: 10px; background: var(--bg); flex: 0 0 auto; }
    .seg-btn {
        padding: .42rem .75rem; border: 0; border-radius: 7px; background: transparent;
        color: var(--delist-muted); font: inherit; font-size: .78rem; font-weight: 650; cursor: pointer;
        transition: background .2s ease, color .2s ease, box-shadow .2s ease;
    }
    .seg-btn.is-active { background: var(--delist-surface); color: var(--delist-ink); box-shadow: 0 1px 3px rgba(16,24,40,.14); }

    .seg-btn:focus-visible, .chip:focus-visible, .pager-btn:focus-visible, .sort-btn:focus-visible, .status-btn:focus-visible,
    .stat-button:focus-visible, .btn:focus-visible, .modal-close:focus-visible,
    .search-field input:focus-visible, .select-field:focus-visible, .modal textarea:focus-visible, .check-row input:focus-visible {
        outline: 3px solid color-mix(in srgb, var(--accent) 35%, transparent);
        outline-offset: 2px;
    }

    .chart-content { display: grid; grid-template-columns: minmax(190px, 250px) minmax(0, 1fr); align-items: center; gap: clamp(1.25rem, 4vw, 3rem); margin: 1.25rem 0 .25rem; }
    .donut-wrap { position: relative; width: 100%; max-width: 250px; aspect-ratio: 1; justify-self: center; padding: .35rem; border-radius: 50%; }
    .bar-wrap { position: relative; height: 250px; margin-top: 1.25rem; padding: .65rem .8rem .25rem; border-radius: 14px; background: var(--bg); }
    .chart-legend { display: grid; gap: .1rem; }
    .legend-row {
        width: 100%; border: 0; background: transparent; font: inherit; text-align: left;
        display: grid; grid-template-columns: minmax(0, 1fr) auto; align-items: center; gap: 1rem;
        padding: .7rem .65rem; margin: 0; border-radius: 10px; cursor: pointer; transition: background .15s ease, transform .15s ease;
    }
    .legend-row:hover { background: var(--bg); transform: translateX(2px); }
    .legend-row:focus-visible { outline: 3px solid color-mix(in srgb, var(--accent) 35%, transparent); outline-offset: 2px; }
    .legend-label { display: flex; align-items: center; gap: .65rem; color: var(--delist-ink); font-size: .84rem; }
    .legend-swatch { width: 11px; height: 11px; flex: 0 0 auto; border-radius: 3px; }
    .legend-value { color: var(--delist-muted); font-size: .81rem; font-variant-numeric: tabular-nums; white-space: nowrap; }
    .legend-value strong { margin-right: .35rem; color: var(--delist-ink); font-size: .87rem; }

    .mini-legend { display: flex; flex-wrap: wrap; gap: .5rem 1.5rem; justify-content: center; margin-top: .9rem; color: var(--delist-muted); font-size: .76rem; }
    .mini-group { display: inline-flex; align-items: center; flex-wrap: wrap; gap: .3rem .7rem; }
    .mini-item { display: inline-flex; align-items: center; gap: .35rem; color: var(--delist-ink); font-weight: 600; }
    .mini-item i { width: 10px; height: 10px; border-radius: 3px; display: inline-block; }

    /* Table */
    .table-panel { padding-bottom: 1rem; }
    .table-heading { margin-bottom: 1rem; }
    .record-count { color: var(--delist-muted); font-size: .78rem; white-space: nowrap; }

    .table-toolbar { display: flex; flex-wrap: wrap; align-items: center; gap: .6rem; margin-bottom: 1rem; }
    .search-field { position: relative; flex: 1 1 200px; max-width: 260px; }
    .search-field svg { position: absolute; left: .7rem; top: 50%; width: 16px; height: 16px; transform: translateY(-50%); fill: none; stroke: var(--delist-muted); stroke-width: 1.7; stroke-linecap: round; pointer-events: none; }
    .search-field input {
        width: 100%; padding: .58rem .8rem .58rem 2.15rem; border: 1px solid var(--delist-border); border-radius: 9px;
        background: var(--bg); color: var(--delist-ink); font: inherit; font-size: .82rem;
    }
    .filter-group { display: inline-flex; gap: .3rem; padding: 3px; border: 1px solid var(--delist-border); border-radius: 10px; background: var(--bg); }
    .chip {
        padding: .38rem .7rem; border: 0; border-radius: 7px; background: transparent; color: var(--delist-muted);
        font: inherit; font-size: .76rem; font-weight: 650; cursor: pointer; transition: background .2s ease, color .2s ease;
    }
    .chip:hover { color: var(--delist-ink); }
    .chip.is-active { background: var(--delist-surface); color: var(--delist-ink); box-shadow: 0 1px 3px rgba(16,24,40,.14); }
    .select-field {
        padding: .55rem .7rem; border: 1px solid var(--delist-border); border-radius: 9px; background: var(--bg);
        color: var(--delist-ink); font: inherit; font-size: .78rem; font-weight: 600;
    }

    .table-scroll { overflow-x: auto; border: 1px solid var(--delist-border); border-radius: 12px; }
    .member-table { width: 100%; border-collapse: collapse; text-align: left; }
    .member-table th { padding: .2rem .5rem; border-bottom: 1px solid var(--delist-border); background: var(--bg); white-space: nowrap; }
    .sort-btn {
        display: inline-flex; align-items: center; gap: .4rem; padding: .6rem .5rem; border: 0; border-radius: 6px;
        background: transparent; color: var(--delist-muted); font: inherit; font-size: .76rem; font-weight: 700; cursor: pointer;
    }
    .sort-btn:hover { color: var(--delist-ink); }
    .sort-btn i { width: 8px; height: 12px; position: relative; opacity: .45; }
    .sort-btn i::before, .sort-btn i::after { content: ""; position: absolute; left: 0; border: 4px solid transparent; }
    .sort-btn i::before { top: -1px; border-bottom-color: currentColor; border-top: 0; }
    .sort-btn i::after { bottom: -1px; border-top-color: currentColor; border-bottom: 0; }
    th[aria-sort="ascending"] .sort-btn, th[aria-sort="descending"] .sort-btn { color: var(--delist-ink); }
    th[aria-sort="ascending"] .sort-btn i, th[aria-sort="descending"] .sort-btn i { opacity: 1; }
    th[aria-sort="ascending"] .sort-btn i::after { opacity: .2; }
    th[aria-sort="descending"] .sort-btn i::before { opacity: .2; }

    .member-table td { padding: .8rem 1rem; border-bottom: 1px solid var(--delist-border); color: var(--delist-ink); font-size: .84rem; }
    .member-table tbody tr { transition: background .15s ease; }
    .member-table tbody tr:hover { background: var(--bg); }
    .member-table tbody tr:last-child td { border-bottom: 0; }
    .member-cell { display: flex; align-items: center; gap: .75rem; font-weight: 650; }
    .avatar { display: grid; place-items: center; width: 32px; height: 32px; flex: 0 0 auto; border-radius: 50%; color: #fff; font-size: .7rem; font-weight: 700; }

    .table-badge { display: inline-flex; align-items: center; gap: .38rem; padding: .34rem .58rem; border-radius: 999px; font-size: .72rem; font-weight: 650; white-space: nowrap; }
    .table-badge::before { width: 6px; height: 6px; border-radius: 50%; background: currentColor; content: ""; }
    .badge-eligible { color: var(--ok); background: var(--ok-soft); }
    .badge-not-eligible { color: var(--bad); background: var(--bad-soft); }

    .kalagayan-badge { display: inline-flex; min-width: 3.3rem; justify-content: center; padding: .34rem .58rem; border-radius: 7px; font-size: .72rem; font-weight: 700; }
    .k-MS { color: #146b47; background: #e2f5eb; }
    .k-UWP { color: #b35208; background: #ffeedb; }
    .k-MK { color: #7d5f00; background: #fff4c7; }
    html[data-theme="dark"] .k-MS { color: #7fdcae; background: #16372a; }
    html[data-theme="dark"] .k-UWP { color: #ffb36b; background: #3d2a16; }
    html[data-theme="dark"] .k-MK { color: #f5d24a; background: #3a3213; }

    .status-btn {
        display: inline-flex; align-items: center; gap: .5rem; min-width: 172px; padding: .4rem .75rem;
        border: 1px dashed var(--delist-border); border-radius: 999px; background: var(--delist-surface);
        color: var(--warn); font: inherit; font-size: .74rem; font-weight: 650; white-space: nowrap; cursor: pointer;
        transition: border-color .2s ease, background .2s ease, color .2s ease, transform .1s ease;
    }
    .status-btn::before { content: ""; width: 12px; height: 12px; border: 2px solid currentColor; border-radius: 50%; flex: 0 0 auto; }
    .status-btn .on-hover { display: none; }
    .status-btn:hover { border-style: solid; border-color: var(--ok); background: var(--ok-soft); color: var(--ok); }
    .status-btn:hover .on-idle { display: none; }
    .status-btn:hover .on-hover { display: inline; }
    .status-btn:active { transform: scale(.97); }
    .status-btn.is-locked { color: var(--delist-muted); cursor: not-allowed; }
    .status-btn.is-locked::before { display: none; }
    .status-btn.is-locked:hover { border-style: dashed; border-color: var(--delist-border); background: var(--delist-surface); color: var(--delist-muted); }
    .status-btn.is-locked:active { transform: none; }
    .status-btn svg { width: 13px; height: 13px; flex: 0 0 auto; fill: none; stroke: currentColor; stroke-width: 1.6; stroke-linecap: round; stroke-linejoin: round; }
    .delist-action { padding: .42rem .7rem; border: 1px solid var(--bad); border-radius: 8px; background: var(--bad-soft); color: var(--bad); font: inherit; font-size: .8rem; font-weight: 750; cursor: pointer; }
    .delist-action:hover { background: color-mix(in srgb, var(--bad) 14%, var(--delist-surface)); }
    .delist-action:focus-visible { outline: 3px solid color-mix(in srgb, var(--bad) 35%, transparent); outline-offset: 2px; }

    .status-done {
        display: inline-flex; align-items: center; gap: .45rem; min-width: 172px; padding: .4rem .75rem;
        border-radius: 999px; color: var(--ok); background: var(--ok-soft); font-size: .74rem; font-weight: 650; white-space: nowrap;
    }
    .status-done svg { width: 14px; height: 14px; fill: none; stroke: currentColor; stroke-width: 2.4; stroke-linecap: round; stroke-linejoin: round; }
    .status-done.just-done { animation: pop .45s cubic-bezier(.2,1.4,.4,1); }
    .status-done.just-done svg path { stroke-dasharray: 20; stroke-dashoffset: 20; animation: draw .4s .1s ease forwards; }
    @keyframes pop { 0% { transform: scale(.85); } 100% { transform: scale(1); } }
    @keyframes draw { to { stroke-dashoffset: 0; } }

    .age-value { font-weight: 600; font-variant-numeric: tabular-nums; white-space: nowrap; }
    .age-empty { color: var(--delist-muted); }

    .empty-state { display: grid; justify-items: center; gap: .4rem; padding: 2.5rem 1rem; color: var(--delist-muted); font-size: .83rem; text-align: center; }
    .empty-state strong { color: var(--delist-ink); font-size: .95rem; }
    .empty-state .chip { border: 1px solid var(--delist-border); margin-top: .4rem; }
    .empty-state[hidden] { display: none; }

    .table-footer { display: flex; align-items: center; justify-content: space-between; gap: 1rem; margin-top: .9rem; }
    .page-info, .page-label { color: var(--delist-muted); font-size: .78rem; }
    .pager { display: flex; align-items: center; gap: .6rem; }
    .pager-btn {
        padding: .45rem .8rem; border: 1px solid var(--delist-border); border-radius: 8px; background: var(--delist-surface);
        color: var(--delist-ink); font: inherit; font-size: .78rem; font-weight: 650; cursor: pointer;
    }
    .pager-btn:hover:not(:disabled) { border-color: var(--accent); }
    .pager-btn:disabled { opacity: .4; cursor: not-allowed; }

    /* Dialogs */
    .modal {
        width: min(92vw, 460px); padding: 0; border: 1px solid var(--delist-border); border-radius: 16px;
        background: var(--delist-surface); color: var(--delist-ink); box-shadow: 0 24px 60px rgba(16, 24, 40, .28);
    }
    .modal::backdrop { background: rgba(15, 23, 42, .5); }
    .modal[open] { animation: modal-in .22s cubic-bezier(.2,.9,.3,1); }
    @keyframes modal-in { from { opacity: 0; transform: translateY(10px) scale(.98); } to { opacity: 1; transform: none; } }
    .modal-body { display: grid; gap: .85rem; padding: 1.4rem; margin: 0; }
    .modal-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 1rem; }
    .modal-head h3 { margin: 0; color: var(--delist-ink); font-size: 1.08rem; letter-spacing: -.02em; }
    .modal-sub { margin: .3rem 0 0; color: var(--delist-muted); font-size: .83rem; }
    .modal-lead { margin: 0; color: var(--delist-muted); font-size: .9rem; line-height: 1.55; }
    .modal-close {
        display: grid; place-items: center; width: 30px; height: 30px; flex: 0 0 auto; padding: 0;
        border: 0; border-radius: 8px; background: transparent; color: var(--delist-muted); cursor: pointer;
    }
    .modal-close:hover { background: var(--bg); color: var(--delist-ink); }
    .modal-close svg { width: 16px; height: 16px; fill: none; stroke: currentColor; stroke-width: 1.8; stroke-linecap: round; }

    .field-label { color: var(--delist-ink); font-size: .82rem; font-weight: 650; }
    .mk-add-grid { display: grid; grid-template-columns: minmax(8rem, .65fr) minmax(12rem, 1fr); align-items: center; gap: .65rem .9rem; }
    .mk-add-grid input, .mk-add-grid select {
        width: 100%; min-width: 0; padding: .65rem .75rem; border: 1px solid var(--delist-border); border-radius: 9px;
        background: var(--bg); color: var(--delist-ink); font: inherit; font-size: .85rem;
    }
    .mk-add-grid input:focus-visible, .mk-add-grid select:focus-visible { outline: 2px solid var(--accent); outline-offset: 2px; }
    .mk-rfid { grid-column: 2; padding: .6rem; }
    @media(max-width:520px) { .mk-add-grid { grid-template-columns: 1fr; gap: .35rem; } .mk-rfid { grid-column: 1; margin-bottom: .4rem; } }
    .modal textarea {
        width: 100%; min-height: 96px; padding: .7rem .8rem; resize: vertical; border: 1px solid var(--delist-border);
        border-radius: 10px; background: var(--bg); color: var(--delist-ink); font: inherit; font-size: .85rem; line-height: 1.5;
    }
    .field-count { justify-self: end; margin-top: -.55rem; color: var(--delist-muted); font-size: .72rem; }
    .check-row {
        display: flex; align-items: flex-start; gap: .7rem; padding: .8rem; border: 1px solid var(--delist-border);
        border-radius: 10px; background: var(--bg); font-size: .8rem; line-height: 1.5; cursor: pointer;
    }
    .check-row input { flex: 0 0 auto; width: 16px; height: 16px; margin-top: .2rem; accent-color: var(--accent); }
    .modal-warn { margin: 0; color: var(--warn); font-size: .78rem; font-weight: 600; }
    .modal-actions { display: flex; justify-content: flex-end; gap: .6rem; margin-top: .25rem; }
    .btn {
        padding: .6rem .95rem; border: 1px solid var(--delist-border); border-radius: 9px; background: var(--delist-surface);
        color: var(--delist-ink); font: inherit; font-size: .82rem; font-weight: 650; cursor: pointer;
    }
    .btn:hover:not(:disabled) { border-color: var(--accent); }
    .btn-primary { background: #17875b; border-color: #17875b; color: #fff; }
    .btn-primary:hover:not(:disabled) { border-color: #17875b; filter: brightness(1.08); }
    .btn:disabled { opacity: .45; cursor: not-allowed; }

    .breakdown-rows { display: grid; gap: .95rem; margin: .25rem 0; }
    .bd-top { display: flex; align-items: center; justify-content: space-between; gap: 1rem; margin-bottom: .4rem; }
    .bd-bar { height: 8px; border-radius: 99px; background: var(--delist-border); overflow: hidden; }
    .bd-bar span { display: block; width: 0; height: 100%; border-radius: inherit; transition: width .7s cubic-bezier(.2,.8,.2,1); }

    .toast {
        position: fixed; left: 50%; bottom: max(1.5rem, env(safe-area-inset-bottom)); width: max-content; max-width: calc(100vw - 2rem); padding: .65rem 1rem; border-radius: 10px;
        background: var(--delist-ink); color: var(--delist-surface); font-size: .8rem; font-weight: 600;
        overflow-wrap: anywhere; text-align: center; opacity: 0; pointer-events: none; transform: translate(-50%, 12px); transition: opacity .25s ease, transform .25s ease; z-index: 2000;
    }
    .toast.show { opacity: 1; transform: translate(-50%, 0); }

    /* Viewer and missing-data actions */
    .delist-tools { display: flex; flex-wrap: wrap; justify-content: flex-end; gap: .6rem; }
    .missing-btn {
        display: inline-flex; align-items: center; gap: .55rem; flex: 0 0 auto; padding: .6rem .85rem;
        border: 1px solid var(--delist-border); border-radius: 10px; background: var(--delist-surface);
        color: var(--delist-ink); font: inherit; font-size: .82rem; font-weight: 650; cursor: pointer;
        transition: border-color .15s ease, background .15s ease;
    }
    .missing-btn:hover { border-color: var(--warn); background: var(--warn-soft); }
    .missing-btn:focus-visible { outline: 3px solid color-mix(in srgb, var(--accent) 35%, transparent); outline-offset: 2px; }
    .missing-btn svg { width: 16px; height: 16px; fill: none; stroke: var(--warn); stroke-width: 1.6; stroke-linecap: round; stroke-linejoin: round; }
    .history-btn {
        padding: .6rem .85rem; border: 1px solid var(--delist-border); border-radius: 10px; background: transparent;
        color: var(--delist-muted); font: inherit; font-size: .82rem; font-weight: 600; cursor: pointer;
        transition: border-color .15s ease, color .15s ease, background .15s ease;
    }
    .history-btn:hover { border-color: var(--accent); background: var(--delist-surface); color: var(--delist-ink); }
    .history-btn:focus-visible { outline: 3px solid color-mix(in srgb, var(--accent) 35%, transparent); outline-offset: 2px; }
    .missing-count {
        min-width: 1.35rem; padding: .05rem .42rem; border-radius: 99px; background: var(--warn-soft); color: var(--warn);
        font-size: .72rem; font-weight: 700; text-align: center; font-variant-numeric: tabular-nums;
    }
    .missing-count[hidden] { display: none; }
    .modal-wide { width: min(96vw, 760px); }
    #delisted-history-dialog .modal-body { max-height: min(86vh, 780px); overflow: auto; }
    #delisted-history-dialog .history-scroll { max-height: min(58vh, 520px); overflow: auto; }
    #delisted-history-dialog .history-table { min-width: 720px; }
    #delisted-history-dialog .history-table thead th { position: sticky; top: 0; z-index: 2; background: var(--delist-surface); }
    #chart-members-dialog { max-height: min(86vh, 780px); }
    #chart-members-dialog .modal-body { max-height: min(86vh, 780px); overflow: auto; }
    #chart-members-dialog .table-scroll { max-height: min(58vh, 520px); overflow: auto; }
    #chart-members-dialog .member-table { min-width: 600px; }
    #chart-members-dialog .member-table thead th { position: sticky; top: 0; z-index: 2; background: var(--delist-surface); }
    #chart-members-dialog .member-table tbody tr { cursor: default; }
    #chart-members-dialog .member-table tbody tr:hover { background: color-mix(in srgb, var(--accent) 7%, var(--delist-surface)); }
    #one-year-row[hidden], #mk-reason-note[hidden] { display: none; }
    .check-row:has(input:checked) { border-color: var(--ok); background: var(--ok-soft); }
    .missing-scroll { max-height: min(50vh, 360px); overflow: auto; }
    .missing-table { min-width: 620px; }
    .missing-table th { position: sticky; top: 0; z-index: 1; padding: .7rem 1rem; color: var(--delist-muted); font-size: .76rem; font-weight: 700; }
    .missing-table tr.is-complete td { background: var(--ok-soft); transition: background .3s ease; }
    .missing-table tr.is-complete .date-input { border-color: var(--ok); background: var(--delist-surface); }
    .registry { color: var(--delist-muted); font-variant-numeric: tabular-nums; white-space: nowrap; }
    .date-input {
        width: 100%; min-width: 150px; padding: .5rem .6rem; border: 1px solid var(--delist-border); border-radius: 8px;
        background: var(--delist-surface); color: var(--delist-ink); font: inherit; font-size: .82rem;
    }
    .date-input.is-missing { border-color: var(--warn); background: var(--warn-soft); }
    .date-input:focus-visible { outline: 3px solid color-mix(in srgb, var(--accent) 35%, transparent); outline-offset: 2px; }
    html[data-theme="dark"] .date-input { color-scheme: dark; }
    .save-hint { margin-right: auto; align-self: center; color: var(--delist-muted); font-size: .78rem; }
    .chart-error { margin: 0; padding: 1rem; color: var(--delist-muted); font-size: .83rem; text-align: center; }

    @media (prefers-reduced-motion: reduce) {
        .delist-page *, .delist-page *::before, .delist-page *::after { animation: none !important; transition: none !important; }
    }

    @media (max-width: 960px) {
        .chart-grid { grid-template-columns: 1fr; }
    }
    @media (max-width: 700px) {
        .delist-stats { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .delist-panel { padding: 1rem; }
        .search-field { max-width: none; flex-basis: 100%; }
    }
    @media (max-width: 520px) {
        .delist-heading { flex-direction: column; gap: .8rem; }
        .delist-stats { grid-template-columns: 1fr; gap: .65rem; }
        .delist-stat { padding: .9rem; }
        .panel-heading { align-items: flex-start; flex-direction: column; }
        .segmented { width: 100%; }
        .seg-btn { flex: 1; }
        .chart-content { grid-template-columns: 1fr; gap: 1rem; }
        .donut-wrap { max-width: 210px; }
        .table-heading { flex-direction: row; }
        .member-table { min-width: 860px; }
        .table-footer { flex-direction: column; align-items: stretch; text-align: center; }
        .pager { justify-content: space-between; }
        .modal-body { padding: 1.1rem; }
    }
</style>
@endpush

@push('scripts')
@vite('resources/js/delistment-charts.js')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const config = document.getElementById('delist-config');
    const members = @json($members);
    const missingRecords = @json($missingRecords);
    const PAGE_SIZE = 20;
    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const Chart = window.Chart;

    /* ---------- Dates ---------- */
    const pad = n => String(n).padStart(2, '0');
    const parseISO = iso => { const [y, m, d] = iso.split('-').map(Number); return new Date(y, m - 1, d); };
    const startOfToday = () => { const n = new Date(); return new Date(n.getFullYear(), n.getMonth(), n.getDate()); };
    const toISO = date => `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
    const plural = (n, word) => `${n} ${word}${n === 1 ? '' : 's'}`;

    function diffYMD(from, to) {
        if (from > to) from = to;
        let y = to.getFullYear() - from.getFullYear();
        let m = to.getMonth() - from.getMonth();
        let d = to.getDate() - from.getDate();
        if (d < 0) { m--; d += new Date(to.getFullYear(), to.getMonth(), 0).getDate(); }
        if (m < 0) { y--; m += 12; }
        return { y, m, d };
    }
    function formatAge(iso) {
        const { y, m, d } = diffYMD(parseISO(iso), startOfToday());
        const parts = [];
        if (y) parts.push(plural(y, 'Year'));
        if (m) parts.push(plural(m, 'Month'));
        if (d) parts.push(plural(d, 'Day'));
        return parts.length ? parts.join(', ') : 'Today';
    }
    const ageYears = member => member.entryDate ? diffYMD(parseISO(member.entryDate), startOfToday()).y : null;
    const ageDays = member => member.entryDate
        ? Math.round((startOfToday() - parseISO(member.entryDate)) / 86400000)
        : null;

    /* ---------- Shared ---------- */
    const css = name => getComputedStyle(document.documentElement).getPropertyValue(name).trim();
    const kalagayanTypes = ['MS', 'UWP', 'MK'];
    const kColors = { MS: '#17875b', UWP: '#e8781a', MK: '#e2ae00' };
    const colors = {
        eligible: '#1677d2', notEligible: '#94a3b8',
        eligibleNotEndorsed: '#12a1a1', eligibleEndorsed: '#17875b',
        notEligibleNotEndorsed: '#e47a5f', notEligibleEndorsed: '#7856b5',
        mk: kColors.MK, done: '#17875b', pending: '#dc4c4c',
    };
    const avatarColors = ['#1677d2', '#17875b', '#7856b5', '#e47a5f', '#c2851a', '#0f8a9d'];
    let total = members.length;

    const toast = document.getElementById('toast');
    let toastTimer;
    function showToast(message) {
        toast.textContent = message;
        toast.classList.add('show');
        clearTimeout(toastTimer);
        toastTimer = setTimeout(() => toast.classList.remove('show'), 2600);
    }

    /* ---------- Stats ---------- */
    function countUp(el, to) {
        const from = Number(el.textContent) || 0;
        if (reduceMotion || from === to) { el.textContent = to; return; }
        const start = performance.now(), duration = 700;
        const tick = now => {
            const t = Math.min((now - start) / duration, 1);
            el.textContent = Math.round(from + (to - from) * (1 - Math.pow(1 - t, 3)));
            if (t < 1) requestAnimationFrame(tick);
        };
        requestAnimationFrame(tick);
    }
    function renderStats() {
        const eligibles = members.filter(m => m.eligible);
        const reported = eligibles.filter(m => m.reported).length;
        const pending = eligibles.length - reported;
        const pct = eligibles.length ? Math.round(reported / eligibles.length * 100) : 0;
        countUp(document.getElementById('member-total'), total);
        countUp(document.getElementById('reported-total'), reported);
        countUp(document.getElementById('pending-total'), pending);
        document.getElementById('reported-note').textContent = `${pct}% of eligibles have been reported (R2-10)`;
        requestAnimationFrame(() => { document.getElementById('reported-bar').style.width = pct + '%'; });
        document.getElementById('record-count').textContent = `${total} members`;
    }

    /* ---------- Dialog helpers ---------- */
    document.querySelectorAll('.modal').forEach(dialog => {
        dialog.querySelectorAll('[data-close]').forEach(btn => btn.addEventListener('click', () => dialog.close()));
        dialog.addEventListener('click', e => { if (e.target === dialog) dialog.close(); }); // backdrop click
    });

    const addMkDialog = document.getElementById('add-mk-dialog');
    const addMkForm = document.getElementById('add-mk-form');
    const addMkSubmit = document.getElementById('add-mk-submit');
    const addMkError = document.getElementById('add-mk-error');
    const mkPurok = document.getElementById('mk-purok');
    const mkGrupo = document.getElementById('mk-grupo');
    const mkKapisanan = document.getElementById('mk-kapisanan');
    const mkMarried = document.getElementById('mk-married');
    const mkBirth = document.getElementById('mk-birth');
    const mkKapisananHint = document.getElementById('mk-kapisanan-hint');
    document.getElementById('add-mk-btn').addEventListener('click', () => {
        addMkForm.reset();
        addMkError.hidden = true;
        addMkError.textContent = '';
        mkGrupo.removeAttribute('max');
        syncMkKapisanan();
        addMkDialog.showModal();
        document.getElementById('mk-full-name').focus();
    });
    mkPurok.addEventListener('change', () => {
        const selected = mkPurok.selectedOptions[0];
        mkGrupo.min = selected?.dataset.start || '1';
        if (selected?.dataset.end) mkGrupo.max = selected.dataset.end;
        else mkGrupo.removeAttribute('max');
    });
    function syncMkKapisanan() {
        const birthDate = mkBirth.value ? new Date(`${mkBirth.value}T00:00:00`) : null;
        if (mkMarried.value === '1') {
            mkKapisanan.value = 'Buklod';
            mkKapisanan.disabled = true;
            mkKapisananHint.textContent = 'Automatically selected: Buklod (married).';
            return;
        }
        if (birthDate && !Number.isNaN(birthDate.getTime())) {
            const today = new Date();
            let age = today.getFullYear() - birthDate.getFullYear();
            if (today.getMonth() < birthDate.getMonth()
                || (today.getMonth() === birthDate.getMonth() && today.getDate() < birthDate.getDate())) age--;
            mkKapisanan.value = age < 18 ? 'Binhi' : 'Kadiwa';
            mkKapisanan.disabled = true;
            mkKapisananHint.textContent = `Automatically selected: ${mkKapisanan.value} based on age ${age}.`;
            return;
        }
        mkKapisanan.disabled = false;
        mkKapisananHint.textContent = 'Enter a date of birth for automatic selection, or choose Kapisanan manually.';
    }
    mkMarried.addEventListener('change', syncMkKapisanan);
    mkBirth.addEventListener('input', syncMkKapisanan);
    mkBirth.addEventListener('change', syncMkKapisanan);
    addMkForm.addEventListener('submit', async event => {
        event.preventDefault();
        if (!addMkForm.reportValidity() || addMkSubmit.disabled) return;

        addMkError.hidden = true;
        addMkError.textContent = '';
        addMkSubmit.disabled = true;
        addMkSubmit.textContent = 'Saving...';
        const body = Object.fromEntries(new FormData(addMkForm).entries());
        body.kapisanan = mkKapisanan.value;
        try {
            const response = await fetch(config.dataset.mkCreateUrl, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': config.dataset.csrf,
                },
                body: JSON.stringify(body),
            });
            const result = await response.json();
            if (!response.ok) {
                const validationMessage = Object.values(result.errors || {}).flat()[0];
                throw new Error(validationMessage || result.message || 'Unable to add this MK member.');
            }

            members.push(result.data);
            total = members.length;
            if (config.dataset.requireBaptismDate === 'true' && !result.data.baptism) {
                missingRecords.push({
                    id: result.data.id,
                    name: result.data.name,
                    registry: result.data.registry || '—',
                    baptism: '',
                });
            }
            addMkDialog.close();
            renderStats();
            renderEligibility();
            renderProgress();
            renderTable();
            renderMissingCount();
            showToast(`${result.data.name} was added as MK.`);
        } catch (error) {
            console.error('MK member could not be added:', error);
            addMkError.textContent = error.message || 'Unable to add this MK member.';
            addMkError.hidden = false;
        } finally {
            addMkSubmit.disabled = false;
            addMkSubmit.textContent = 'Add MK member';
        }
    });

    const delistedHistoryDialog = document.getElementById('delisted-history-dialog');
    document.getElementById('delisted-history-btn').addEventListener('click', () => delistedHistoryDialog.showModal());

    const chartMembersDialog = document.getElementById('chart-members-dialog');
    function showChartMembers(title, subtitle, matchingMembers, exportCategory) {
        document.getElementById('chart-members-title').textContent = title;
        document.getElementById('chart-members-sub').textContent = `${matchingMembers.length} matching ${matchingMembers.length === 1 ? 'record' : 'records'} · ${subtitle}`;
        const exportButton = document.getElementById('chart-members-export');
        exportButton.href = matchingMembers.length
            ? config.dataset.exportUrl.replace('__CATEGORY__', encodeURIComponent(exportCategory))
            : '#';
        exportButton.hidden = matchingMembers.length === 0;
        const rows = matchingMembers.map(member => {
            const row = document.createElement('tr');
            const values = [
                member.name,
                member.kalagayan,
                member.eligible ? 'Eligible' : 'Not eligible',
                member.reported
                    ? 'Created & reported'
                    : (!member.eligible && member.endorsed
                        ? 'Endorsed · Not eligible — review required'
                        : 'Not yet done'),
            ];
            values.forEach(value => {
                const cell = document.createElement('td');
                cell.textContent = value;
                row.append(cell);
            });
            return row;
        });
        document.getElementById('chart-members-rows').replaceChildren(...rows);
        document.getElementById('chart-members-empty').hidden = matchingMembers.length > 0;
        chartMembersDialog.showModal();
    }

    /* ---------- Entry breakdown popup ---------- */
    const breakdownDialog = document.getElementById('breakdown-dialog');
    document.getElementById('total-card').addEventListener('click', () => {
        const counts = kalagayanTypes.map(k => ({ k, n: members.filter(m => m.kalagayan === k).length }));
        const byKey = Object.fromEntries(counts.map(c => [c.k, c.n]));
        document.getElementById('breakdown-summary').textContent =
            `Of the ${total} entries, ${byKey.MS} are MS, ${byKey.UWP} are UWP and ${byKey.MK} are MK.`;

        const bars = [];
        document.getElementById('breakdown-rows').replaceChildren(...counts.map(({ k, n }) => {
            const pct = total ? Math.round(n / total * 100) : 0;
            const row = document.createElement('div');
            const top = document.createElement('div');
            top.className = 'bd-top';
            const label = document.createElement('span');
            label.className = 'legend-label';
            const swatch = document.createElement('span');
            swatch.className = 'legend-swatch';
            swatch.style.backgroundColor = kColors[k];
            const name = document.createElement('span');
            name.textContent = k;
            label.append(swatch, name);
            const value = document.createElement('span');
            value.className = 'legend-value';
            const strong = document.createElement('strong');
            strong.textContent = n;
            value.append(strong, document.createTextNode(`${pct}%`));
            top.append(label, value);
            const track = document.createElement('div');
            track.className = 'bd-bar';
            const fill = document.createElement('span');
            fill.style.background = kColors[k];
            track.append(fill);
            bars.push([fill, pct]);
            row.append(top, track);
            return row;
        }));

        breakdownDialog.showModal();
        requestAnimationFrame(() => requestAnimationFrame(() => bars.forEach(([el, pct]) => { el.style.width = pct + '%'; })));
    });

    /* ---------- Charts ---------- */
    let chartsOK = typeof Chart !== 'undefined';
    if (chartsOK) {
        Chart.defaults.font.family = getComputedStyle(document.body).fontFamily;
        Chart.defaults.animation = reduceMotion ? false : { duration: 900, easing: 'easeOutQuart' };
    }
    const chartFont = () => (chartsOK ? Chart.defaults.font.family : 'sans-serif');

    function showChartError() {
        if (typeof progressChart !== 'undefined' && progressChart) { try { progressChart.destroy(); } catch (e) {} progressChart = null; }
        document.querySelectorAll('.donut-wrap, .bar-wrap').forEach(el => {
            const msg = document.createElement('p');
            msg.className = 'chart-error';
            msg.textContent = "The chart couldn't load. Reload the page to try again.";
            el.replaceChildren(msg);
        });
    }

    const categories = detailed => detailed
        ? [
            { key: 'not-eligible-no-endorsement', label: 'Not eligible · No endorsement', color: colors.notEligibleNotEndorsed, matches: m => !m.eligible && !m.endorsed && m.kalagayan !== 'MK' },
            { key: 'not-eligible-endorsed', label: 'Not eligible · Endorsed (review required)', color: colors.notEligibleEndorsed, matches: m => !m.eligible && m.endorsed && m.kalagayan !== 'MK' },
            { key: 'eligible-no-endorsement', label: 'Eligible · No endorsement', color: colors.eligibleNotEndorsed, matches: m => m.eligible && !m.endorsed && m.kalagayan !== 'MK' },
            { key: 'eligible-endorsed', label: 'Eligible · Endorsed', color: colors.eligibleEndorsed, matches: m => m.eligible && m.endorsed && m.kalagayan !== 'MK' },
            { key: 'mk', label: 'MK', color: colors.mk, matches: m => m.kalagayan === 'MK' },
        ]
        : [
            { key: 'eligible', label: 'Eligible', color: colors.eligible, matches: m => m.eligible },
            { key: 'not-eligible', label: 'Not eligible', color: colors.notEligible, matches: m => !m.eligible },
        ];

    // Eligible share in the middle of the donut.
    const centerText = {
        id: 'centerText',
        afterDraw(chart) {
            if (!chart.ctx || !chart.chartArea) return;
            const { ctx, chartArea: { left, right, top, bottom } } = chart;
            const cx = (left + right) / 2, cy = (top + bottom) / 2;
            const eligible = members.filter(m => m.eligible).length;
            const pct = total ? Math.round(eligible / total * 100) : 0;
            ctx.save();
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';
            ctx.fillStyle = css('--text') || '#111';
            ctx.font = '700 26px ' + Chart.defaults.font.family;
            ctx.fillText(pct + '%', cx, cy - 8);
            ctx.fillStyle = css('--text-muted') || '#667085';
            ctx.font = '600 11px ' + Chart.defaults.font.family;
            ctx.fillText('eligible', cx, cy + 14);
            ctx.restore();
        },
    };

    const surface = () => css('--surface') || '#fff';
    const legend = document.getElementById('chart-legend');
    const description = document.getElementById('chart-description');
    let detailed = false;
    let eligibilityChart = null;

    function buildEligibilityChart(cats) {
        if (!chartsOK) return;
        try {
        if (eligibilityChart) {
            eligibilityChart.stop();
            eligibilityChart.$categories = cats;
            eligibilityChart.data.labels = cats.map(c => c.label);
            eligibilityChart.data.datasets[0].data = cats.map(c => c.count);
            eligibilityChart.data.datasets[0].backgroundColor = cats.map(c => c.color);
            eligibilityChart.data.datasets[0].borderColor = surface();
            eligibilityChart.update('none');
            return;
        }
        eligibilityChart = new Chart(document.getElementById('eligibility-chart'), {
            type: 'doughnut',
            data: {
                labels: cats.map(c => c.label),
                datasets: [{ data: cats.map(c => c.count), backgroundColor: cats.map(c => c.color), borderWidth: 3, borderColor: surface(), hoverOffset: 8 }],
            },
            options: {
                cutout: '68%',
                responsive: true,
                maintainAspectRatio: false,
                animation: reduceMotion ? false : { animateRotate: true, animateScale: true, duration: 1000, easing: 'easeOutQuart' },
                onClick: (event, _active, chart) => {
                    const [hit] = chart.getElementsAtEventForMode(event, 'nearest', { intersect: true }, true);
                    if (!hit) return;
                    const category = chart.$categories[hit.index];
                    showChartMembers(category.label, 'R2-10 eligibility', members.filter(category.matches), category.key);
                },
                plugins: {
                    legend: { display: false },
                    tooltip: { callbacks: { label: ctx => ` ${ctx.label}: ${ctx.parsed} (${total ? Math.round(ctx.parsed / total * 100) : 0}%)` } },
                },
            },
            plugins: [centerText],
        });
        eligibilityChart.$categories = cats;
        } catch (err) {
            console.error('Eligibility chart failed:', err);
            chartsOK = false;
            showChartError();
        }
    }

    function renderEligibility() {
        const cats = categories(detailed).map(c => ({ ...c, count: members.filter(c.matches).length }));
        buildEligibilityChart(cats);
        if (eligibilityChart) eligibilityChart.canvas.setAttribute('aria-label',
            `Doughnut chart showing ${cats.map(c => `${c.label}: ${c.count}`).join(', ')}`);

        legend.replaceChildren(...cats.map((c, i) => {
            const row = document.createElement('button');
            row.type = 'button';
            row.className = 'legend-row';
            const label = document.createElement('span');
            label.className = 'legend-label';
            const swatch = document.createElement('span');
            swatch.className = 'legend-swatch';
            swatch.style.backgroundColor = c.color;
            const text = document.createElement('span');
            text.textContent = c.label;
            label.append(swatch, text);
            const value = document.createElement('span');
            value.className = 'legend-value';
            const strong = document.createElement('strong');
            strong.textContent = c.count;
            value.append(strong, document.createTextNode(`${total ? Math.round(c.count / total * 100) : 0}%`));
            row.append(label, value);
            row.setAttribute('aria-label', `Show ${c.count} records: ${c.label}`);
            row.addEventListener('click', () => showChartMembers(c.label, 'R2-10 eligibility', members.filter(c.matches), c.key));
            row.addEventListener('mouseenter', () => {
                if (!eligibilityChart || !eligibilityChart.ctx) return;
                eligibilityChart.setActiveElements([{ datasetIndex: 0, index: i }]);
                eligibilityChart.update('none');
            });
            row.addEventListener('mouseleave', () => {
                if (!eligibilityChart || !eligibilityChart.ctx) return;
                eligibilityChart.setActiveElements([]);
                eligibilityChart.update('none');
            });
            return row;
        }));

        description.textContent = detailed
            ? 'Eligibility split by endorsement, with MK shown separately. Click a chart slice or label to see names.'
            : 'Eligible and not eligible members. Click a chart slice or label to see names.';
    }

    // Numbers inside each segment, plus the reported % after the bar.
    const segmentLabels = {
        id: 'segmentLabels',
        afterDatasetsDraw(chart) {
            if (!chart.ctx || !chart.chartArea) return;
            const { ctx } = chart;
            const font = chartFont();
            ctx.save();
            ctx.textBaseline = 'middle';
            chart.data.datasets.forEach((ds, i) => {
                const meta = chart.getDatasetMeta(i);
                if (meta.hidden) return;
                meta.data.forEach((bar, idx) => {
                    const v = ds.data[idx];
                    if (!v || !Number.isFinite(bar.x) || !Number.isFinite(bar.base)) return;
                    if (Math.abs(bar.x - bar.base) < 20) return; // too narrow to fit a number yet
                    ctx.font = '700 13px ' + font;
                    ctx.fillStyle = '#fff';
                    ctx.textAlign = 'center';
                    ctx.fillText(String(v), (bar.x + bar.base) / 2, bar.y);
                });
            });
            const done = chart.data.datasets[0].data;
            const pending = chart.data.datasets[1].data;
            chart.getDatasetMeta(1).data.forEach((bar, idx) => {
                const sum = (done[idx] || 0) + (pending[idx] || 0);
                if (!sum || !Number.isFinite(bar.x)) return;
                ctx.font = '600 11px ' + font;
                ctx.fillStyle = css('--text-muted') || '#667085';
                ctx.textAlign = 'left';
                ctx.fillText(Math.round((done[idx] || 0) / sum * 100) + '%', bar.x + 10, bar.y);
            });
            ctx.restore();
        },
    };

    let progressChart = null;
    if (chartsOK) {
        try {
            const segment = color => ({
                backgroundColor: color, borderColor: surface(), borderWidth: 2,
                borderRadius: 10, borderSkipped: false, barThickness: 34,
            });
            progressChart = new Chart(document.getElementById('progress-chart'), {
                type: 'bar',
                data: {
                    labels: kalagayanTypes,
                    datasets: [
                        { label: 'Created & reported', data: [], ...segment(colors.done) },
                        { label: 'Not yet done', data: [], ...segment(colors.pending) },
                    ],
                },
                options: {
                    indexAxis: 'y',
                    responsive: true,
                    maintainAspectRatio: false,
                    layout: { padding: { right: 44 } },
                    animation: reduceMotion ? false : { duration: 900, easing: 'easeOutQuart', delay: ctx => ctx.type === 'data' ? ctx.dataIndex * 120 : 0 },
                    scales: {
                        x: { stacked: true, display: false, min: 0 },
                        y: { stacked: true, grid: { display: false }, border: { display: false }, ticks: { font: { weight: '700', size: 13 } } },
                    },
                    plugins: { legend: { display: false } },
                    onClick: (event, _active, chart) => {
                        const [hit] = chart.getElementsAtEventForMode(event, 'nearest', { intersect: true }, true);
                        if (!hit) return;
                        const kalagayan = kalagayanTypes[hit.index];
                        const reported = hit.datasetIndex === 0;
                        const label = reported ? 'Created & reported' : 'Not yet done';
                        showChartMembers(`${kalagayan} · ${label}`, 'R2-10 report progress',
                            members.filter(member => member.kalagayan === kalagayan && member.reported === reported),
                            `progress-${kalagayan.toLowerCase()}-${reported ? 'reported' : 'pending'}`);
                    },
                },
                plugins: [segmentLabels],
            });
        } catch (err) {
            console.error('Progress chart failed:', err);
            chartsOK = false;
        }
    }
    if (!chartsOK) showChartError();

    function renderProgress() {
        if (!progressChart) return;
        const done = kalagayanTypes.map(k => members.filter(m => m.kalagayan === k && m.reported).length);
        const pending = kalagayanTypes.map(k => members.filter(m => m.kalagayan === k && !m.reported).length);
        progressChart.data.datasets[0].data = done;
        progressChart.data.datasets[1].data = pending;
        progressChart.data.datasets.forEach(d => { d.borderColor = surface(); });
        progressChart.options.scales.x.max = Math.max(1, ...kalagayanTypes.map((k, i) => done[i] + pending[i]));
        progressChart.options.scales.y.ticks.color = css('--text') || '#111';
        progressChart.update();
        progressChart.canvas.setAttribute('aria-label',
            `Stacked bar chart of reported versus not yet done by kalagayan: ${kalagayanTypes.map((k, i) => `${k} ${done[i]} reported, ${pending[i]} not yet done`).join('; ')}`);
    }

    document.querySelectorAll('.seg-btn').forEach(btn => btn.addEventListener('click', () => {
        detailed = btn.dataset.view === 'detailed';
        document.querySelectorAll('.seg-btn').forEach(b => {
            const active = b === btn;
            b.classList.toggle('is-active', active);
            b.setAttribute('aria-pressed', String(active));
        });
        renderEligibility();
    }));

    /* ---------- Report form ---------- */
    const reportDialog = document.getElementById('report-dialog');
    const reportForm = document.getElementById('report-form');
    const reportNote = document.getElementById('report-note');
    const reportAssure = document.getElementById('report-assure');
    const oneYearRow = document.getElementById('one-year-row');
    const oneYearConfirmed = document.getElementById('one-year-confirmed');
    const mkReasonNote = document.getElementById('mk-reason-note');
    const reportSubmit = document.getElementById('report-submit');
    let pendingMember = null;
    let reportSaving = false;

    function updateReportState() {
        document.getElementById('report-count').textContent = `${reportNote.value.length} / 500`;
        const hasReason = reportNote.value.trim().length > 0;
        const validReason = pendingMember?.kalagayan === 'MK' ? hasReason : hasReason || oneYearConfirmed.checked;
        reportSubmit.disabled = reportSaving || !(validReason && reportAssure.checked);
    }
    reportNote.addEventListener('input', updateReportState);
    reportAssure.addEventListener('change', updateReportState);
    oneYearConfirmed.addEventListener('change', () => {
        if (oneYearConfirmed.checked) reportNote.value = '';
        reportNote.disabled = oneYearConfirmed.checked;
        updateReportState();
    });
    reportDialog.addEventListener('close', () => { pendingMember = null; });

    function openReport(member) {
        pendingMember = member;
        document.getElementById('report-member').textContent = `${member.name} · ${member.kalagayan}`;
        reportNote.value = '';
        oneYearRow.hidden = !['MS', 'UWP'].includes(member.kalagayan);
        mkReasonNote.hidden = member.kalagayan !== 'MK';
        oneYearConfirmed.checked = false;
        reportNote.disabled = false;
        reportNote.placeholder = member.kalagayan === 'MK'
            ? 'A specific description is required for MK.'
            : 'Enter a brief description, or confirm the one-year MS/UWP condition below.';
        reportAssure.checked = false;
        updateReportState();
        reportDialog.showModal();
        reportNote.focus();
    }

    reportForm.addEventListener('submit', async e => {
        e.preventDefault();
        if (reportSubmit.disabled || reportSaving || !pendingMember) return;
        const member = pendingMember;
        const note = reportNote.value.trim();
        const oneYear = oneYearConfirmed.checked;
        reportSaving = true;
        reportSubmit.textContent = 'Saving...';
        updateReportState();
        try {
            await markReported(member, note, oneYear);
            reportDialog.close();
        } catch (error) {
            console.error('R2-10 endorsement could not be saved:', error);
            showToast(error.message || 'Unable to save the R2-10 endorsement.');
        } finally {
            reportSaving = false;
            reportSubmit.textContent = 'Confirm & mark as reported';
            updateReportState();
        }
    });

    /* ---------- Table ---------- */
    const state = { search: '', eligibility: 'all', status: 'all', kalagayan: 'all', age: 'all', sortKey: null, sortDir: 'asc', page: 1 };
    const rowsEl = document.getElementById('member-rows');
    const emptyEl = document.getElementById('empty-state');
    const ageSort = document.getElementById('age-sort');
    let justDoneId = null;

    const ageMatches = m => state.age === 'all'
        || (m.entryDate && (state.age === 'under' ? ageYears(m) === 0 : ageYears(m) >= 1));

    function filtered() {
        const q = state.search.trim().toLowerCase();
        let list = members.filter(m =>
            (!q || m.name.toLowerCase().includes(q) || m.kalagayan.toLowerCase().includes(q)) &&
            (state.eligibility === 'all' || (state.eligibility === 'eligible') === m.eligible) &&
            (state.status === 'all' || (state.status === 'done') === m.reported) &&
            (state.kalagayan === 'all' || m.kalagayan === state.kalagayan) &&
            ageMatches(m)
        );
        if (state.sortKey) {
            const dir = state.sortDir === 'asc' ? 1 : -1;
            list = [...list].sort((a, b) => {
                if (state.sortKey === 'age') {
                    // Sort by time since date of entry; records without a date go last.
                    const av = ageDays(a), bv = ageDays(b);
                    if (av === null && bv === null) return 0;
                    if (av === null) return 1;
                    if (bv === null) return -1;
                    return (av - bv) * dir;
                }
                const av = a[state.sortKey], bv = b[state.sortKey];
                if (typeof av === 'string') return av.localeCompare(bv, undefined, { numeric: true }) * dir;
                return (Number(av) - Number(bv)) * dir;
            });
        }
        return list;
    }

    function makeBadge(text, className, title) {
        const badge = document.createElement('span');
        badge.className = `table-badge ${className}`;
        badge.textContent = text;
        if (title) badge.title = title;
        return badge;
    }

    const lockSvg = '<svg viewBox="0 0 16 16" aria-hidden="true"><rect x="3.5" y="7" width="9" height="6.5" rx="1.5"/><path d="M5.5 7V5.2a2.5 2.5 0 0 1 5 0V7"/></svg>';

    function statusCell(member) {
        if (member.reported) {
            const done = document.createElement('span');
            done.className = 'status-done' + (member.id === justDoneId ? ' just-done' : '');
            if (member.note) done.title = member.note;
            done.innerHTML = '<svg viewBox="0 0 16 16" aria-hidden="true"><path d="m3.5 8.5 3 3 6-7"/></svg>';
            done.append(document.createTextNode('Created & Reported'));
            return done;
        }
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'status-btn';

        if (!member.eligible) {
            btn.classList.add('is-locked');
            btn.setAttribute('aria-disabled', 'true');
            btn.title = member.endorsed
                ? 'Endorsed, but the member does not currently meet the R2-10 eligibility rules.'
                : "Not eligible for R2-10, so it can't be marked as created & reported";
            btn.setAttribute('aria-label', member.endorsed
                ? `${member.name} is endorsed but does not currently meet the R2-10 eligibility rules.`
                : `Not yet done. ${member.name} is not eligible, so R2-10 can't be reported.`);
            btn.innerHTML = lockSvg;
            btn.append(document.createTextNode(member.endorsed
                ? 'Endorsed · Not eligible — review required'
                : 'Not yet done'));
            btn.addEventListener('click', () => showToast(member.endorsed
                ? `${member.name} is endorsed, but does not currently meet the R2-10 eligibility rules.`
                : `${member.name} is not eligible, so R2-10 can't be reported.`));
            return btn;
        }

        btn.setAttribute('aria-haspopup', 'dialog');
        btn.setAttribute('aria-label', `Mark R2-10 as created and reported for ${member.name}`);
        const idle = document.createElement('span');
        idle.className = 'on-idle';
        idle.textContent = 'Not yet done';
        const hover = document.createElement('span');
        hover.className = 'on-hover';
        hover.textContent = 'Mark as reported';
        btn.append(idle, hover);
        btn.addEventListener('click', () => openReport(member));
        return btn;
    }

    async function markReported(member, note, oneYearConfirmed = false) {
        if (member.reported || !member.eligible) return;
        const response = await fetch(config.dataset.endorseUrl.replace('__RECORD__', encodeURIComponent(member.id)), {
            method: 'PUT',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': config.dataset.csrf,
            },
            body: JSON.stringify({ note: note || null, one_year_confirmed: oneYearConfirmed, assurance: true }),
        });
        const result = await response.json();
        if (!response.ok) throw new Error(result.message || 'Unable to save the R2-10 endorsement.');
        member.endorsed = true;
        member.reported = true;
        member.note = note;
        justDoneId = member.id;
        renderTable();
        renderStats();
        renderProgress();
        showToast(`${member.name}: R2-10 marked as created & reported`);
        setTimeout(() => { justDoneId = null; }, 600);
    }

    const delistDialog = document.getElementById('delist-dialog');
    const delistForm = document.getElementById('delist-form');
    const delistReason = document.getElementById('delist-reason');
    const delistSubmit = document.getElementById('delist-submit');
    let pendingDelistMember = null;
    let delistSaving = false;

    function openDelist(member) {
        pendingDelistMember = member;
        delistForm.reset();
        document.getElementById('delist-member').textContent = `${member.name} · MK`;
        document.getElementById('delist-error').textContent = '';
        delistDialog.showModal();
        delistReason.focus();
    }

    delistForm.addEventListener('submit', async event => {
        event.preventDefault();
        if (delistSaving || !pendingDelistMember || !delistForm.reportValidity()) return;

        const member = pendingDelistMember;
        delistSaving = true;
        delistSubmit.disabled = true;
        delistSubmit.textContent = 'Saving...';
        document.getElementById('delist-error').textContent = '';
        try {
            const response = await fetch(config.dataset.delistUrl.replace('__RECORD__', encodeURIComponent(member.id)), {
                method: 'PUT',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': config.dataset.csrf,
                },
                body: JSON.stringify({ reason: delistReason.value.trim() }),
            });
            const result = await response.json();
            if (!response.ok) throw new Error(result.message || 'Unable to delist this MK member.');

            members.splice(members.indexOf(member), 1);
            total = members.length;
            const missingIndex = missingRecords.findIndex(record => record.id === member.id);
            if (missingIndex !== -1) missingRecords.splice(missingIndex, 1);
            delistDialog.close();
            renderStats();
            renderEligibility();
            renderProgress();
            renderTable();
            renderMissingCount();
            showToast(`${member.name} was delisted and marked INACTIVE.`);
        } catch (error) {
            console.error('MK delistment could not be saved:', error);
            document.getElementById('delist-error').textContent = error.message || 'Unable to delist this MK member.';
        } finally {
            delistSaving = false;
            delistSubmit.disabled = false;
            delistSubmit.textContent = 'Confirm delistment';
        }
    });
    delistDialog.addEventListener('close', () => { pendingDelistMember = null; });

    function renderTable() {
        const list = filtered();
        const pages = Math.max(1, Math.ceil(list.length / PAGE_SIZE));
        state.page = Math.min(state.page, pages);
        const start = (state.page - 1) * PAGE_SIZE;
        const visible = list.slice(start, start + PAGE_SIZE);

        rowsEl.replaceChildren(...visible.map(member => {
            const row = document.createElement('tr');

            const name = document.createElement('td');
            const cell = document.createElement('div');
            cell.className = 'member-cell';
            const avatar = document.createElement('span');
            avatar.className = 'avatar';
            avatar.style.background = avatarColors[member.id % avatarColors.length];
            avatar.setAttribute('aria-hidden', 'true');
            avatar.textContent = member.name.replace(/\D/g, '') || member.name[0];
            cell.append(avatar, document.createTextNode(member.name));
            name.append(cell);

            const kalagayan = document.createElement('td');
            const kBadge = document.createElement('span');
            kBadge.className = `kalagayan-badge k-${member.kalagayan}`;
            kBadge.textContent = member.kalagayan;
            kalagayan.append(kBadge);

            const eligibility = document.createElement('td');
            eligibility.append(member.kalagayan === 'MK'
                ? makeBadge('Eligible (auto)', 'badge-eligible', 'MK is automatically eligible')
                : makeBadge(member.eligible ? 'Eligible' : 'Not eligible', member.eligible ? 'badge-eligible' : 'badge-not-eligible'));

            const status = document.createElement('td');
            status.append(statusCell(member));

            const age = document.createElement('td');
            const ageEl = document.createElement('span');
            if (member.entryDate) {
                ageEl.className = 'age-value';
                ageEl.textContent = formatAge(member.entryDate);
                ageEl.title = 'Entered on ' + parseISO(member.entryDate).toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });
            } else {
                ageEl.className = 'age-empty';
                ageEl.textContent = '—';
                ageEl.setAttribute('aria-label', 'Not reported yet');
            }
            age.append(ageEl);

            row.append(name, kalagayan, eligibility, status, age);
            const delistCell = document.createElement('td');
            if (member.kalagayan === 'MK' && member.reported) {
                const delistButton = document.createElement('button');
                delistButton.type = 'button';
                delistButton.className = 'delist-action';
                delistButton.textContent = 'Delist';
                delistButton.setAttribute('aria-label', `Delist MK member ${member.name}`);
                delistButton.addEventListener('click', () => openDelist(member));
                delistCell.append(delistButton);
            } else {
                delistCell.textContent = member.kalagayan === 'MK' ? 'Endorse first' : '—';
            }
            row.append(delistCell);
            return row;
        }));

        emptyEl.hidden = list.length !== 0;
        document.getElementById('page-info').textContent = list.length
            ? `Showing ${start + 1}–${start + visible.length} of ${list.length}`
            : 'No results';
        document.getElementById('page-label').textContent = `Page ${state.page} of ${pages}`;
        document.getElementById('prev-page').disabled = state.page <= 1;
        document.getElementById('next-page').disabled = state.page >= pages;
    }

    const resetPageAndRender = () => { state.page = 1; renderTable(); };

    document.getElementById('member-search').addEventListener('input', e => { state.search = e.target.value; resetPageAndRender(); });
    document.getElementById('kalagayan-filter').addEventListener('change', e => { state.kalagayan = e.target.value; resetPageAndRender(); });

    function bindChips(selector, key, attr) {
        document.querySelectorAll(selector).forEach(chip => chip.addEventListener('click', () => {
            state[key] = chip.dataset[attr];
            document.querySelectorAll(selector).forEach(c => c.classList.toggle('is-active', c === chip));
            resetPageAndRender();
        }));
    }
    bindChips('[data-filter]', 'eligibility', 'filter');
    bindChips('[data-status]', 'status', 'status');
    bindChips('[data-age]', 'age', 'age');

    document.getElementById('pending-card').addEventListener('click', () => {
        Object.assign(state, {
            search: '',
            eligibility: 'eligible',
            status: 'pending',
            kalagayan: 'all',
            age: 'all',
            page: 1,
        });
        document.getElementById('member-search').value = '';
        document.getElementById('kalagayan-filter').value = 'all';
        document.querySelectorAll('[data-filter]').forEach(chip => chip.classList.toggle('is-active', chip.dataset.filter === 'eligible'));
        document.querySelectorAll('[data-status]').forEach(chip => chip.classList.toggle('is-active', chip.dataset.status === 'pending'));
        document.querySelectorAll('[data-age]').forEach(chip => chip.classList.toggle('is-active', chip.dataset.age === 'all'));
        renderTable();
        document.getElementById('members-heading').scrollIntoView({
            behavior: reduceMotion ? 'auto' : 'smooth',
            block: 'start',
        });
    });

    document.getElementById('clear-filters').addEventListener('click', () => {
        Object.assign(state, { search: '', eligibility: 'all', status: 'all', kalagayan: 'all', age: 'all' });
        document.getElementById('member-search').value = '';
        document.getElementById('kalagayan-filter').value = 'all';
        document.querySelectorAll('[data-filter]').forEach(c => c.classList.toggle('is-active', c.dataset.filter === 'all'));
        document.querySelectorAll('[data-status]').forEach(c => c.classList.toggle('is-active', c.dataset.status === 'all'));
        document.querySelectorAll('[data-age]').forEach(c => c.classList.toggle('is-active', c.dataset.age === 'all'));
        resetPageAndRender();
    });

    // Sorting: the column headers and the "Sort by age" select stay in sync.
    function setSort(key, dir) {
        state.sortKey = key;
        state.sortDir = dir;
        document.querySelectorAll('.member-table th').forEach(th => th.setAttribute('aria-sort', 'none'));
        if (key) {
            document.querySelector(`[data-sort="${key}"]`).closest('th')
                .setAttribute('aria-sort', dir === 'asc' ? 'ascending' : 'descending');
        }
        ageSort.value = key === 'age' ? (dir === 'asc' ? 'newest' : 'longest') : '';
        resetPageAndRender();
    }
    document.querySelectorAll('.sort-btn').forEach(btn => btn.addEventListener('click', () => {
        const key = btn.dataset.sort;
        setSort(key, state.sortKey === key && state.sortDir === 'asc' ? 'desc' : 'asc');
    }));
    ageSort.addEventListener('change', () => {
        if (ageSort.value === 'newest') setSort('age', 'asc');
        else if (ageSort.value === 'longest') setSort('age', 'desc');
        else setSort(null, 'asc');
    });

    document.getElementById('prev-page').addEventListener('click', () => { state.page--; renderTable(); });
    document.getElementById('next-page').addEventListener('click', () => { state.page++; renderTable(); });

    /* ---------- Entries with missing data ---------- */
    const missingDialog = document.getElementById('missing-dialog');
    const missingRowsEl = document.getElementById('missing-rows');
    const missingSave = document.getElementById('missing-save');
    const missingHint = document.getElementById('missing-hint');
    const isComplete = r => Boolean(r.baptism);
    const stillMissing = () => missingRecords.filter(r => !isComplete(r));
    let drafts = {}; // record id -> baptism date while editing

    function renderMissingCount() {
        const n = stillMissing().length;
        const badge = document.getElementById('missing-count');
        badge.textContent = n;
        badge.hidden = n === 0;
        document.getElementById('missing-sub').textContent = n
            ? `${n} ${n === 1 ? 'entry needs' : 'entries need'} updating`
            : 'Nothing left to update';
    }

    function changedIds() {
        return Object.keys(drafts).filter(id => {
            const r = missingRecords.find(x => String(x.id) === id);
            return drafts[id].baptism !== r.baptism;
        });
    }
    function updateSaveState() {
        const n = changedIds().length;
        missingSave.disabled = n === 0;
        missingHint.textContent = n ? `${n} unsaved ${n === 1 ? 'change' : 'changes'}` : '';
    }

    function makeDateInput(record, field, label) {
        const input = document.createElement('input');
        input.type = 'date';
        input.className = 'date-input';
        input.max = toISO(startOfToday());
        input.value = drafts[record.id] ? drafts[record.id][field] : record[field];
        input.setAttribute('aria-label', `${label} for ${record.name}`);
        const flag = () => input.classList.toggle('is-missing', !input.value);
        flag();
        input.addEventListener('input', () => {
            drafts[record.id] = { ...(drafts[record.id] || {}), [field]: input.value };
            flag();
            updateSaveState();
        });
        return input;
    }

    function renderMissingRows() {
        const list = stillMissing();
        document.getElementById('missing-table').hidden = list.length === 0;
        document.getElementById('missing-empty').hidden = list.length !== 0;
        missingRowsEl.replaceChildren(...list.map(record => {
            const row = document.createElement('tr');
            row.dataset.id = record.id;
            const name = document.createElement('td');
            name.textContent = record.name;
            name.style.fontWeight = '650';
            const registry = document.createElement('td');
            registry.className = 'registry';
            registry.textContent = record.registry;
            const baptism = document.createElement('td');
            baptism.append(makeDateInput(record, 'baptism', 'Date of baptism'));
            row.append(name, registry, baptism);
            return row;
        }));
    }

    document.getElementById('missing-btn').addEventListener('click', () => {
        drafts = {};
        renderMissingCount();
        renderMissingRows();
        updateSaveState();
        missingDialog.showModal();
    });
    missingDialog.addEventListener('close', () => { drafts = {}; });

    missingSave.addEventListener('click', async () => {
        const ids = changedIds();
        if (!ids.length) return;
        const today = toISO(startOfToday());
        if (ids.some(id => drafts[id].baptism > today)) {
            showToast("Dates can't be in the future.");
            return;
        }
        missingSave.disabled = true;
        missingSave.textContent = 'Saving...';
        try {
            const response = await fetch(config.dataset.updateDatesUrl, {
                method: 'PUT',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': config.dataset.csrf,
                },
                body: JSON.stringify({
                    changes: ids.map(id => ({
                        id: Number(id),
                        baptism: drafts[id].baptism,
                    })),
                }),
            });
            const result = await response.json();
            if (!response.ok) throw new Error(result.message || 'Unable to save record dates.');

            result.data.forEach(saved => {
                const record = missingRecords.find(item => item.id === saved.id);
                if (record) {
                    record.baptism = saved.baptism;
                }
                const member = members.find(item => item.id === saved.id);
                if (member) {
                    member.eligible = saved.eligible;
                    member.reported = saved.reported;
                }
            });
            drafts = {};
            renderMissingCount();
            renderMissingRows();
            updateSaveState();
            renderTable();
            renderStats();
            renderEligibility();
            renderProgress();
            showToast('Record dates saved. R2-10 eligibility has been recalculated.');
        } catch (error) {
            console.error('Record dates could not be saved:', error);
            showToast(error.message || 'Unable to save record dates.');
            updateSaveState();
        } finally {
            missingSave.textContent = 'Save changes';
        }
    });

    /* ---------- Init ---------- */
    renderStats();
    renderEligibility();
    renderProgress();
    renderTable();
    renderMissingCount();

    // Re-theme charts when the light/dark switch changes.
    new MutationObserver(() => { renderEligibility(); renderProgress(); })
        .observe(document.documentElement, { attributes: true, attributeFilter: ['data-theme'] });
});
</script>
@endpush