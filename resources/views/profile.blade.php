@extends('layouts.app')

@section('title', 'My Profile')

@section('content')
<style>
  /* ===== Theme tokens: light is the default, dark overrides ===== */
  :root {
    --p-surface: #ffffff;
    --p-raised: #f4f4f5;
    --p-line: #e4e4e7;
    --p-line-strong: #d4d4d8;
    --p-ink: #09090b;
    --p-ink-soft: #6b6b73;
    --p-on-ink: #ffffff;
    --p-ink-hover: #3f3f46;
    --p-danger: #dc2626;
    --p-backdrop: rgba(0, 0, 0, .5);
  }

  :root[data-profile-theme="dark"],
  .dark,
  .dark-mode,
  .theme-dark,
  [data-theme="dark"],
  [data-bs-theme="dark"],
  [data-mode="dark"],
  [data-color-scheme="dark"] {
    --p-surface: #000000;
    --p-raised: #0d0d0d;
    --p-line: #262626;
    --p-line-strong: #3a3a3a;
    --p-ink: #ffffff;
    --p-ink-soft: #9a9a9a;
    --p-on-ink: #000000;
    --p-ink-hover: #d9d9d9;
    --p-danger: #f87171;
    --p-backdrop: rgba(0, 0, 0, .8);
  }

  :root[data-profile-theme="light"] {
    --p-surface: #ffffff;
    --p-raised: #f4f4f5;
    --p-line: #e4e4e7;
    --p-line-strong: #d4d4d8;
    --p-ink: #09090b;
    --p-ink-soft: #6b6b73;
    --p-on-ink: #ffffff;
    --p-ink-hover: #3f3f46;
    --p-danger: #dc2626;
    --p-backdrop: rgba(0, 0, 0, .5);
  }

  .profile-page {
    font-family: -apple-system, 'Helvetica Neue', Arial, sans-serif;
    max-width: 860px;
    margin: 0 auto;
    padding: 2.5rem 0 4rem;
    color: var(--p-ink);
  }

  /* ===== Page heading ===== */
  .page-head { margin-bottom: 2rem; }
  .page-eyebrow { font-size: .9rem; color: var(--p-ink-soft); margin: 0 0 .4rem; }
  .page-title {
    font-size: 2.6rem;
    font-weight: 700;
    letter-spacing: -0.03em;
    line-height: 1.05;
    margin: 0 0 .6rem;
    color: var(--p-ink);
  }
  .page-sub { font-size: 1rem; color: var(--p-ink-soft); margin: 0; }

  /* ===== Section (line separated, no cards) ===== */
  .section { border-top: 1px solid var(--p-line); }
  .section-pad { padding: 1.8rem 0; }
  .section h2 {
    font-size: 1.05rem;
    font-weight: 700;
    color: var(--p-ink);
    margin: 0 0 .35rem;
  }
  .section .section-desc {
    font-size: .88rem;
    color: var(--p-ink-soft);
    line-height: 1.5;
    margin: 0 0 1.4rem;
  }

  /* ===== Identity row ===== */
  .identity {
    display: flex;
    align-items: center;
    gap: 1.4rem;
    padding: 1.8rem 0;
  }
  .profile-avatar {
    width: 96px;
    height: 96px;
    border-radius: 50%;
    border: 1px solid var(--p-line-strong);
    object-fit: cover;
    background: var(--p-raised);
    flex-shrink: 0;
  }
  .identity-text { flex: 1; min-width: 0; }
  .profile-name {
    font-size: 1.6rem;
    font-weight: 700;
    letter-spacing: -0.02em;
    color: var(--p-ink);
    margin: 0 0 .2rem;
    word-break: break-word;
  }
  .profile-role { font-size: .92rem; color: var(--p-ink-soft); margin: 0; }

  /* ===== Buttons ===== */
  .btn {
    font-family: inherit;
    font-size: .92rem;
    font-weight: 600;
    border-radius: 2px;
    padding: .65rem 1.3rem;
    cursor: pointer;
    border: 1px solid transparent;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: .5rem;
    transition: background .15s ease, border-color .15s ease, color .15s ease;
  }
  .btn:focus-visible { outline: 2px solid var(--p-ink); outline-offset: 2px; }
  .btn[disabled] { opacity: .55; cursor: not-allowed; }
  .btn-primary { background: var(--p-ink); color: var(--p-on-ink); border-color: var(--p-ink); }
  .btn-primary:hover:not([disabled]) { background: var(--p-ink-hover); border-color: var(--p-ink-hover); }
  .btn-ghost { background: transparent; color: var(--p-ink); border-color: var(--p-line-strong); }
  .btn-ghost:hover:not([disabled]) { background: var(--p-raised); border-color: var(--p-ink-soft); }

  /* ===== Account details (ruled list) ===== */
  .detail-row {
    display: flex;
    justify-content: space-between;
    gap: 1rem;
    padding: .9rem 0;
    border-top: 1px solid var(--p-line);
    font-size: .94rem;
  }
  .detail-row:first-child { border-top: none; }
  .detail-row .label { color: var(--p-ink-soft); flex-shrink: 0; }
  .detail-row .value { color: var(--p-ink); font-weight: 600; text-align: right; min-width: 0; overflow-wrap: anywhere; }

  /* ===== Password ===== */
  .password-field { margin-bottom: 1.1rem; max-width: 420px; }
  .password-field label {
    display: block;
    margin-bottom: .4rem;
    color: var(--p-ink);
    font-size: .85rem;
    font-weight: 600;
  }
  .password-field input {
    width: 100%;
    padding: .7rem .8rem;
    border: 1px solid var(--p-line-strong);
    border-radius: 2px;
    background: transparent;
    color: var(--p-ink);
    font: inherit;
    transition: border-color .15s ease;
  }
  .password-field input:focus { outline: none; border-color: var(--p-ink); }
  .password-field input[aria-invalid="true"] { border-color: var(--p-danger); }
  .password-error { margin: .35rem 0 0; color: var(--p-danger); font-size: .8rem; }

  /* ===== Modal ===== */
  .modal-overlay {
    display: none;
    position: fixed;
    inset: 0;
    background: var(--p-backdrop);
    align-items: center;
    justify-content: center;
    z-index: 100;
    padding: 1rem;
  }
  .modal-overlay.open { display: flex; }

  .modal-card {
    background: var(--p-surface);
    color: var(--p-ink);
    border: 1px solid var(--p-line-strong);
    border-radius: 2px;
    width: 100%;
    max-width: 440px;
    max-height: 90vh;
    overflow-y: auto;
  }

  .modal-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 1.1rem 1.4rem;
    border-bottom: 1px solid var(--p-line);
  }
  .modal-head h3 { margin: 0; font-size: 1.05rem; font-weight: 700; color: var(--p-ink); }
  .modal-close {
    background: transparent;
    border: 1px solid var(--p-line-strong);
    width: 32px; height: 32px;
    border-radius: 2px;
    display: flex; align-items: center; justify-content: center;
    cursor: pointer;
    color: var(--p-ink-soft);
    transition: color .15s ease, border-color .15s ease;
  }
  .modal-close:hover { color: var(--p-ink); border-color: var(--p-ink-soft); }
  .modal-close:focus-visible { outline: 2px solid var(--p-ink); outline-offset: 2px; }

  .modal-body { padding: 1.4rem; }

  .photo-upload {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding-bottom: 1.5rem;
    margin-bottom: 1.5rem;
    border-bottom: 1px solid var(--p-line);
  }
  .photo-upload-preview {
    position: relative;
    width: 104px;
    height: 104px;
    margin-bottom: .7rem;
  }
  .photo-upload-preview img {
    width: 100%;
    height: 100%;
    border-radius: 50%;
    object-fit: cover;
    background: var(--p-raised);
    border: 1px solid var(--p-line-strong);
    display: block;
  }
  .photo-upload-btn {
    position: absolute;
    bottom: -2px;
    right: -2px;
    width: 34px;
    height: 34px;
    border-radius: 50%;
    background: var(--p-ink);
    color: var(--p-on-ink);
    border: 3px solid var(--p-surface);
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: background .15s ease;
  }
  .photo-upload-btn:hover { background: var(--p-ink-hover); }
  .photo-upload-btn:has(+ input:focus-visible) { outline: 2px solid var(--p-ink); outline-offset: 2px; }
  .photo-upload input[type="file"] {
    position: absolute;
    width: 1px;
    height: 1px;
    opacity: 0;
    overflow: hidden;
    pointer-events: none;
  }
  .photo-hint { font-size: .78rem; color: var(--p-ink-soft); text-align: center; overflow-wrap: anywhere; }
  .photo-warning { font-size: .78rem; color: var(--p-ink); margin: .4rem 0 0; text-align: center; }
  .photo-warning:empty { display: none; }

  .field { margin-bottom: 1.1rem; }
  .field label {
    display: block;
    font-size: .85rem;
    font-weight: 600;
    color: var(--p-ink);
    margin-bottom: .4rem;
  }
  .field input[type="text"] {
    width: 100%;
    padding: .7rem .8rem;
    border-radius: 2px;
    border: 1px solid var(--p-line-strong);
    font-size: .94rem;
    font-family: inherit;
    color: var(--p-ink);
    background: transparent;
    transition: border-color .15s ease;
  }
  .field input[type="text"]:focus { outline: none; border-color: var(--p-ink); }
  .field input[aria-invalid="true"] { border-color: var(--p-danger); }
  .field-error { margin: .35rem 0 0; color: var(--p-danger); font-size: .8rem; }

  .modal-actions {
    display: flex;
    gap: .7rem;
    margin-top: 1.6rem;
    padding-top: 1.4rem;
    border-top: 1px solid var(--p-line);
  }
  .modal-actions .btn { flex: 1; }

  @media (max-width: 560px) {
    .page-title { font-size: 2rem; }
    .identity { flex-direction: column; align-items: flex-start; }
    .detail-row { flex-direction: column; gap: .2rem; }
    .detail-row .value { text-align: left; }
  }
