@extends('layouts.landing-admin')

@section('title', 'Landing Admin Dashboard | HisebGhor')
@section('page_heading', 'Landing Admin Dashboard')

@section('content')
<div class="page-title">
    <div>
        <span class="page-label">Landing Page</span>
        <h2>Landing Page Admin Dashboard</h2>
        <p>Separate control center for managing public HisebGhor landing content, demo/login flow, sections, and inquiries.</p>
        @if($updatedAt)
            <p class="hint" style="margin-top:6px">Last updated {{ $updatedAt->format('d M Y h:i A') }}@if($updatedBy) by {{ $updatedBy->name }}@endif.</p>
        @endif
    </div>
    <div class="actions" style="border-top:0;padding-top:0">
        <a href="{{ route('landing-admin.edit', ['section' => 'basic']) }}" class="button btn-primary">Edit Landing Page</a>
        <a href="{{ route('landing.public') }}" target="_blank" class="button btn-outline">Open Public Page</a>
    </div>
</div>

<div class="landing-dashboard-grid">
    <div class="landing-dashboard-card">
        <small>Publish Status</small>
        <strong>{{ $isPublished ? 'Live' : 'Draft' }}</strong>
        <p>{{ $isPublished ? 'Public landing page is visible.' : 'Landing page is hidden except preview mode.' }}</p>
    </div>
    <div class="landing-dashboard-card">
        <small>Enabled Sections</small>
        <strong>{{ $enabledSections }}/{{ $totalSections }}</strong>
        <p>Hero, features, pricing, FAQ, contact and other public sections.</p>
    </div>
    <div class="landing-dashboard-card">
        <small>Total Inquiries</small>
        <strong>{{ number_format($totalInquiries) }}</strong>
        <p>Historical demo/contact requests stored from the landing page.</p>
    </div>
    <div class="landing-dashboard-card">
        <small>New Inquiries</small>
        <strong>{{ number_format((int) ($statusCounts[\App\Models\LandingPageInquiry::STATUS_NEW] ?? 0)) }}</strong>
        <p>New landing inquiries waiting for review.</p>
    </div>
</div>

<div class="landing-admin-panel" style="margin-bottom:18px">
    <div class="landing-admin-panel-head">
        <div>
            <h3>Quick Edit Sections</h3>
            <p>Open only the section you want to manage. The accounting dashboard and setup menus are kept separate.</p>
        </div>
    </div>
    <div class="landing-admin-panel-body">
        <div class="landing-admin-quick-grid">
            @foreach([
                ['section' => 'basic', 'title' => 'Basic Setup', 'desc' => 'Brand, SEO, language, colors'],
                ['section' => 'nav', 'title' => 'Navigation', 'desc' => 'Landing menu links'],
                ['section' => 'hero', 'title' => 'Hero Section', 'desc' => 'Main banner and CTA buttons'],
                ['section' => 'features', 'title' => 'Feature Screens', 'desc' => 'Public feature preview cards'],
                ['section' => 'pricing', 'title' => 'Pricing', 'desc' => 'Packages and pricing notes'],
                ['section' => 'faq', 'title' => 'FAQ', 'desc' => 'Common questions'],
                ['section' => 'contact', 'title' => 'Contact', 'desc' => 'Contact/demo area labels'],
                ['section' => 'footer', 'title' => 'Footer', 'desc' => 'Footer copy'],
            ] as $item)
                <a class="landing-admin-quick-card" href="{{ route('landing-admin.edit', ['section' => $item['section']]) }}">
                    <div>
                        <strong>{{ $item['title'] }}</strong>
                        <span>{{ $item['desc'] }}</span>
                    </div>
                    <span class="landing-admin-status">Edit</span>
                </a>
            @endforeach
        </div>
    </div>
</div>

<div class="landing-admin-panel">
    <div class="landing-admin-panel-head">
        <div>
            <h3>Latest Landing Inquiries</h3>
            <p>Recent inquiries are kept here for tracking. Demo CTA buttons now send visitors to the system login page.</p>
        </div>
        <a href="{{ route('landing-admin.edit', ['section' => 'contact']) }}" class="button btn-outline">Manage Contact</a>
    </div>
    <div class="landing-admin-panel-body">
        <div class="landing-admin-table-wrap">
            <table class="landing-admin-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Business</th>
                        <th>Mobile</th>
                        <th>Status</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($latestInquiries as $inquiry)
                        <tr>
                            <td>{{ $inquiry->name }}</td>
                            <td>{{ $inquiry->business_name ?: '—' }}</td>
                            <td>{{ $inquiry->mobile ?: '—' }}</td>
                            <td><span class="landing-admin-status {{ $inquiry->status === \App\Models\LandingPageInquiry::STATUS_NEW ? '' : 'muted' }}">{{ ucfirst($inquiry->status) }}</span></td>
                            <td>{{ $inquiry->created_at?->format('d M Y h:i A') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" style="text-align:center;color:#667085;padding:26px">No landing inquiries yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
