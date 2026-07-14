<x-layouts::accounting title="Business Areas">
    <div class="feed-ui">
        @include('feed.partials.tabs')

        <div class="feed-page-heading">
            <div>
                <h1>Business Area Master Data</h1>
                <p>Manage business areas. Active areas appear in the Business Area dropdown in tracking setups.</p>
            </div>
        </div>

        <div class="business-master-layout">
            <section class="feed-card">
                <div class="feed-card-header">
                    <div>
                        <div class="feed-card-title">Business Area Master Data</div>
                        <div class="feed-card-sub">Add business areas here. Active areas appear in the Business Area dropdown below.</div>
                    </div>
                </div>
                <div class="feed-card-body">
                    <div class="feed-table-wrap business-area-table-wrap">
                        <table class="feed-table business-area-table">
                            <thead><tr><th>Area</th><th>Unit Label</th><th>Status</th></tr></thead>
                            <tbody>
                                @foreach($businessAreaRecords as $area)
                                    <tr>
                                        <td><strong>{{ $area->name }}</strong><div class="feed-small">{{ strtoupper(str_replace('_', '-', $area->code)) }}</div></td>
                                        <td>{{ $area->unit_label }}</td>
                                        <td><span class="feed-status {{ $area->is_active ? 'feed-status-green' : 'feed-status-red' }}">{{ $area->is_active ? 'Active' : 'Inactive' }}</span></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>

            <section class="feed-card">
                <div class="feed-card-header">
                    <div>
                        <div class="feed-card-title">Add Business Area</div>
                        <div class="feed-card-sub">Only three fields are needed. The code is generated automatically from the area name.</div>
                    </div>
                </div>
                <div class="feed-card-body">
                    <form method="POST" action="{{ route('master.business-areas.store') }}">
                        @csrf
                        <div class="feed-grid-3 business-area-form-grid">
                            <div class="feed-field">
                                <label>Area Name <span class="feed-req">*</span></label>
                                <input name="area_name" class="feed-control" value="{{ old('area_name') }}" placeholder="e.g. Goat, Poultry, Banana" required>
                            </div>
                            <div class="feed-field">
                                <label>Unit Label <span class="feed-req">*</span></label>
                                <input name="area_unit_label" class="feed-control" value="{{ old('area_unit_label', 'Unit') }}" placeholder="e.g. Shed, Pond, Plot" required>
                            </div>
                            <div class="feed-field">
                                <label>Status <span class="feed-req">*</span></label>
                                <select name="area_is_active" class="feed-control" required>
                                    <option value="1" @selected(old('area_is_active', '1') === '1')>Active</option>
                                    <option value="0" @selected(old('area_is_active') === '0')>Inactive</option>
                                </select>
                            </div>
                        </div>
                        <div class="feed-action-bar business-area-action-bar">
                            <div class="feed-help">After saving, the active area is available in Business Area, Default Business and tracking setup dropdowns.</div>
                            <button class="feed-btn feed-btn-primary" type="submit">Save Business Area</button>
                        </div>
                    </form>
                </div>
            </section>
        </div>
    </div>
</x-layouts::accounting>
