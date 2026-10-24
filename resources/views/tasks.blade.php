@extends('layouts.app')

@section('title', 'Tasks')

@php
    $workspaceName = trim(($authenticatedUser['first_name'] ?? '').' '.($authenticatedUser['last_name'] ?? '')) ?: ($authenticatedUser['gmail'] ?? 'You');
    $workspaceFirstName = trim((string) ($authenticatedUser['first_name'] ?? '')) ?: 'Head Secretary';
    $workspaceInitials = collect(preg_split('/\s+/', $workspaceName, -1, PREG_SPLIT_NO_EMPTY))->take(2)->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))->implode('') ?: '?';
    $workspaceAvatar = \App\Http\Controllers\ProfilePictureController::urlFor($authenticatedUser['id'] ?? null, $authenticatedUser['profile_picture'] ?? null);
@endphp

@push('styles')
@include('messages.partials.workspace-styles')
<style>
    /* The Tasks page: workspace tokens as a full page (header, stats, toolbar, feed + side column). */
    .tasks-page.workspace { height: auto; min-height: calc(100vh - var(--bar-height, 56px) - 4rem); overflow: visible; border: 0; border-radius: 0; background: transparent; }
    .tasks-shell { display: grid; gap: 1rem; width: 100%; max-width: 1100px; margin: 0 auto; }
    .tasks-hero { display: flex; align-items: flex-end; justify-content: space-between; gap: 1rem; flex-wrap: wrap; }
    .tasks-hero h1 { margin: 0; font-size: clamp(1.6rem, 3vw, 2rem); font-weight: 650; letter-spacing: -.035em; line-height: 1.05; }
    .tasks-hero p { max-width: 46rem; margin: .4rem 0 0; color: var(--ws-muted); font-size: .9rem; line-height: 1.55; }

    .tasks-layout { display: grid; grid-template-columns: minmax(0, 1fr) 320px; gap: 1rem; align-items: start; }
    .tasks-main { display: grid; gap: .75rem; min-width: 0; }
    .tasks-side { position: sticky; top: calc(var(--bar-height, 56px) + 1rem); display: grid; align-content: start; gap: .75rem; }
    @media (max-width: 1060px) {
        .tasks-layout { grid-template-columns: minmax(0, 1fr); }
        .tasks-side { position: static; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); }
    }
</style>
@endpush