</style>

<script>
  /* Follow whatever theme the layout is using (light/dark toggle), without
     depending on a specific class name. Runs before the page paints. */
  (function () {
    const root = document.documentElement;

    function parseRgb(value) {
      const m = value && value.match(/rgba?\(([^)]+)\)/);
      if (!m) return null;
      const p = m[1].split(',').map((s) => parseFloat(s));
      return { r: p[0], g: p[1], b: p[2], a: p.length > 3 ? p[3] : 1 };
    }

    function luminance(c) {
      return (0.2126 * c.r + 0.7152 * c.g + 0.0722 * c.b) / 255;
    }

    function detect() {
      let el = document.body;
      while (el) {
        const c = parseRgb(getComputedStyle(el).backgroundColor);
        if (c && c.a > 0.5) return luminance(c) < 0.5 ? 'dark' : 'light';
        el = el.parentElement;
      }
      const text = parseRgb(getComputedStyle(document.body).color);
      if (text) return luminance(text) > 0.5 ? 'dark' : 'light';
      return 'light';
    }

    function sync() {
      const next = detect();
      if (root.getAttribute('data-profile-theme') !== next) {
        root.setAttribute('data-profile-theme', next);
      }
    }

    sync();
    document.addEventListener('DOMContentLoaded', sync);
    window.addEventListener('load', sync);

    const watch = { attributes: true, attributeFilter: ['class', 'style', 'data-theme', 'data-bs-theme', 'data-mode', 'data-color-scheme'] };
    new MutationObserver(sync).observe(root, watch);
    document.addEventListener('DOMContentLoaded', function () {
      new MutationObserver(sync).observe(document.body, watch);
    });

    // Theme toggles often change styles right after a click.
    document.addEventListener('click', function () { setTimeout(sync, 50); setTimeout(sync, 300); });
    window.addEventListener('storage', sync);
    if (window.matchMedia) {
      const mq = window.matchMedia('(prefers-color-scheme: dark)');
      if (mq.addEventListener) mq.addEventListener('change', sync);
    }
  })();
