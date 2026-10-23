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
    /* The Tasks page reuses the workspace look as a full page instead of a chat-window panel. */
    .tasks-page.workspace { height: auto; min-height: calc(100vh - var(--bar-height, 56px) - 4rem); overflow: visible; }
    .tasks-hero { display: flex; align-items: flex-end; justify-content: space-between; gap: 1rem; flex-wrap: wrap; padding: 1.25rem 1.25rem .25rem; max-width: 680px; width: 100%; margin: 0 auto; }
    .tasks-hero h1 { margin: 0; font-size: clamp(1.5rem, 3vw, 1.9rem); font-weight: 750; letter-spacing: -.03em; }
    .tasks-hero p { margin: .3rem 0 0; color: var(--ws-muted); font-size: .88rem; line-height: 1.5; }
    .tasks-page .ws-column { padding-top: .5rem; }
    .tasks-hero { max-width: 1040px; }

    /* Feed in the middle, due-date column on the right (stacks above the feed on narrow screens) */
    .tasks-layout { display: grid; grid-template-columns: minmax(0, 680px) 320px; justify-content: center; gap: 1rem; padding: 0 .75rem 1.5rem; }
    .tasks-layout .ws-column { margin: 0; padding-left: 0; padding-right: 0; }
    .tasks-side { position: sticky; top: calc(var(--bar-height, 56px) + .75rem); display: grid; align-content: start; gap: .75rem; padding-top: .5rem; }
    @media (max-width: 1100px) {
        .tasks-layout { grid-template-columns: minmax(0, 680px); }
        .tasks-side { position: static; order: -1; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); }
    }
</style>
@endpush

@section('content')
<div class="workspace tasks-page" id="tasksApp"
    data-csrf="{{ csrf_token() }}"
    data-is-head="{{ $tasksIsHead ? 'true' : 'false' }}"
    data-user-name="{{ $workspaceName }}"
    data-user-avatar="{{ $workspaceAvatar }}"
    data-task-list-url="{{ route('assigned-tasks.index') }}"
    data-task-seen-url="{{ route('assigned-tasks.seen') }}"
    data-task-secretaries-url="{{ route('assigned-tasks.secretaries') }}"
    data-task-create-url="{{ route('assigned-tasks.store') }}"
    data-task-update-url="{{ route('assigned-tasks.update', ['task' => '__ID__']) }}"
    data-task-delete-url="{{ route('assigned-tasks.destroy', ['task' => '__ID__']) }}"
    data-task-comment-url="{{ route('assigned-tasks.comment', ['task' => '__ID__']) }}"
    data-task-react-url="{{ route('assigned-tasks.react', ['task' => '__ID__']) }}"
    data-task-complete-url="{{ route('assigned-tasks.complete', ['task' => '__ID__']) }}"
    data-task-reopen-url="{{ route('assigned-tasks.reopen', ['task' => '__ID__']) }}"
    data-task-confirm-url="{{ route('assigned-tasks.confirm', ['task' => '__ID__']) }}">
    <header class="tasks-hero">
        <div>
            <h1>Tasks</h1>
            <p>
                @if ($tasksIsHead)
                    Assign work to secretaries, choose who sees it and what they can do, and confirm finished tasks.
                @else
                    Work the Head Secretary shared with you. Comment, and mark a task done when it's finished.
                @endif
            </p>
        </div>
    </header>
    <div class="tasks-layout">
    <div class="ws-column">
        @if ($tasksIsHead)
            <div class="ws-card ws-composer" id="taskComposer">
                <div class="ws-composer-row">
                    @include('messages.partials.workspace-avatar')
                    <button type="button" class="ws-composer-open" id="taskComposeToggle" aria-expanded="false" aria-controls="taskComposeForm">
                        What should the secretaries do, {{ $workspaceFirstName }}?
                    </button>
                </div>
                <form class="ws-compose-form" id="taskComposeForm" hidden>
                    <h3 id="taskComposeHeading">Assign a task</h3>
                    <input id="taskTitle" type="text" maxlength="200" placeholder="Task title" aria-label="Task title" required>
                    <textarea id="taskBody" rows="3" maxlength="5000" placeholder="Details (optional)" aria-label="Task details"></textarea>
                    <label class="ws-field-inline">Due date <input id="taskDue" type="date" min="{{ now('Asia/Manila')->toDateString() }}"></label>

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
        <p class="ws-status" id="taskStatusLine" role="status" aria-live="polite"></p>
        <div class="ws-feed" id="taskFeed" aria-live="polite"></div>
    </div>
    <aside class="tasks-side" aria-label="Due dates">
        <section class="ws-card ws-side-card" id="taskCritical" aria-live="polite"></section>
        <section class="ws-card ws-side-card" id="taskCalendar" aria-label="Due date calendar"></section>
    </aside>
    </div>
</div>
@endsection

@push('scripts')
@vite('resources/js/tasks.js')
@endpush
