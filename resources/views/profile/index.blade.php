<x-layouts::accounting title="My Profile">
    <section class="hg-page-header">
        <div>
            <h1>My Profile</h1>
            <p>Review your account details, update your profile picture, and securely change your password.</p>
        </div>
        <span class="hg-status {{ $profileUser->isAccountActive() ? 'active' : 'inactive' }}">
            {{ $profileUser->accountStatusLabel() }}
        </span>
    </section>

    @if(session('profile_status'))
        <div class="hg-alert hg-alert-success">{{ session('profile_status') }}</div>
    @endif

    @if(session('password_status'))
        <div class="hg-alert hg-alert-success">{{ session('password_status') }}</div>
    @endif

    @php
        $photoPath = trim((string) ($profileUser->profile_photo_path ?? ''));
        $photoUrl = $photoPath !== ''
            ? route('accounting.profile.photo', ['v' => optional($profileUser->updated_at)->timestamp])
            : null;
    @endphp

    <div class="hg-profile-grid">
        <section class="hg-card hg-profile-summary-card">
            <div class="hg-profile-identity">
                <span class="hg-profile-avatar">
                    @if($photoUrl)
                        <img src="{{ $photoUrl }}" alt="{{ $profileUser->name }} profile picture">
                    @else
                        {{ $profileUser->initials() ?: 'U' }}
                    @endif
                </span>
                <div>
                    <span class="hg-profile-kicker">Logged-in account</span>
                    <h2>{{ $profileUser->name }}</h2>
                    <p>{{ $profileUser->email }}</p>
                </div>
            </div>

            <div class="hg-profile-info-list">
                <div class="hg-profile-info-row">
                    <span>Name</span>
                    <strong>{{ $profileUser->name }}</strong>
                </div>
                <div class="hg-profile-info-row">
                    <span>Email</span>
                    <strong>{{ $profileUser->email }}</strong>
                </div>
                <div class="hg-profile-info-row">
                    <span>Assigned Role</span>
                    <strong>{{ $profileUser->roleLabel() }}</strong>
                </div>
                <div class="hg-profile-info-row">
                    <span>Company</span>
                    <strong>{{ $profileUser->company?->name ?? 'Not assigned' }}</strong>
                </div>
                <div class="hg-profile-info-row">
                    <span>Account Status</span>
                    <strong>{{ $profileUser->accountStatusLabel() }}</strong>
                </div>
            </div>

            <div class="hg-profile-note">
                Name, email, company, and role are managed from User Management so account permissions remain controlled by an administrator.
            </div>
        </section>

        <div class="hg-profile-actions-column">
            <section class="hg-card" id="profile-picture">
                <div class="hg-section-head">
                    <div>
                        <h2>{{ $photoPath !== '' ? 'Change Profile Picture' : 'Upload Profile Picture' }}</h2>
                        <p>Your picture appears in the top-right account menu and on this profile page.</p>
                    </div>
                </div>

                @if($errors->profilePhoto->any())
                    <div class="hg-alert hg-alert-danger">
                        <strong>Could not update profile picture.</strong>
                        {{ $errors->profilePhoto->first() }}
                    </div>
                @endif

                <form method="POST" action="{{ route('accounting.profile.photo.update') }}" enctype="multipart/form-data" class="hg-profile-form">
                    @csrf
                    @method('PUT')

                    <div class="hg-field @error('profile_photo', 'profilePhoto') has-error @enderror">
                        <label for="profilePhoto">Profile Picture <span class="hg-required">*</span></label>
                        <div
                            class="hg-profile-photo-uploader"
                            data-profile-photo-uploader
                            role="group"
                            aria-label="Profile picture chooser"
                        >
                            <div class="hg-profile-photo-preview" data-profile-photo-preview>
                                @if($photoUrl)
                                    <img src="{{ $photoUrl }}" alt="Current profile picture">
                                @else
                                    {{ $profileUser->initials() ?: 'U' }}
                                @endif
                            </div>
                            <div class="hg-profile-photo-copy">
                                <strong>Choose a clear profile photo</strong>
                                <span>Click this area or drag and drop a JPG, PNG, or WebP image. Maximum file size: 2 MB.</span>
                                <div class="hg-profile-photo-controls">
                                    <button type="button" class="hg-btn" data-profile-photo-choose>Choose Photo</button>
                                    <span class="hg-profile-photo-name" data-profile-photo-name>No new photo selected</span>
                                </div>
                            </div>
                            <input
                                id="profilePhoto"
                                class="hg-profile-photo-input"
                                type="file"
                                name="profile_photo"
                                accept="image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp"
                                required
                            >
                        </div>
                        @error('profile_photo', 'profilePhoto')
                            <span class="hg-field-error">{{ $message }}</span>
                        @enderror
                    </div>

                    <button type="submit" class="hg-btn hg-btn-primary">
                        {{ $photoPath !== '' ? 'Update Profile Picture' : 'Upload Profile Picture' }}
                    </button>
                </form>
            </section>

            <section class="hg-card" id="change-password">
                <div class="hg-section-head">
                    <div>
                        <h2>Change Password</h2>
                        <p>Confirm your current password before setting a new one.</p>
                    </div>
                </div>

                @if($errors->passwordUpdate->any())
                    <div class="hg-alert hg-alert-danger">
                        <strong>Could not change password.</strong>
                        {{ $errors->passwordUpdate->first() }}
                    </div>
                @endif

                <form method="POST" action="{{ route('accounting.profile.password') }}" class="hg-profile-form">
                    @csrf
                    @method('PUT')

                    <div class="hg-field @error('current_password', 'passwordUpdate') has-error @enderror">
                        <label for="currentPassword">Current Password <span class="hg-required">*</span></label>
                        <input id="currentPassword" type="password" name="current_password" autocomplete="current-password" required>
                        @error('current_password', 'passwordUpdate')
                            <span class="hg-field-error">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="hg-grid hg-grid-2">
                        <div class="hg-field @error('new_password', 'passwordUpdate') has-error @enderror">
                            <label for="newPassword">New Password <span class="hg-required">*</span></label>
                            <input id="newPassword" type="password" name="new_password" autocomplete="new-password" required>
                            @error('new_password', 'passwordUpdate')
                                <span class="hg-field-error">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="hg-field">
                            <label for="newPasswordConfirmation">Confirm New Password <span class="hg-required">*</span></label>
                            <input id="newPasswordConfirmation" type="password" name="new_password_confirmation" autocomplete="new-password" required>
                        </div>
                    </div>

                    <small class="hg-password-hint">Use at least 8 characters and choose a password different from your current password.</small>

                    <button type="submit" class="hg-btn hg-btn-primary">Change Password</button>
                </form>
            </section>
        </div>
    </div>
</x-layouts::accounting>