</script>

@php
    // Saved values (what is actually stored) vs. values the user typed (old input after a failed save).
    $savedFirstName = $user['first_name'] ?? 'Juan';
    $savedLastName  = $user['last_name'] ?? 'Dela Cruz';
    $inputFirstName = old('first_name', $savedFirstName);
    $inputLastName  = old('last_name', $savedLastName);

    // Local initials avatar: no external request, no name sent to a third party.
    $initials = mb_strtoupper(mb_substr(trim($savedFirstName), 0, 1) . mb_substr(trim($savedLastName), 0, 1));
    $fallbackAvatar = 'data:image/svg+xml;charset=utf-8,' . rawurlencode(
        '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 96 96">'
        . '<rect width="96" height="96" fill="#1a1a1a"/>'
        . '<text x="50%" y="50%" dy=".35em" text-anchor="middle" font-family="Helvetica, Arial, sans-serif" font-size="36" font-weight="700" fill="#ffffff">'
        . e($initials)
        . '</text></svg>'
    );

    $currentPhoto = \App\Http\Controllers\ProfilePictureController::urlFor($user['id'] ?? null, $user['profile_picture'] ?? null)
        ?? $fallbackAvatar;

    // Reopen the modal when the profile update failed validation.
    $profileHasErrors = $errors->hasAny(['first_name', 'last_name', 'picture']);
