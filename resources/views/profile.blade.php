@extends('layouts.app')

@section('title', 'My Profile')

@section('content')
<style>
  :root {
    --p-accent: var(--accent, #0a6bd6);
    --p-accent-hover: var(--accent-hover, #0a4a8a);
    --p-navy: #101828;
    --p-ink-soft: var(--text-muted, #64748b);
    --p-surface: var(--surface, #ffffff);
    --p-border: var(--border, #e3e8ee);
    --p-bg: var(--bg, #f6f8fa);
}

.profile-page {
    font-family: -apple-system, 'Helvetica Neue', Arial, sans-serif;
    max-width: 720px;
    margin: 0 auto;
}

    /* ===== Profile header card (cover + avatar, Meta-style) ===== */
    .profile-card {
        background: var(--p-surface);
        border: 1px solid var(--p-border);
        border-radius: 16px;
        overflow: hidden;
        margin-bottom: 1.5rem;
    }

    .profile-cover {
        height: 150px;
        background: #101828;
    }

    .profile-body {
        text-align: center;
        padding: 0 1.5rem 1.8rem;
    }

    .profile-avatar-wrap {
        margin-top: -64px;
        margin-bottom: 0.9rem;
    }

    .profile-avatar {
        width: 128px;
        height: 128px;
        border-radius: 50%;
        border: 4px solid var(--p-surface);
        object-fit: cover;
        background: var(--p-border);
        display: inline-block;
    }

    .profile-name {
        font-size: 1.4rem;
        font-weight: 700;
        color: var(--p-navy);
        margin: 0 0 0.2rem;
    }

    .profile-role {
        font-size: 0.9rem;
        color: var(--p-ink-soft);
        margin: 0 0 1.2rem;
    }

    .btn {
        font-family: inherit;
        font-size: 0.92rem;
        font-weight: 600;
        border-radius: 8px;
        padding: 0.6rem 1.3rem;
        cursor: pointer;
        border: none;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        transition: background 0.15s ease, border-color 0.15s ease, color 0.15s ease;
    }
    .btn-primary { background: var(--p-accent); color: #fff; }
    .btn-primary:hover { background: var(--p-accent-hover); }
    .btn-ghost { background: var(--p-bg); color: var(--p-navy); border: 1px solid var(--p-border); }
    .btn-ghost:hover { background: var(--p-border); }

    /* ===== Details card ===== */
    .details-card {
        background: var(--p-surface);
        border: 1px solid var(--p-border);
        border-radius: 16px;
        padding: 1.4rem 1.6rem;
    }
    .details-card h3 {
        font-size: 1rem;
        font-weight: 700;
        color: var(--p-navy);
        margin: 0 0 1rem;
    }
    .detail-row {
        display: flex;
        justify-content: space-between;
        padding: 0.7rem 0;
        border-bottom: 1px solid var(--p-border);
        font-size: 0.92rem;
    }
    .detail-row:last-child { border-bottom: none; }
    .detail-row .label { color: var(--p-ink-soft); }
    .detail-row .value { color: var(--p-navy); font-weight: 600; }
    .password-card {
        background: var(--p-surface);
        border: 1px solid var(--p-border);
        border-radius: 16px;
        padding: 1.4rem 1.6rem;
        margin-top: 1.5rem;
    }
    .password-card h2 { margin:0 0 .4rem; color:var(--p-navy); font-size:1rem; }
    .password-card p { margin:0 0 1rem; color:var(--p-ink-soft); font-size:.86rem; line-height:1.5; }
    .password-field { margin-bottom:1rem; }
    .password-field label { display:block; margin-bottom:.4rem; color:var(--p-navy); font-size:.85rem; font-weight:600; }
    .password-field input { width:100%; padding:.65rem .8rem; border:1px solid var(--p-border); border-radius:8px; background:var(--p-bg); color:var(--p-navy); font:inherit; }
    .password-error { margin:.35rem 0 0; color:#b42318; font-size:.8rem; }

    /* ===== Modal ===== */
    .modal-overlay {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(16, 24, 40, 0.55);
        align-items: center;
        justify-content: center;
        z-index: 100;
        padding: 1rem;
    }
    .modal-overlay.open { display: flex; }

    .modal-card {
        background: var(--p-surface);
        border-radius: 16px;
        width: 100%;
        max-width: 420px;
        max-height: 90vh;
        overflow-y: auto;
        box-shadow: 0 20px 50px rgba(16, 24, 40, 0.25);
    }

    .modal-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 1.1rem 1.4rem;
        border-bottom: 1px solid var(--p-border);
    }
    .modal-head h3 { margin: 0; font-size: 1.05rem; font-weight: 700; color: var(--p-navy); }
    .modal-close {
        background: var(--p-bg);
        border: none;
        width: 30px; height: 30px;
        border-radius: 50%;
        display: flex; align-items: center; justify-content: center;
        cursor: pointer;
        color: var(--p-ink-soft);
    }
    .modal-close:hover { background: var(--p-border); }

    .modal-body { padding: 1.4rem; }

    .photo-upload {
        display: flex;
        flex-direction: column;
        align-items: center;
        margin-bottom: 1.5rem;
    }
    .photo-upload-preview {
        position: relative;
        width: 104px;
        height: 104px;
        margin-bottom: 0.7rem;
    }
    .photo-upload-preview img {
        width: 100%;
        height: 100%;
        border-radius: 50%;
        object-fit: cover;
        background: var(--p-border);
        display: block;
    }
    .photo-upload-btn {
        position: absolute;
        bottom: -2px;
        right: -2px;
        width: 34px;
        height: 34px;
        border-radius: 50%;
        background: var(--p-accent);
        color: #fff;
        border: 3px solid var(--p-surface);
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
    }
    .photo-upload-btn:hover { background: var(--p-accent-hover); }
    .photo-upload input[type="file"] { display: none; }
    .photo-hint { font-size: 0.78rem; color: var(--p-ink-soft); }

    .field { margin-bottom: 1.1rem; }
    .field label {
        display: block;
        font-size: 0.85rem;
        font-weight: 600;
        color: var(--p-navy);
        margin-bottom: 0.4rem;
    }
    .field input[type="text"] {
        width: 100%;
        padding: 0.65rem 0.8rem;
        border-radius: 8px;
        border: 1px solid var(--p-border);
        font-size: 0.94rem;
        font-family: inherit;
        color: var(--p-navy);
        background: var(--p-bg);
    }
    .field input[type="text"]:focus {
        outline: none;
        border-color: var(--p-accent);
        background: var(--p-surface);
    }

    .modal-actions {
        display: flex;
        gap: 0.7rem;
        margin-top: 0.4rem;
    }
    .modal-actions .btn { flex: 1; justify-content: center; }
</style>

@php
    $currentFirstName = old('first_name', $user['first_name'] ?? 'Juan');
    $currentLastName  = old('last_name', $user['last_name'] ?? 'Dela Cruz');
    $currentPhoto     = !empty($user['profile_picture'])
        ? asset('storage/' . $user['profile_picture'])
        : 'https://ui-avatars.com/api/?name=' . urlencode($currentFirstName . ' ' . $currentLastName) . '&background=0A6BD6&color=fff&size=256';
@endphp

<div class="profile-page">

    <div class="profile-card">
        <div class="profile-cover"></div>
        <div class="profile-body">
            <div class="profile-avatar-wrap">
                <img class="profile-avatar" id="profileAvatarDisplay" src="{{ $currentPhoto }}" alt="Profile picture">
            </div>
            <h1 class="profile-name" id="profileNameDisplay">{{ $currentFirstName }} {{ $currentLastName }}</h1>
            <p class="profile-role">{{ $user['tungkulin'] ?? 'Secretary' }}</p>
            <button type="button" class="btn btn-primary" id="openEditProfile">
                <svg width="15" height="15" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M11.5 3.5l5 5L6 19H1v-5z"/></svg>
                Edit Profile
            </button>
        </div>
    </div>

    <div class="details-card">
        <h3>Account Details</h3>
        <div class="detail-row">
            <span class="label">First Name</span>
            <span class="value" id="detailFirstName">{{ $currentFirstName }}</span>
        </div>
        <div class="detail-row">
            <span class="label">Last Name</span>
            <span class="value" id="detailLastName">{{ $currentLastName }}</span>
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

    <section class="password-card" aria-labelledby="changePasswordHeading">
        <h2 id="changePasswordHeading">Change Password</h2>
        <p>Changing your password will sign out this account from all browsers and devices. You will need to log in again.</p>
        <form method="POST" action="{{ route('profile.password.update') }}">
            @csrf
            @method('PUT')
            <div class="password-field">
                <label for="currentPassword">Current password</label>
                <input id="currentPassword" type="password" name="current_password" autocomplete="current-password" required>
                @error('current_password')<p class="password-error">{{ $message }}</p>@enderror
            </div>
            <div class="password-field">
                <label for="newPassword">New password</label>
                <input id="newPassword" type="password" name="password" minlength="8" autocomplete="new-password" required>
                @include('components.password-strength', ['inputId' => 'newPassword'])
                @error('password')<p class="password-error">{{ $message }}</p>@enderror
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
<div class="modal-overlay" id="editProfileOverlay">
    <div class="modal-card">
        <div class="modal-head">
            <h3>Edit Profile</h3>
            <button type="button" class="modal-close" id="closeEditProfile" aria-label="Close">
                <svg width="16" height="16" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M5 5l10 10M15 5L5 15"/></svg>
            </button>
        </div>

        <form method="POST" action="{{ Route::has('profile.update') ? route('profile.update') : '#' }}" enctype="multipart/form-data" id="editProfileForm">
            @csrf
            @method('PUT')

            <div class="modal-body">

                <div class="photo-upload">
                    <div class="photo-upload-preview">
                        <img id="photoPreview" src="{{ $currentPhoto }}" alt="Profile photo preview">
                        <label class="photo-upload-btn" for="profilePhotoInput">
                            <svg width="15" height="15" viewBox="0 0 20 20" fill="none" stroke="#fff" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 7h2l1.5-2h5L14 7h2a1 1 0 0 1 1 1v7a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V8a1 1 0 0 1 1-1z"/><circle cx="10" cy="11.5" r="2.5"/></svg>
                        </label>
<input type="file" id="profilePhotoInput" name="picture" accept="image/*">                    </div>
                    <span class="photo-hint">Click the icon to upload a new photo</span>
                </div>

                <div class="field">
                    <label for="firstNameInput">First Name</label>
                    <input type="text" id="firstNameInput" name="first_name" value="{{ $currentFirstName }}">
                </div>

                <div class="field">
                    <label for="lastNameInput">Last Name</label>
                    <input type="text" id="lastNameInput" name="last_name" value="{{ $currentLastName }}">
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
        const overlay = document.getElementById('editProfileOverlay');
        const openBtn = document.getElementById('openEditProfile');
        const closeBtn = document.getElementById('closeEditProfile');
        const discardBtn = document.getElementById('discardEditProfile');

        const firstNameInput = document.getElementById('firstNameInput');
        const lastNameInput = document.getElementById('lastNameInput');
        const photoInput = document.getElementById('profilePhotoInput');
        const photoPreview = document.getElementById('photoPreview');

        // Remember original values so Discard can restore them
        const original = {
            firstName: firstNameInput.value,
            lastName: lastNameInput.value,
            photoSrc: photoPreview.src,
        };

        function openModal() {
            overlay.classList.add('open');
        }

        function closeModal() {
            overlay.classList.remove('open');
            // Reset the form back to original values whenever the modal closes
            // without a successful save (covers both the X button and Discard).
            firstNameInput.value = original.firstName;
            lastNameInput.value = original.lastName;
            photoPreview.src = original.photoSrc;
            photoInput.value = '';
        }

        openBtn?.addEventListener('click', openModal);
        closeBtn?.addEventListener('click', closeModal);
        discardBtn?.addEventListener('click', closeModal);

        overlay?.addEventListener('click', (e) => {
            if (e.target === overlay) closeModal();
        });

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && overlay.classList.contains('open')) closeModal();
        });

        // Live preview of the chosen photo before upload
        photoInput?.addEventListener('change', () => {
            const file = photoInput.files && photoInput.files[0];
            if (!file) return;
            const reader = new FileReader();
            reader.onload = (e) => { photoPreview.src = e.target.result; };
            reader.readAsDataURL(file);
        });

        // On successful save, sync the header card + details without a full reload
        // (only runs if you wire this up with fetch/AJAX instead of a normal submit;
        // for a normal form submit, the page will simply reload with new data).
    })();
</script>
@endsection