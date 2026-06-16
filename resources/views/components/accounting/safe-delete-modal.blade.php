<div class="hg-modal" id="safe-delete-modal" data-safe-delete-modal aria-hidden="true">
    <div class="hg-modal-box hg-safe-delete-box" role="dialog" aria-modal="true" aria-labelledby="safe-delete-title">
        <div class="hg-modal-head">
            <div>
                <div class="hg-page-kicker">Permanent database deletion</div>
                <h2 id="safe-delete-title">Confirm safe deletion</h2>
            </div>
            <button class="hg-btn hg-btn-small" type="button" data-safe-delete-close aria-label="Close">✕</button>
        </div>
        <div class="hg-modal-body">
            <div class="hg-alert hg-alert-danger hg-safe-delete-warning">
                <strong data-safe-delete-entity>Selected record</strong> will be permanently deleted from the frontend, backend, and database.
            </div>

            <div data-safe-delete-dependencies-wrap>
                <h3 class="hg-safe-delete-heading">Affected dependencies</h3>
                <div class="hg-safe-delete-list" data-safe-delete-dependencies></div>
            </div>

            <div class="hg-notice" data-safe-delete-confirmation>
                Related records will lose this relationship and will be made inactive or incomplete until repaired.
            </div>

            <div class="hg-safe-delete-error" data-safe-delete-error hidden></div>

            <div class="hg-actions hg-safe-delete-actions">
                <button class="hg-btn" type="button" data-safe-delete-close>Cancel</button>
                <button class="hg-btn hg-btn-danger" type="button" data-safe-delete-confirm>
                    Yes, delete permanently
                </button>
            </div>
        </div>
    </div>
</div>