@endphp

<div class="profile-page">

    <header class="page-head">
        <p class="page-eyebrow">Account</p>
        <h1 class="page-title">My Profile</h1>
        <p class="page-sub">Manage your name, photo, and password.</p>
    </header>

    {{-- Identity --}}
    <section class="section">
        <div class="identity">
            <img class="profile-avatar" id="profileAvatarDisplay" src="{{ $currentPhoto }}" width="96" height="96" decoding="async" data-fallback="{{ $fallbackAvatar }}" onerror="this.onerror=null;this.src=this.dataset.fallback;" alt="Profile picture">
            <div class="identity-text">
                <h2 class="profile-name" id="profileNameDisplay">{{ $savedFirstName }} {{ $savedLastName }}</h2>
                <p class="profile-role">{{ $user['tungkulin'] ?? 'Secretary' }}</p>
            </div>
            <button type="button" class="btn btn-primary" id="openEditProfile" aria-haspopup="dialog">
                <svg width="15" height="15" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M11.5 3.5l5 5L6 19H1v-5z"/></svg>
                Edit Profile
            </button>
        </div>
    </section>

    {{-- Account details --}}
    <section class="section section-pad">
        <h2>Account Details</h2>
        <p class="section-desc">Your name, email, and role in the system. Email and role are read-only.</p>
        <div class="detail-list">
            <div class="detail-row">
                <span class="label">First Name</span>
                <span class="value" id="detailFirstName">{{ $savedFirstName }}</span>
            </div>
            <div class="detail-row">
                <span class="label">Last Name</span>
                <span class="value" id="detailLastName">{{ $savedLastName }}</span>
            </div>
            <div class="detail-row">
                <span class="label">Email</span>
                <span class="value">{{ $user['gmail'] ?? 'user@example.com' }}</span>
            </div>
            <div class="detail-row">
                <span class="label">Role</span>
                <span class="value">{{ $user['tungkulin'] ?? 'Secretary' }}</span>
            </div>
        </div>
    </section>

    {{-- Change password --}}
    <section class="section section-pad" aria-labelledby="changePasswordHeading">
        <h2 id="changePasswordHeading">Change Password</h2>
        <p class="section-desc">Changing your password will sign out this account from all browsers and devices. You will need to log in again.</p>
        <form method="POST" action="{{ route('profile.password.update') }}" data-lock-submit data-pending-label="Changing password…">
            @csrf
            @method('PUT')
            <div class="password-field">
                <label for="currentPassword">Current password</label>
                <input id="currentPassword" type="password" name="current_password" autocomplete="current-password" required @error('current_password') aria-invalid="true" aria-describedby="currentPasswordError" @enderror>
                @error('current_password')<p class="password-error" id="currentPasswordError">{{ $message }}</p>@enderror
            </div>
            <div class="password-field">
                <label for="newPassword">New password</label>
                <input id="newPassword" type="password" name="password" minlength="8" autocomplete="new-password" required @error('password') aria-invalid="true" aria-describedby="newPasswordError" @enderror>
                @include('components.password-strength', ['inputId' => 'newPassword'])
                @error('password')<p class="password-error" id="newPasswordError">{{ $message }}</p>@enderror
            </div>
            <div class="password-field">
                <label for="newPasswordConfirmation">Confirm new password</label>
                <input id="newPasswordConfirmation" type="password" name="password_confirmation" minlength="8" autocomplete="new-password" required>
            </div>
            <button type="submit" class="btn btn-primary">Change Password</button>
        </form>
    </section>

</div>