@section('content')
<div class="workspace tasks-page" id="tasksApp"
    data-csrf="{{ csrf_token() }}"
    data-is-head="{{ $tasksIsHead ? 'true' : 'false' }}"
    data-user-id="{{ (int) ($authenticatedUser['id'] ?? 0) }}"
    data-user-name="{{ $workspaceName }}"
    data-user-avatar="{{ $workspaceAvatar }}"
    data-task-list-url="{{ route('assigned-tasks.index') }}"
    data-task-completed-url="{{ route('assigned-tasks.completed') }}"
    data-task-activity-url="{{ route('assigned-tasks.activity') }}"
    data-task-seen-url="{{ route('assigned-tasks.seen') }}"
    data-task-secretaries-url="{{ route('assigned-tasks.secretaries') }}"
    data-task-people-url="{{ route('assigned-tasks.people') }}"
    data-task-create-url="{{ route('assigned-tasks.store') }}"
    data-task-update-url="{{ route('assigned-tasks.update', ['task' => '__ID__']) }}"
    data-task-delete-url="{{ route('assigned-tasks.destroy', ['task' => '__ID__']) }}"
    data-task-comment-url="{{ route('assigned-tasks.comment', ['task' => '__ID__']) }}"
    data-task-react-url="{{ route('assigned-tasks.react', ['task' => '__ID__']) }}"
    data-task-complete-url="{{ route('assigned-tasks.complete', ['task' => '__ID__']) }}"
    data-task-undo-url="{{ route('assigned-tasks.undo-complete', ['task' => '__ID__']) }}"
    data-task-reopen-url="{{ route('assigned-tasks.reopen', ['task' => '__ID__']) }}"
    data-task-confirm-url="{{ route('assigned-tasks.confirm', ['task' => '__ID__']) }}">
    <div class="tasks-shell">
        <header class="tasks-hero">
            <div>
                <h1>Tasks</h1>
                <p>
                    @if ($tasksIsHead)
                        Assign work to secretaries, choose who sees it and what they can do, and confirm finished tasks.
                    @else
                        Work the Head Secretary shared with you. Comment, react, and mark a task done when it's finished.
                    @endif
                </p>
            </div>
        </header>

        {{-- Numbers at a glance; each one is also a quick filter. --}}
        <div class="ws-stats" id="taskStats" role="group" aria-label="Task summary"></div>

        <div class="tasks-layout">
            <div class="tasks-main">
                @if ($tasksIsHead)
                    <div class="ws-card ws-composer" id="taskComposer">
                        <div class="ws-composer-row">
                            @include('messages.partials.workspace-avatar')
                            <button type="button" class="ws-composer-open" id="taskComposeToggle" aria-expanded="false" aria-controls="taskComposeForm">
                                What should the secretaries do, {{ $workspaceFirstName }}?
                            </button>
                            <span class="ws-kbd" aria-hidden="true">N</span>
                        </div>
                        <form class="ws-compose-form" id="taskComposeForm" autocomplete="off" hidden>
                            <h3 id="taskComposeHeading">Assign a task</h3>
                            <input id="taskTitle" type="text" maxlength="200" placeholder="Task title (type @ to mention someone)" aria-label="Task title" autocomplete="off" autocorrect="off" spellcheck="true" data-lpignore="true" data-1p-ignore required>
                            <textarea id="taskBody" rows="3" maxlength="5000" placeholder="Details (optional). Type @ and a name to mention someone, e.g. @Kimberly" aria-label="Task details" autocomplete="off" data-lpignore="true" data-1p-ignore></textarea>
                            <div class="ws-field-row">
                                <label class="ws-field-inline">Due date <input id="taskDue" type="date" min="{{ now('Asia/Manila')->toDateString() }}"></label>
                                <div class="ws-quick-dates" role="group" aria-label="Quick due dates">
                                    <button type="button" class="ws-chip-btn" data-due-offset="0">Today</button>
                                    <button type="button" class="ws-chip-btn" data-due-offset="1">Tomorrow</button>
                                    <button type="button" class="ws-chip-btn" data-due-offset="7">In a week</button>
                                    <button type="button" class="ws-chip-btn" data-due-offset="">No date</button>
                                </div>
                            </div>

                            <fieldset class="ws-fieldset">
                                <legend>Who can see it</legend>
                                <label class="ws-radio"><input type="radio" name="taskVisibility" value="assigned" checked>
                                    <span><strong>Assigned secretaries only</strong><small>Only the people chosen below see this task.</small></span></label>
                                <label class="ws-radio"><input type="radio" name="taskVisibility" value="everyone">
                                    <span><strong>Everyone</strong><small>Every secretary sees it; only the people below are assigned.</small></span></label>
                                <label class="ws-check-line" id="taskOthersRow" hidden><input type="checkbox" id="taskOthersComment"> Secretaries who are not assigned can comment</label>
                            </fieldset>

                            <fieldset class="ws-fieldset">
                                <legend>Assigned secretaries</legend>
                                <label class="ws-check-line"><input type="checkbox" id="taskSelectAll"> Select all</label>
                                <div class="ws-people" id="taskPeople"></div>
                            </fieldset>

                            <div class="ws-compose-actions">
                                <button type="button" class="ws-btn" id="taskCancel">Cancel</button>
                                <button type="submit" class="ws-btn primary" id="taskSubmit">Post task</button>
                            </div>
                        </form>
                    </div>
                @endif

                <div class="ws-toolbar" role="toolbar" aria-label="Show tasks">
                    <div class="ws-tabs" role="tablist" aria-label="Open or completed">
                        <button type="button" role="tab" data-view="open" aria-selected="true">Open <span class="ws-tab-count" data-view-count="open"></span></button>
                        <button type="button" role="tab" data-view="completed" aria-selected="false">Completed <span class="ws-tab-count" data-view-count="completed"></span></button>
                    </div>
                    <div class="ws-toolbar-right">
                        <label class="ws-search">
                            <svg viewBox="0 0 24 24" class="ws-icon" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
                            <input type="search" id="taskSearch" placeholder="Search tasks…" aria-label="Search tasks" autocomplete="off">
                            <span class="ws-kbd" aria-hidden="true">/</span>
                        </label>
                        <select id="taskSort" class="ws-select" aria-label="Sort tasks">
                            <option value="due">Due date</option>
                            <option value="new">Newest</option>
                            <option value="active">Recently active</option>
                        </select>
                    </div>
                </div>
                <div class="ws-filters" id="taskFilters" role="group" aria-label="Filter open tasks">
                    <button type="button" class="ws-filter" data-filter="all" aria-pressed="true">All</button>
                    <button type="button" class="ws-filter" data-filter="mine" aria-pressed="false">{{ $tasksIsHead ? 'Needs my confirmation' : 'Assigned to me' }}</button>
                    <button type="button" class="ws-filter" data-filter="overdue" aria-pressed="false">Overdue</button>
                    <button type="button" class="ws-filter" data-filter="soon" aria-pressed="false">Due in 2 days</button>
                    <button type="button" class="ws-filter" data-filter="waiting" aria-pressed="false">Waiting for confirmation</button>
                </div>

                <p class="ws-status" id="taskStatusLine" role="status" aria-live="polite"></p>
                <div class="ws-feed" id="taskFeed" aria-live="polite"></div>
            </div>

            <aside class="tasks-side" aria-label="Due dates and activity">
                <section class="ws-card ws-side-card" id="taskCritical" aria-live="polite"></section>
                <section class="ws-card ws-side-card" id="taskCalendar" aria-label="Due date calendar"></section>
                <section class="ws-card ws-side-card" id="taskActivity" aria-label="Recent activity"></section>
            </aside>
        </div>
    </div>
</div>
@endsection

@push('scripts')
@vite('resources/js/tasks.js')
@endpush
