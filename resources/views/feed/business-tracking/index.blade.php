<x-layouts::accounting title="Business Tracking Setup">
    <div class="feed-ui">
        @include('feed.partials.tabs')

        <div class="feed-page-heading">
            <div>
                <h1>Business Tracking Setup</h1>
                <p>Track every transaction by business area, operational unit and production cycle without creating duplicate ledger accounts.</p>
            </div>
            <div class="feed-heading-actions">
                <a class="feed-btn" href="#how-business-tracking-works">View Setup Guide</a>
                <button class="feed-btn feed-btn-primary" type="button" data-tracking-focus>＋ Add Tracking Unit</button>
            </div>
        </div>

        <div class="feed-info-banner feed-info-blue">
            <strong>Recommended HisebGhor design:</strong> Cattle, Fish and Vegetables should be maintained as a separate <strong>Business Tracking Dimension</strong>, not as separate Chart of Accounts. The normal sales, purchase, expense and inventory accounts remain unchanged.
        </div>



        <div class="business-layout feed-section-gap">
            <section class="feed-card">
                <div class="feed-card-header">
                    <div>
                        <div class="feed-card-title">Business Structure</div>
                        <div class="feed-card-sub">Level 1 is the business. Level 2 is configurable as Shed, Pond or Vegetable/Crop. Level 3 can store a batch, season or production cycle.</div>
                    </div>
                </div>
                <div class="feed-card-body">
                    <div class="business-tree">
                        @foreach($businessAreas as $key => $business)
                            @php($groupUnits = $businessGroups->get($key, collect()))
                            <div class="business-group">
                                <div class="business-group-head">
                                    <div class="business-identity">
                                        <div class="business-icon">{{ $business['icon'] }}</div>
                                        <div>
                                            <div class="business-name">{{ $business['name'] }}</div>
                                            <div class="business-meta">{{ $groupUnits->count() }} configured {{ strtolower($business['unit_label']) }} unit{{ $groupUnits->count() === 1 ? '' : 's' }}</div>
                                        </div>
                                    </div>
                                    <button class="feed-btn feed-btn-sm" type="button" data-prepare-tracking-unit="{{ $key }}">＋ Add {{ $business['unit_label'] }}</button>
                                </div>
                                <div class="business-children">
                                    @forelse($groupUnits as $unit)
                                        <div class="business-child {{ $unit->is_active ? '' : 'is-muted' }}">
                                            <div>
                                                <span class="branch">└─</span><strong>{{ $unit->name }}</strong>
                                                <div class="unit-code">{{ $unit->code }}</div>
                                            </div>
                                            <div>{{ $unit->unit_type }}</div>
                                            <div>
                                                @if($unit->children->count())
                                                    <span class="feed-badge">{{ $unit->children->count() }} cycle{{ $unit->children->count() === 1 ? '' : 's' }}</span>
                                                @elseif($unit->is_active)
                                                    <span class="feed-status feed-status-green">Active</span>
                                                @else
                                                    <span class="feed-status feed-status-red">Inactive</span>
                                                @endif
                                            </div>
                                            <div style="display: flex; gap: 5px;">
                                                <button
                                                    class="feed-btn feed-btn-sm"
                                                    type="button"
                                                    data-edit-unit
                                                    data-id="{{ $unit->id }}"
                                                    data-update-url="{{ route('feed.business-tracking.units.update', $unit) }}"
                                                    data-business-area="{{ $unit->business_area }}"
                                                    data-unit-type="{{ $unit->unit_type }}"
                                                    data-location="{{ $unit->location }}"
                                                    data-name="{{ $unit->name }}"
                                                    data-parent-id="{{ $unit->parent_id }}"
                                                    data-responsible-person="{{ $unit->responsible_person }}"
                                                    data-start-date="{{ optional($unit->start_date)->format('Y-m-d') }}"
                                                    data-is-active="{{ $unit->is_active ? 1 : 0 }}"
                                                    data-description="{{ $unit->description }}"
                                                >Edit</button>
                                                @if(auth()->user()->canDeleteAccountingRecords())
                                                    <form method="POST" action="{{ route('feed.business-tracking.units.destroy', $unit) }}" data-safe-delete-form>
                                                        @csrf @method('DELETE')
                                                        <button class="feed-btn feed-btn-sm feed-btn-red" type="submit">Delete</button>
                                                    </form>
                                                @endif
                                            </div>
                                        </div>

                                        @foreach($unit->children as $child)
                                            <div class="business-child business-child-nested {{ $child->is_active ? '' : 'is-muted' }}">
                                                <div>
                                                    <span class="branch">&nbsp;&nbsp;&nbsp;└─</span><strong>{{ $child->name }}</strong>
                                                    <div class="unit-code">{{ $child->code }}</div>
                                                </div>
                                                <div>{{ $child->unit_type }}</div>
                                                <div><span class="feed-status {{ $child->is_active ? 'feed-status-green' : 'feed-status-red' }}">{{ $child->is_active ? 'Active' : 'Inactive' }}</span></div>
                                                <div style="display: flex; gap: 5px;">
                                                    <button
                                                        class="feed-btn feed-btn-sm"
                                                        type="button"
                                                        data-edit-unit
                                                        data-id="{{ $child->id }}"
                                                        data-update-url="{{ route('feed.business-tracking.units.update', $child) }}"
                                                        data-business-area="{{ $child->business_area }}"
                                                        data-unit-type="{{ $child->unit_type }}"
                                                        data-location="{{ $child->location }}"
                                                        data-name="{{ $child->name }}"
                                                        data-parent-id="{{ $child->parent_id }}"
                                                        data-responsible-person="{{ $child->responsible_person }}"
                                                        data-start-date="{{ optional($child->start_date)->format('Y-m-d') }}"
                                                        data-is-active="{{ $child->is_active ? 1 : 0 }}"
                                                        data-description="{{ $child->description }}"
                                                    >Edit</button>
                                                    @if(auth()->user()->canDeleteAccountingRecords())
                                                        <form method="POST" action="{{ route('feed.business-tracking.units.destroy', $child) }}" data-safe-delete-form>
                                                            @csrf @method('DELETE')
                                                            <button class="feed-btn feed-btn-sm feed-btn-red" type="submit">Delete</button>
                                                        </form>
                                                    @endif
                                                </div>
                                            </div>
                                        @endforeach
                                    @empty
                                        <div class="feed-empty-note">No {{ strtolower($business['name']) }} tracking unit added yet.</div>
                                    @endforelse
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </section>

            <section class="feed-card">
                <div class="feed-card-header">
                    <div>
                        <div class="feed-card-title">Add or Edit Tracking Unit</div>
                        <div class="feed-card-sub">Labels change automatically for the selected business.</div>
                    </div>
                </div>
                <div class="feed-card-body">
                    <form id="businessTrackingUnitForm" method="POST" action="{{ route('feed.business-tracking.units.store') }}">
                        @csrf
                        <input type="hidden" name="_method" value="POST" data-form-method>
                        <div style="display: flex; flex-direction: column; gap: 15px;">
                            <div class="feed-field">
                                <label>Business Area <span class="feed-req">*</span></label>
                                <select id="trackingBusiness" name="business_area" class="feed-control" required>
                                    @foreach($businessAreas as $key => $business)
                                        <option value="{{ $key }}" @selected(old('business_area', array_key_first($businessAreas)) === $key)>{{ $business['name'] }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="feed-field">
                                <label>Unit Type <span class="feed-req">*</span></label>
                                <select id="trackingUnitType" name="unit_type" class="feed-control" data-old-value="{{ old('unit_type') }}" required></select>
                            </div>
                            <div class="feed-field">
                                <label>Location</label>
                                <input id="trackingUnitLocation" class="feed-control" name="location" value="{{ old('location') }}" placeholder="e.g. Farm Side A">
                            </div>
                            <div class="feed-field">
                                <label id="trackingUnitNameLabel">Unit Name <span class="feed-req">*</span></label>
                                <input id="trackingUnitName" class="feed-control" name="name" value="{{ old('name') }}" placeholder="e.g. Shed 03" required>
                            </div>

                            <div class="feed-field">
                                <label>Responsible Person</label>
                                <input id="trackingResponsiblePerson" class="feed-control" name="responsible_person" value="{{ old('responsible_person') }}" placeholder="Optional">
                            </div>
                            <div class="feed-field">
                                <label>Start Date</label>
                                <input id="trackingStartDate" class="feed-control" name="start_date" type="date" value="{{ old('start_date', now()->format('Y-m-d')) }}">
                            </div>
                            <div class="feed-field">
                                <label>Status</label>
                                <select id="trackingStatus" name="is_active" class="feed-control">
                                    <option value="1" @selected(old('is_active', '1') === '1')>Active</option>
                                    <option value="0" @selected(old('is_active') === '0')>Inactive</option>
                                </select>
                            </div>
                            <div class="feed-field">
                                <label>Description</label>
                                <textarea id="trackingDescription" class="feed-control" name="description" placeholder="Optional note about the shed, pond, crop, plot or production cycle">{{ old('description') }}</textarea>
                            </div>
                        </div>
                        <div class="feed-action-bar">
                            <button class="feed-btn" type="button" data-clear-unit-form>Clear</button>
                            <button class="feed-btn feed-btn-primary" type="submit" data-unit-submit-label>Save Tracking Unit</button>
                        </div>
                    </form>
                </div>
            </section>
        </div>

        <div class="business-layout feed-section-gap">
            <section class="feed-card">
                <div class="feed-card-header">
                    <div>
                        <div class="feed-card-title">Tracking Rules</div>
                        <div class="feed-card-sub">Control when a business selection is required.</div>
                    </div>
                </div>
                <div class="feed-card-body">
                    <form method="POST" action="{{ route('feed.business-tracking.rules.save') }}">
                        @csrf
                        <div class="rule-list">
                            <div class="rule-row">
                                <div><div class="rule-title">Require business tracking for farm transactions</div><div class="rule-desc">Purchase, sale, expense, asset purchase and owner transactions cannot be posted without a business tag.</div></div>
                                <label class="mini-switch"><input type="checkbox" name="require_farm_tracking" value="1" @checked($settings->require_farm_tracking)><span class="mini-slider"></span></label>
                            </div>
                            <div class="rule-row">
                                <div><div class="rule-title">Allow mixed businesses in one transaction</div><div class="rule-desc">Each item or cost line can be assigned to a different shed, pond or vegetable.</div></div>
                                <label class="mini-switch"><input type="checkbox" name="allow_mixed_businesses" value="1" @checked($settings->allow_mixed_businesses)><span class="mini-slider"></span></label>
                            </div>
                            <div class="rule-row">
                                <div><div class="rule-title">Allow General / Shared Farm allocation</div><div class="rule-desc">Shared costs can be distributed between businesses by percentage.</div></div>
                                <label class="mini-switch"><input type="checkbox" name="allow_shared_allocation" value="1" @checked($settings->allow_shared_allocation)><span class="mini-slider"></span></label>
                            </div>
                            <div class="rule-row">
                                <div><div class="rule-title">Track optional batch, season or production cycle</div><div class="rule-desc">Examples: Dairy Batch 2026, Rohu Cycle 2026 or Tomato Winter 2026.</div></div>
                                <label class="mini-switch"><input type="checkbox" name="track_production_cycle" value="1" @checked($settings->track_production_cycle)><span class="mini-slider"></span></label>
                            </div>
                        </div>
                        <div class="feed-action-bar">
                            <div class="feed-help">These settings create reporting tags. They do not create new debit or credit accounts.</div>
                            <button class="feed-btn feed-btn-primary" type="submit">Save Rules</button>
                        </div>
                    </form>
                </div>
            </section>

            <section class="feed-card">
                <div class="feed-card-header">
                    <div>
                        <div class="feed-card-title">Default Assignments</div>
                        <div class="feed-card-sub">Optional defaults reduce repetitive selection.</div>
                    </div>
                </div>
                <div class="feed-card-body">
                    <div class="feed-table-wrap">
                        <table class="feed-table" style="min-width:650px">
                            <thead><tr><th>Source</th><th>Default Business</th><th>Default Unit</th><th>Override</th><th></th></tr></thead>
                            <tbody>
                                @forelse($assignments as $assignment)
                                    <tr>
                                        <td><strong>{{ $assignmentSourceLabels[$assignment->id] ?? 'Removed source' }}</strong><div class="feed-small">{{ $assignment->source_type_label }}</div></td>
                                        <td>{{ $businessAreas[$assignment->business_area]['name'] ?? ucfirst($assignment->business_area) }}</td>
                                        <td>{{ $assignment->trackingUnit?->name ?: 'Ask during entry' }}</td>
                                        <td><span class="feed-status {{ $assignment->allow_override ? 'feed-status-green' : 'feed-status-amber' }}">{{ $assignment->allow_override ? 'Allowed' : 'Locked' }}</span></td>
                                        <td>
                                            <form method="POST" action="{{ route('feed.business-tracking.default-assignments.destroy', $assignment) }}" onsubmit="return confirm('Remove this default assignment?')">
                                                @csrf
                                                @method('DELETE')
                                                <button class="feed-btn feed-btn-sm" type="submit">Delete</button>
                                            </form>
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="5" class="feed-empty-note">No default assignment added yet.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <button class="feed-btn feed-btn-soft feed-btn-sm" style="margin-top:10px" type="button" data-toggle-default-assignment>＋ Add Default Assignment</button>

                    <form method="POST" action="{{ route('feed.business-tracking.default-assignments.store') }}" class="default-assignment-form" data-default-assignment-form hidden>
                        @csrf
                        <div class="feed-grid-2">
                            <div class="feed-field">
                                <label>Source Type <span class="feed-req">*</span></label>
                                <select name="source_type" id="assignmentSourceType" class="feed-control" required>
                                    @foreach(\App\Models\Feed\FeedBusinessTrackingDefaultAssignment::SOURCE_TYPES as $sourceType => $sourceLabel)
                                        <option value="{{ $sourceType }}">{{ $sourceLabel }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="feed-field" data-source-select-wrap>
                                <label>Source <span class="feed-req">*</span></label>
                                <select name="source_id" id="assignmentSourceId" class="feed-control">
                                    @foreach($items as $item)
                                        <option value="{{ $item->id }}" data-source-type="feed_item">{{ $item->name }} ({{ $item->code }})</option>
                                    @endforeach
                                    @foreach($warehouses as $warehouse)
                                        <option value="{{ $warehouse->id }}" data-source-type="warehouse">{{ $warehouse->name }} ({{ $warehouse->code }})</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="feed-field" data-source-manual-wrap hidden>
                                <label>Manual Source <span class="feed-req">*</span></label>
                                <input name="source_label" class="feed-control" placeholder="e.g. Shared electricity cost">
                            </div>
                            <div class="feed-field">
                                <label>Default Business <span class="feed-req">*</span></label>
                                <select name="business_area" id="assignmentBusinessArea" class="feed-control" required>
                                    @foreach($businessAreas as $key => $business)
                                        <option value="{{ $key }}">{{ $business['name'] }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="feed-field">
                                <label>Default Unit</label>
                                <select name="business_tracking_unit_id" id="assignmentUnit" class="feed-control"></select>
                            </div>
                        </div>
                        <div class="feed-grid-2" style="margin-top:12px">
                            <div class="check-row"><label class="mini-switch"><input type="checkbox" name="allow_override" value="1" checked><span class="mini-slider"></span></label><div><div class="rule-title">Allow override</div><div class="rule-desc">The user can change the default while posting.</div></div></div>
                            <div class="check-row"><label class="mini-switch"><input type="checkbox" name="is_active" value="1" checked><span class="mini-slider"></span></label><div><div class="rule-title">Active assignment</div><div class="rule-desc">Inactive assignments are ignored during entry.</div></div></div>
                        </div>
                        <div class="feed-action-bar"><button class="feed-btn" type="button" data-toggle-default-assignment>Cancel</button><button class="feed-btn feed-btn-primary" type="submit">Save Default Assignment</button></div>
                    </form>
                </div>
            </section>
        </div>

        <section class="feed-card feed-section-gap" id="how-business-tracking-works">
            <div class="feed-card-header">
                <div>
                    <div class="feed-card-title">How It Works in Transactions</div>
                    <div class="feed-card-sub">The user selects one of three simple tracking modes.</div>
                </div>
            </div>
            <div class="feed-card-body">
                <div class="implementation-grid">
                    <div class="implementation-card"><div class="implementation-number">1</div><b>Single Business</b><p>Select Business Area, then Shed, Pond or Vegetable once at the transaction header. All lines inherit the same tag.</p></div>
                    <div class="implementation-card"><div class="implementation-number">2</div><b>Mixed by Line</b><p>Use when one invoice contains items for different businesses. Each line receives its own business, unit and cycle.</p></div>
                    <div class="implementation-card"><div class="implementation-number">3</div><b>General / Shared</b><p>Use for electricity, security or common labour. Allocate the transaction between businesses, and require the total to equal 100%.</p></div>
                </div>
            </div>
        </section>
    </div>

    <script>
        window.HISEBGHOR_BUSINESS_TRACKING = {
            storeUrl: @json(route('feed.business-tracking.units.store')),
            config: @json($businessConfigJson),
            defaultBusinessArea: @json(array_key_first($businessAreas)),
            oldBusinessArea: @json(old('business_area', array_key_first($businessAreas))),
            oldParentId: @json(old('parent_id')),
            oldUnitType: @json(old('unit_type')),
            oldAssignmentBusinessArea: @json(old('business_area', array_key_first($businessAreas))),
        };
    </script>
</x-layouts::accounting>