<!-- ===== Edit Profile Modal ===== -->
<div class="modal-overlay {{ $profileHasErrors ? 'open' : '' }}" id="editProfileOverlay">
    <div class="modal-card" role="dialog" aria-modal="true" aria-labelledby="editProfileTitle">
        <div class="modal-head">
            <h3 id="editProfileTitle">Edit Profile</h3>
            <button type="button" class="modal-close" id="closeEditProfile" aria-label="Close">
                <svg width="16" height="16" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><path d="M5 5l10 10M15 5L5 15"/></svg>
            </button>
        </div>

        <form method="POST"
              action="{{ Route::has('profile.update') ? route('profile.update') : '#' }}"
              enctype="multipart/form-data"
              id="editProfileForm"
              data-lock-submit
              data-pending-label="Saving…"
              data-first-name="{{ $savedFirstName }}"
              data-last-name="{{ $savedLastName }}">
            @csrf
            @method('PUT')

            <div class="modal-body">

                <div class="photo-upload">
                    <div class="photo-upload-preview">
                        <img id="photoPreview" src="{{ $currentPhoto }}" width="104" height="104" decoding="async" data-fallback="{{ $fallbackAvatar }}" onerror="this.onerror=null;this.src=this.dataset.fallback;" alt="Profile photo preview">
                        <label class="photo-upload-btn" for="profilePhotoInput" title="Upload photo">
                            <span style="position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;">Upload new photo</span>
                            <svg width="15" height="15" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 7h2l1.5-2h5L14 7h2a1 1 0 0 1 1 1v7a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V8a1 1 0 0 1 1-1z"/><circle cx="10" cy="11.5" r="2.5"/></svg>
                        </label>
                        <input type="file" id="profilePhotoInput" name="picture" accept="image/*" @error('picture') aria-invalid="true" aria-describedby="photoStatus photoServerError" @else aria-describedby="photoStatus" @enderror>
                    </div>
                    <span class="photo-hint" id="photoHint">Click the icon to upload a new photo</span>
                    <p class="photo-warning" id="photoStatus" role="status" aria-live="polite"></p>
                    @error('picture')<p class="field-error" id="photoServerError" data-server-error>{{ $message }}</p>@enderror
                </div>

                <div class="field">
                    <label for="firstNameInput">First Name</label>
                    <input type="text" id="firstNameInput" name="first_name" value="{{ $inputFirstName }}" @error('first_name') aria-invalid="true" aria-describedby="firstNameError" @enderror>
                    @error('first_name')<p class="field-error" id="firstNameError" data-server-error>{{ $message }}</p>@enderror
                </div>

                <div class="field">
                    <label for="lastNameInput">Last Name</label>
                    <input type="text" id="lastNameInput" name="last_name" value="{{ $inputLastName }}" @error('last_name') aria-invalid="true" aria-describedby="lastNameError" @enderror>
                    @error('last_name')<p class="field-error" id="lastNameError" data-server-error>{{ $message }}</p>@enderror
                </div>

                <div class="modal-actions">
                    <button type="button" class="btn btn-ghost" id="discardEditProfile">Discard</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>

            </div>
        </form>
    </div>
</div>

<script>
    (function () {
        const page = document.querySelector('.profile-page');
        const overlay = document.getElementById('editProfileOverlay');
        const card = overlay.querySelector('.modal-card');
        const openBtn = document.getElementById('openEditProfile');
        const closeBtn = document.getElementById('closeEditProfile');
        const discardBtn = document.getElementById('discardEditProfile');
        const form = document.getElementById('editProfileForm');

        const firstNameInput = document.getElementById('firstNameInput');
        const lastNameInput = document.getElementById('lastNameInput');
        const photoInput = document.getElementById('profilePhotoInput');
        const photoPreview = document.getElementById('photoPreview');
        const photoStatus = document.getElementById('photoStatus');
        const photoHint = document.getElementById('photoHint');

        // Soft client-side hint only. The server remains the source of truth for limits.
        const PICTURE_WARN_BYTES = 2 * 1024 * 1024;
        const defaultHint = photoHint.textContent;

        // Saved values (from the database), not the values typed before a failed save.
        const saved = {
            firstName: form.dataset.firstName,
            lastName: form.dataset.lastName,
            photoSrc: photoPreview.getAttribute('src'),
        };

        let previewObjectUrl = null;

        function revokePreview() {
            if (previewObjectUrl) {
                URL.revokeObjectURL(previewObjectUrl);
                previewObjectUrl = null;
            }
        }

        function focusables() {
            return Array.from(card.querySelectorAll(
                'a[href], button:not([disabled]), input:not([disabled]):not([type="hidden"]):not([type="file"]), label.photo-upload-btn, [tabindex]:not([tabindex="-1"])'
            )).filter((el) => el.offsetParent !== null);
        }

        function setModalState(isOpen) {
            if (isOpen) {
                overlay.classList.add('open');
                if (page) page.inert = true;
                document.body.style.overflow = 'hidden';
            } else {
                overlay.classList.remove('open');
                if (page) page.inert = false;
                document.body.style.overflow = '';
            }
        }

        function openModal() {
            setModalState(true);
            const firstInvalid = card.querySelector('[aria-invalid="true"]');
            (firstInvalid || firstNameInput).focus();
        }

        function closeModal() {
            setModalState(false);

            // Restore saved values whenever the modal closes without a successful save
            // (covers the X button, Discard, Escape, and the backdrop).
            firstNameInput.value = saved.firstName;
            lastNameInput.value = saved.lastName;
            photoPreview.src = saved.photoSrc;
            photoInput.value = '';
            revokePreview();
            photoStatus.textContent = '';
            photoHint.textContent = defaultHint;

            // Remove stale server errors left over from a failed save.
            card.querySelectorAll('[data-server-error]').forEach((el) => el.remove());
            card.querySelectorAll('[aria-invalid="true"]').forEach((el) => {
                el.removeAttribute('aria-invalid');
                el.removeAttribute('aria-describedby');
            });

            openBtn?.focus();
        }

        openBtn?.addEventListener('click', openModal);
        closeBtn?.addEventListener('click', closeModal);
        discardBtn?.addEventListener('click', closeModal);

        overlay?.addEventListener('click', (e) => {
            if (e.target === overlay) closeModal();
        });

        document.addEventListener('keydown', (e) => {
            if (!overlay.classList.contains('open')) return;

            if (e.key === 'Escape') {
                closeModal();
                return;
            }

            // Keep Tab / Shift+Tab inside the dialog.
            if (e.key === 'Tab') {
                const items = focusables();
                if (!items.length) return;
                const first = items[0];
                const last = items[items.length - 1];
                if (e.shiftKey && document.activeElement === first) {
                    e.preventDefault();
                    last.focus();
                } else if (!e.shiftKey && document.activeElement === last) {
                    e.preventDefault();
                    first.focus();
                }
            }
        });

        // Live preview of the chosen photo before upload
        photoInput?.addEventListener('change', () => {
            const file = photoInput.files && photoInput.files[0];
            photoStatus.textContent = '';

            if (!file) {
                photoPreview.src = saved.photoSrc;
                photoHint.textContent = defaultHint;
                revokePreview();
                return;
            }

            if (!file.type.startsWith('image/')) {
                photoInput.value = '';
                photoPreview.src = saved.photoSrc;
                photoHint.textContent = defaultHint;
                revokePreview();
                photoStatus.textContent = 'That file is not an image. Choose a JPG, PNG, or similar image.';
                return;
            }

            revokePreview();
            previewObjectUrl = URL.createObjectURL(file);
            photoPreview.src = previewObjectUrl;
            photoHint.textContent = file.name + ' (not saved yet)';

            if (file.size > PICTURE_WARN_BYTES) {
                const mb = (file.size / (1024 * 1024)).toFixed(1);
                photoStatus.textContent = 'This image is ' + mb + ' MB. Large files may be rejected when you save.';
            }
        });

        // Prevent double submits and show a pending label.
        // Button labels are restored if the page is restored from the back/forward cache.
        const lockedForms = document.querySelectorAll('form[data-lock-submit]');

        lockedForms.forEach((f) => {
            const submitBtn = f.querySelector('button[type="submit"]');
            if (!submitBtn) return;
            const idleLabel = submitBtn.textContent;

            f.addEventListener('submit', (e) => {
                if (f.dataset.submitting === '1') {
                    e.preventDefault();
                    return;
                }
                f.dataset.submitting = '1';
                submitBtn.disabled = true;
                submitBtn.textContent = f.dataset.pendingLabel || 'Saving…';
            });

            window.addEventListener('pageshow', (e) => {
                if (!e.persisted) return;
                delete f.dataset.submitting;
                submitBtn.disabled = false;
                submitBtn.textContent = idleLabel;
            });
        });

        window.addEventListener('beforeunload', revokePreview);

        // If the server re-rendered the page with errors, the modal is already open.
        if (overlay.classList.contains('open')) {
            openModal();
        }
    })();
</script>
@endsection